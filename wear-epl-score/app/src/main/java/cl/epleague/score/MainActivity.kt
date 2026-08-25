package cl.epleague.score

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.wear.compose.material3.MaterialTheme
import androidx.wear.compose.material3.Text
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import java.time.LocalDateTime
import java.time.format.DateTimeFormatter
import java.util.Locale

private val Navy = Color(0xFF07101C)
private val NavyLight = Color(0xFF1C2F48)
private val Gold = Color(0xFFC9A762)
private val GoldSoft = Color(0xFFF5E6BF)
private val White = Color(0xFFF8FAFD)
private val Muted = Color(0xFF9EABBD)
private val Green = Color(0xFF62C985)
private val Red = Color(0xFFE56E73)

private enum class AppScreen { LOADING, PAIR, MATCHES, FORMAT, SCORE, CONFIRM, SUCCESS }

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent { MaterialTheme { EplScoreApp() } }
    }
}

@Composable
private fun EplScoreApp() {
    val context = LocalContext.current
    val store = remember { EplStore(context) }
    val api = remember { EplApi() }
    val scope = rememberCoroutineScope()

    var token by remember { mutableStateOf(store.accessToken()) }
    var session by remember { mutableStateOf(store.activeSession()) }
    var screen by remember {
        mutableStateOf(if (session != null) AppScreen.SCORE else AppScreen.LOADING)
    }
    var matches by remember { mutableStateOf<List<MatchInfo>>(emptyList()) }
    var selectedMatch by remember { mutableStateOf<MatchInfo?>(null) }
    var pairing by remember { mutableStateOf<PairStart?>(null) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf("") }
    var successMessage by remember { mutableStateOf("") }

    fun loadMatches() {
        if (token.isBlank()) {
            screen = AppScreen.PAIR
            return
        }
        scope.launch {
            busy = true
            error = ""
            try {
                matches = api.matches(token)
                screen = AppScreen.MATCHES
            } catch (failure: ApiException) {
                if (failure.statusCode == 401) {
                    store.clearAccessToken()
                    token = ""
                    screen = AppScreen.PAIR
                }
                error = failure.message.orEmpty()
            } catch (failure: Exception) {
                error = "No pudimos conectar con EPL. Revisa la conexión."
            } finally {
                busy = false
            }
        }
    }

    LaunchedEffect(Unit) {
        if (session == null) loadMatches()
    }

    LaunchedEffect(pairing?.deviceCode) {
        val pair = pairing ?: return@LaunchedEffect
        while (isActive && screen == AppScreen.PAIR) {
            delay(pair.pollIntervalSeconds * 1_000)
            try {
                when (val status = api.pairingStatus(pair.deviceCode)) {
                    is PairStatus.Approved -> {
                        token = status.token
                        store.saveAccessToken(status.token)
                        pairing = null
                        loadMatches()
                        break
                    }
                    PairStatus.Expired, PairStatus.Invalid -> {
                        pairing = null
                        error = "El código venció. Genera uno nuevo."
                        break
                    }
                    PairStatus.Pending -> Unit
                }
            } catch (_: Exception) {
                // La vinculación sigue vigente; se vuelve a consultar sin despertar servicios extra.
            }
        }
    }

    Box(Modifier.fillMaxSize().background(Navy)) {
        when (screen) {
            AppScreen.LOADING -> CenterMessage("EPL", if (busy) "Buscando tus partidos…" else "Iniciando…")
            AppScreen.PAIR -> PairScreen(
                pairing = pairing,
                busy = busy,
                error = error,
                onStart = {
                    scope.launch {
                        busy = true
                        error = ""
                        try {
                            pairing = api.startPairing()
                        } catch (_: Exception) {
                            error = "No pudimos generar el código. Revisa la conexión."
                        } finally {
                            busy = false
                        }
                    }
                },
                onDemo = {
                    selectedMatch = MatchInfo.demo()
                    screen = AppScreen.FORMAT
                },
            )
            AppScreen.MATCHES -> MatchesScreen(
                matches = matches,
                activeSession = session,
                busy = busy,
                error = error,
                onRefresh = { loadMatches() },
                onResume = { screen = AppScreen.SCORE },
                onSelect = {
                    selectedMatch = it
                    screen = AppScreen.FORMAT
                },
                onUnlink = {
                    store.clearAccessToken()
                    token = ""
                    matches = emptyList()
                    screen = AppScreen.PAIR
                },
            )
            AppScreen.FORMAT -> FormatScreen(
                match = selectedMatch ?: MatchInfo.demo(),
                onChoose = { golden ->
                    val newSession = ScoreSession(selectedMatch ?: MatchInfo.demo(), golden)
                    session = newSession
                    store.saveSession(newSession)
                    screen = AppScreen.SCORE
                },
                onBack = { screen = if (token.isBlank()) AppScreen.PAIR else AppScreen.MATCHES },
            )
            AppScreen.SCORE -> {
                val currentSession = session
                if (currentSession == null) {
                    CenterMessage("Sin partido", "Vuelve a elegir un encuentro.")
                } else {
                    ScoreScreen(
                        session = currentSession,
                        onPoint = { side ->
                            val updated = ScoreEngine.addPoint(currentSession, side)
                            session = updated
                            store.saveSession(updated)
                        },
                        onUndo = {
                            val updated = ScoreEngine.undo(currentSession)
                            session = updated
                            store.saveSession(updated)
                        },
                        onFinish = { screen = AppScreen.CONFIRM },
                        onMinimize = { screen = if (token.isBlank()) AppScreen.PAIR else AppScreen.MATCHES },
                    )
                }
            }
            AppScreen.CONFIRM -> session?.let { currentSession ->
                ConfirmScreen(
                    session = currentSession,
                    busy = busy,
                    error = error,
                    onBack = { screen = AppScreen.SCORE },
                    onSend = {
                        scope.launch {
                            busy = true
                            error = ""
                            try {
                                successMessage = if (currentSession.match.demo) {
                                    "Prueba completada. El resultado real se enviará a EPL."
                                } else {
                                    if (token.isBlank()) throw ApiException("Vuelve a vincular el reloj antes de enviar.", 401)
                                    api.submitResult(token, currentSession)
                                }
                                store.clearSession()
                                session = null
                                screen = AppScreen.SUCCESS
                            } catch (failure: Exception) {
                                error = failure.message ?: "No se pudo enviar. El marcador quedó guardado."
                            } finally {
                                busy = false
                            }
                        }
                    },
                )
            } ?: CenterMessage("Sin partido", "No hay un marcador activo.")
            AppScreen.SUCCESS -> SuccessScreen(successMessage) {
                if (token.isBlank()) screen = AppScreen.PAIR else loadMatches()
            }
        }
    }
}

@Composable
private fun PairScreen(
    pairing: PairStart?,
    busy: Boolean,
    error: String,
    onStart: () -> Unit,
    onDemo: () -> Unit,
) {
    Column(
        Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(horizontal = 22.dp, vertical = 14.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        BrandMark()
        Text("Vincula tu reloj", color = White, fontSize = 18.sp, fontWeight = FontWeight.Bold)
        Text(
            if (pairing == null) "Genera un código y confírmalo desde tu cuenta EPL."
            else "Entra a epleague.cl/reloj.php e ingresa:",
            color = Muted,
            fontSize = 11.sp,
            textAlign = TextAlign.Center,
            modifier = Modifier.padding(vertical = 8.dp),
        )
        if (pairing != null) {
            Text(pairing.userCode, color = Gold, fontSize = 29.sp, fontWeight = FontWeight.Black)
            Text("Esperando confirmación…", color = Green, fontSize = 10.sp, modifier = Modifier.padding(8.dp))
        } else {
            EplButton(if (busy) "Generando…" else "Generar código", onStart, enabled = !busy)
        }
        if (error.isNotBlank()) ErrorText(error)
        Spacer(Modifier.height(8.dp))
        Text("PROTOTIPO", color = Gold, fontSize = 9.sp, fontWeight = FontWeight.Bold)
        Text("Puedes probar el marcador sin vincular.", color = Muted, fontSize = 10.sp)
        MiniButton("Abrir partido de prueba", onDemo)
    }
}

@Composable
private fun MatchesScreen(
    matches: List<MatchInfo>,
    activeSession: ScoreSession?,
    busy: Boolean,
    error: String,
    onRefresh: () -> Unit,
    onResume: () -> Unit,
    onSelect: (MatchInfo) -> Unit,
    onUnlink: () -> Unit,
) {
    LazyColumn(
        Modifier.fillMaxSize().padding(horizontal = 14.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(7.dp),
    ) {
        item {
            BrandMark()
            Text("Mis partidos", color = White, fontSize = 19.sp, fontWeight = FontWeight.Bold)
            Text("Elige el que vas a jugar", color = Muted, fontSize = 10.sp)
        }
        activeSession?.let { active ->
            item {
                MatchCard(active.match, label = "CONTINUAR MARCADOR", forceEnabled = true, onClick = onResume)
            }
        }
        if (error.isNotBlank()) item { ErrorText(error) }
        if (matches.isEmpty() && !busy) {
            item { Text("No tienes partidos disponibles.", color = Muted, fontSize = 11.sp, modifier = Modifier.padding(16.dp)) }
        }
        items(matches, key = { it.id }) { match -> MatchCard(match, onClick = { onSelect(match) }) }
        item {
            MiniButton(if (busy) "Actualizando…" else "Actualizar", onRefresh, enabled = !busy)
            MiniButton("Desvincular reloj", onUnlink, danger = true)
            Spacer(Modifier.height(18.dp))
        }
    }
}

@Composable
private fun MatchCard(
    match: MatchInfo,
    label: String = "ABRIR PARTIDO",
    forceEnabled: Boolean = false,
    onClick: () -> Unit,
) {
    val enabled = forceEnabled || match.canSubmit
    Column(
        Modifier.fillMaxWidth().alpha(if (enabled) 1f else .58f)
            .background(NavyLight, RoundedCornerShape(18.dp))
            .clickable(enabled = enabled, onClick = onClick)
            .padding(13.dp),
    ) {
        Text(match.leagueName.uppercase(), color = Gold, fontSize = 8.sp, fontWeight = FontWeight.Bold, maxLines = 1)
        Text(
            "${match.localName}  vs  ${match.visitorName}",
            color = White,
            fontSize = 12.sp,
            fontWeight = FontWeight.Bold,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
        )
        Text(
            if (enabled) "${compactDate(match.scheduledAt)} · $label" else match.blockedReason,
            color = if (enabled) Green else Red,
            fontSize = 9.sp,
            maxLines = 2,
        )
    }
}

@Composable
private fun FormatScreen(match: MatchInfo, onChoose: (Boolean) -> Unit, onBack: () -> Unit) {
    Column(
        Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        BrandMark()
        Text("Formato del partido", color = White, fontSize = 17.sp, fontWeight = FontWeight.Bold)
        Text("${match.localName} vs ${match.visitorName}", color = Muted, fontSize = 10.sp, textAlign = TextAlign.Center)
        Spacer(Modifier.height(10.dp))
        EplButton("Punto de oro", { onChoose(true) })
        MiniButton("Ventaja tradicional", { onChoose(false) })
        MiniButton("Volver", onBack)
    }
}

@Composable
private fun ScoreScreen(
    session: ScoreSession,
    onPoint: (Side) -> Unit,
    onUndo: () -> Unit,
    onFinish: () -> Unit,
    onMinimize: () -> Unit,
) {
    val haptic = LocalHapticFeedback.current
    val (localPoint, visitorPoint) = ScoreEngine.pointLabels(session)
    val state = session.current
    Column(
        Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(horizontal = 12.dp, vertical = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(session.match.roundName.uppercase(), color = Gold, fontSize = 8.sp, fontWeight = FontWeight.Bold)
        Text(
            if (state.completedSets.isEmpty()) "Primer set" else state.completedSets.joinToString("  ") { "${it.local}-${it.visitor}" },
            color = White,
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
        )
        Text(if (state.tieBreak) "TIE-BREAK" else if (session.goldenPoint) "PUNTO DE ORO" else "VENTAJA", color = Muted, fontSize = 8.sp)

        Row(Modifier.fillMaxWidth().height(92.dp).padding(top = 5.dp), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            ScoreSide(
                name = session.match.localName,
                games = state.localGames,
                points = localPoint,
                color = Gold,
                modifier = Modifier.weight(1f).fillMaxHeight(),
            ) {
                haptic.performHapticFeedback(HapticFeedbackType.LongPress)
                onPoint(Side.LOCAL)
            }
            ScoreSide(
                name = session.match.visitorName,
                games = state.visitorGames,
                points = visitorPoint,
                color = Color(0xFF6FA8E8),
                modifier = Modifier.weight(1f).fillMaxHeight(),
            ) {
                haptic.performHapticFeedback(HapticFeedbackType.LongPress)
                onPoint(Side.VISITOR)
            }
        }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            MiniButton("↶ Deshacer", onUndo, enabled = session.history.isNotEmpty(), modifier = Modifier.weight(1f))
            MiniButton("Ocultar", onMinimize, modifier = Modifier.weight(1f))
        }
        if (state.finished) EplButton("Revisar resultado", onFinish, color = Green)
        Spacer(Modifier.height(14.dp))
    }
}

@Composable
private fun ScoreSide(name: String, games: Int, points: String, color: Color, modifier: Modifier, onClick: () -> Unit) {
    Column(
        modifier.background(color, RoundedCornerShape(22.dp)).clickable(onClick = onClick).padding(8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Text(name, color = Navy, fontSize = 9.sp, fontWeight = FontWeight.Bold, maxLines = 2, textAlign = TextAlign.Center)
        Row(verticalAlignment = Alignment.Bottom, horizontalArrangement = Arrangement.spacedBy(5.dp)) {
            Text(games.toString(), color = Navy, fontSize = 31.sp, fontWeight = FontWeight.Black)
            Text(points, color = NavyLight, fontSize = 17.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(bottom = 5.dp))
        }
    }
}

@Composable
private fun ConfirmScreen(session: ScoreSession, busy: Boolean, error: String, onBack: () -> Unit, onSend: () -> Unit) {
    Column(
        Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        BrandMark()
        Text("Confirma el resultado", color = White, fontSize = 17.sp, fontWeight = FontWeight.Bold)
        Text("${session.match.localName} vs ${session.match.visitorName}", color = Muted, fontSize = 10.sp, textAlign = TextAlign.Center)
        Text(
            session.current.completedSets.joinToString("  ·  ") { "${it.local}-${it.visitor}" },
            color = Gold,
            fontSize = 24.sp,
            fontWeight = FontWeight.Black,
            modifier = Modifier.padding(vertical = 10.dp),
        )
        Text("Al confirmar, EPL actualizará el partido y notificará al rival.", color = Muted, fontSize = 9.sp, textAlign = TextAlign.Center)
        if (error.isNotBlank()) ErrorText(error)
        EplButton(if (busy) "Enviando…" else "Enviar a EPL", onSend, enabled = !busy, color = Green)
        MiniButton("Volver al marcador", onBack, enabled = !busy)
    }
}

@Composable
private fun SuccessScreen(message: String, onContinue: () -> Unit) {
    Column(
        Modifier.fillMaxSize().padding(22.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Box(Modifier.size(54.dp).background(Green, CircleShape), contentAlignment = Alignment.Center) {
            Text("✓", color = Navy, fontSize = 30.sp, fontWeight = FontWeight.Black)
        }
        Text("¡Listo!", color = White, fontSize = 22.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(top = 8.dp))
        Text(message, color = Muted, fontSize = 10.sp, textAlign = TextAlign.Center, modifier = Modifier.padding(7.dp))
        EplButton("Mis partidos", onContinue)
    }
}

@Composable
private fun BrandMark() {
    Box(
        Modifier.padding(bottom = 7.dp).size(38.dp).background(Gold, CircleShape),
        contentAlignment = Alignment.Center,
    ) { Text("EPL", color = Navy, fontSize = 11.sp, fontWeight = FontWeight.Black) }
}

@Composable
private fun CenterMessage(title: String, subtitle: String) {
    Column(
        Modifier.fillMaxSize().padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        BrandMark()
        Text(title, color = White, fontSize = 19.sp, fontWeight = FontWeight.Bold)
        Text(subtitle, color = Muted, fontSize = 10.sp, textAlign = TextAlign.Center)
    }
}

@Composable
private fun EplButton(label: String, onClick: () -> Unit, enabled: Boolean = true, color: Color = Gold) {
    Box(
        Modifier.fillMaxWidth().padding(top = 8.dp).alpha(if (enabled) 1f else .5f)
            .background(color, RoundedCornerShape(24.dp)).clickable(enabled = enabled, onClick = onClick)
            .padding(horizontal = 12.dp, vertical = 11.dp),
        contentAlignment = Alignment.Center,
    ) { Text(label, color = Navy, fontSize = 11.sp, fontWeight = FontWeight.Black, textAlign = TextAlign.Center) }
}

@Composable
private fun MiniButton(
    label: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    danger: Boolean = false,
) {
    Box(
        modifier.padding(top = 7.dp).alpha(if (enabled) 1f else .45f)
            .background(if (danger) Color(0xFF3A2026) else NavyLight, RoundedCornerShape(18.dp))
            .clickable(enabled = enabled, onClick = onClick).padding(horizontal = 10.dp, vertical = 9.dp),
        contentAlignment = Alignment.Center,
    ) { Text(label, color = if (danger) Red else White, fontSize = 9.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center) }
}

@Composable
private fun ErrorText(message: String) {
    Text(
        message,
        color = Red,
        fontSize = 9.sp,
        textAlign = TextAlign.Center,
        modifier = Modifier.fillMaxWidth().padding(top = 8.dp).background(Color(0xFF321A20), RoundedCornerShape(12.dp)).padding(8.dp),
    )
}

private fun compactDate(raw: String): String {
    if (raw.isBlank()) return "Sin fecha"
    return runCatching {
        val parsed = LocalDateTime.parse(raw.replace(' ', 'T'))
        parsed.format(DateTimeFormatter.ofPattern("dd MMM · HH:mm", Locale.forLanguageTag("es-CL")))
    }.getOrElse { raw.take(16) }
}
