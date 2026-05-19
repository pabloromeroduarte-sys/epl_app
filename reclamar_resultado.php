<?php
$page_title = 'Reclamar Resultado';
$player_tab = 'resultado';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/mail.php';
epl_require_login();

epl_ensure_disputas_schema();

$jugador = epl_jugador_actual();
$db      = epl_db();

$partido_id = (int)($_GET['partido_id'] ?? $_POST['partido_id'] ?? 0);
$ok    = false;
$error = '';

// ── Cargar partido ─────────────────────────────────────────────
$partido = null;
if ($partido_id > 0) {
    $st = $db->prepare("
        SELECT p.*,
               el.nombre AS local_nombre,
               ev.nombre AS visitante_nombre,
               l.nombre  AS liga_nombre,
               g.nombre  AS ganador_nombre,
               r.nombre  AS recinto_nombre
        FROM partidos p
        JOIN equipos el  ON el.id = p.equipo_local_id
        JOIN equipos ev  ON ev.id = p.equipo_visitante_id
        JOIN ligas l     ON l.id  = p.liga_id
        LEFT JOIN equipos g  ON g.id  = p.ganador_id
        LEFT JOIN recintos r ON r.id  = p.recinto_id
        WHERE p.id = ? AND p.estado = 'jugado'
    ");
    $st->execute([$partido_id]);
    $partido = $st->fetch();
}

// ── Validaciones de acceso ────────────────────────────────────
$puede_reclamar = false;
$razon_no_puede = '';
$es_rival       = false;
$disputa_previa = null;

if ($partido) {
    // ¿El jugador pertenece a alguno de los dos equipos?
    $liga_id = (int)$partido['liga_id'];
    $stEq = $db->prepare("
        SELECT equipo_id FROM liga_equipos
        WHERE liga_id = ? AND jugador_id = ?
          AND equipo_id IN (?, ?)
        LIMIT 1
    ");
    $stEq->execute([$liga_id, $jugador['id'], $partido['equipo_local_id'], $partido['equipo_visitante_id']]);
    $mi_equipo_row = $stEq->fetch();

    if (!$mi_equipo_row) {
        $razon_no_puede = 'No eres parte de este partido.';
    } else {
        $mi_equipo_id = (int)$mi_equipo_row['equipo_id'];

        // ¿Soy del equipo que ingresó el resultado?
        $ingresado_por = (int)($partido['ingresado_por'] ?? 0);
        $stIng = $db->prepare("SELECT equipo_id FROM liga_equipos WHERE liga_id=? AND jugador_id=? AND equipo_id IN (?,?) LIMIT 1");
        $stIng->execute([$liga_id, $ingresado_por, $partido['equipo_local_id'], $partido['equipo_visitante_id']]);
        $equipo_ingreso = $stIng->fetch();
        $equipo_ingreso_id = $equipo_ingreso ? (int)$equipo_ingreso['equipo_id'] : 0;

        if ($equipo_ingreso_id > 0 && $mi_equipo_id === $equipo_ingreso_id) {
            $razon_no_puede = 'Fuiste tu equipo quien ingresó este resultado. Solo el equipo rival puede reclamarlo.';
        } else {
            $es_rival = true;

            // ¿Expiró la ventana de 24h?
            $ingresado_at = $partido['resultado_ingresado_at'];
            if (!$ingresado_at) {
                // Si no hay timestamp (resultado antiguo), no se puede reclamar
                $razon_no_puede = 'Este resultado fue ingresado antes de que existiera el sistema de reclamos.';
            } else {
                $expires = strtotime($ingresado_at) + 86400; // +24h
                if (time() > $expires) {
                    $razon_no_puede = 'El plazo de 24 horas para reclamar este resultado ha vencido.';
                } else {
                    // ¿Ya existe una disputa pendiente de este partido?
                    $stD = $db->prepare("SELECT * FROM partido_disputas WHERE partido_id=? AND estado='pendiente' LIMIT 1");
                    $stD->execute([$partido_id]);
                    $disputa_previa = $stD->fetch();
                    if ($disputa_previa) {
                        $razon_no_puede = 'Ya existe un reclamo pendiente para este partido. Los administradores lo están revisando.';
                    } else {
                        $puede_reclamar = true;
                    }
                }
            }
        }
    }
}

// ── Procesar reclamo ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puede_reclamar) {
    $motivo = trim($_POST['motivo'] ?? '');
    if (strlen($motivo) < 10) {
        $error = 'Por favor describe el problema con al menos 10 caracteres.';
    } else {
        $expires_at = date('Y-m-d H:i:s', strtotime($partido['resultado_ingresado_at']) + 86400);

        $db->prepare("
            INSERT INTO partido_disputas (partido_id, jugador_id, motivo, expires_at)
            VALUES (?, ?, ?, ?)
        ")->execute([$partido_id, $jugador['id'], $motivo, $expires_at]);

        $disputa_id = (int)$db->lastInsertId();

        // Datos para la notificación
        $sets_txt = [];
        if ($partido['games_s1_local'] !== null) $sets_txt[] = $partido['games_s1_local'] . '-' . $partido['games_s1_visitante'];
        if ($partido['games_s2_local'] !== null) $sets_txt[] = $partido['games_s2_local'] . '-' . $partido['games_s2_visitante'];
        if ($partido['games_s3_local'] !== null) $sets_txt[] = $partido['games_s3_local'] . '-' . $partido['games_s3_visitante'];
        $resultado_sets = implode(' / ', $sets_txt) ?: "{$partido['sets_local']}-{$partido['sets_visitante']}";

        $asunto_admin = "⚠️ Reclamo de resultado: {$partido['local_nombre']} vs {$partido['visitante_nombre']}";
        $url_admin    = epl_url("admin/disputas.php");

        // Notificar a todos los administradores
        foreach (epl_admins_ids() as $admin_id) {
            epl_notif_crear(
                (int)$admin_id,
                'disputa',
                $asunto_admin,
                "{$jugador['nombre']} {$jugador['apellido']} reclama el resultado de {$partido['local_nombre']} vs {$partido['visitante_nombre']}. Motivo: {$motivo}",
                $url_admin,
                true // skip plain email, enviamos visual
            );
            epl_mail_partido_visual(
                (int)$admin_id,
                $asunto_admin,
                $partido['local_nombre'],
                $partido['visitante_nombre'],
                [
                    ['icon' => '🎾', 'label' => 'Resultado registrado', 'valor' => $resultado_sets],
                    ['icon' => '🏆', 'label' => 'Ganador registrado',   'valor' => $partido['ganador_nombre'] ?? '—'],
                    ['icon' => '👤', 'label' => 'Reclama',              'valor' => $jugador['nombre'] . ' ' . $jugador['apellido']],
                    ['icon' => '💬', 'label' => 'Motivo',               'valor' => $motivo],
                    ['icon' => '📅', 'label' => 'Liga',                 'valor' => $partido['liga_nombre']],
                ],
                'Un jugador ha reclamado el resultado de este partido.',
                '⚠️ Revisa el partido y corrige el marcador si corresponde.',
                $url_admin,
                '🔍 Ver Disputas'
            );
        }

        $ok = true;
        $puede_reclamar = false;
    }
}

// ── Tiempo restante ───────────────────────────────────────────
$segundos_restantes = 0;
$horas_restantes    = 0;
if ($partido && $partido['resultado_ingresado_at'] && $puede_reclamar) {
    $segundos_restantes = max(0, (strtotime($partido['resultado_ingresado_at']) + 86400) - time());
    $horas_restantes    = floor($segundos_restantes / 3600);
    $minutos_restantes  = floor(($segundos_restantes % 3600) / 60);
}

require_once 'includes/header.php';
?>

<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main">

  <div class="dash-header">
    <h1 class="dash-title">Reclamar Resultado</h1>
    <p style="color:var(--gray-400);font-size:.88rem">Disputa el marcador si hay un error.</p>
  </div>

  <?php if (!$partido): ?>
  <div class="rr-empty">
    <div class="rr-empty-icon">🔍</div>
    <h3>Partido no encontrado</h3>
    <p>El partido no existe o ya fue procesado.</p>
    <a href="dashboard.php" class="btn btn-navy" style="margin-top:1rem">Volver al Inicio</a>
  </div>

  <?php elseif ($ok): ?>
  <div class="rr-success">
    <div class="rr-success-icon">✅</div>
    <h3>Reclamo enviado</h3>
    <p>Los administradores fueron notificados y revisarán el partido a la brevedad. Te contactarán si necesitan más información.</p>
    <a href="dashboard.php" class="btn btn-navy" style="margin-top:1.5rem">Volver al Inicio</a>
  </div>

  <?php elseif (!$puede_reclamar): ?>
  <div class="rr-blocked">
    <div class="rr-blocked-icon">⚠️</div>
    <h3>No puedes reclamar este resultado</h3>
    <p><?= epl_h($razon_no_puede) ?></p>
    <?php if ($disputa_previa): ?>
    <p style="margin-top:.75rem;font-size:.85rem;color:var(--gray-400)">Reclamo registrado el <?= date('d/m/Y H:i', strtotime($disputa_previa['created_at'])) ?>.</p>
    <?php endif; ?>
    <a href="dashboard.php" class="btn btn-navy" style="margin-top:1.5rem">Volver al Inicio</a>
  </div>

  <?php else: ?>

  <!-- VS Header del partido -->
  <div class="rr-match-card">
    <div class="rr-match-header">
      <div class="rr-team"><?= epl_h($partido['local_nombre']) ?></div>
      <div class="rr-vs-badge">VS</div>
      <div class="rr-team rr-team-right"><?= epl_h($partido['visitante_nombre']) ?></div>
    </div>

    <!-- Resultado actual -->
    <?php
    $sets_display = [];
    if ($partido['games_s1_local'] !== null) $sets_display[] = ['l' => $partido['games_s1_local'], 'v' => $partido['games_s1_visitante']];
    if ($partido['games_s2_local'] !== null) $sets_display[] = ['l' => $partido['games_s2_local'], 'v' => $partido['games_s2_visitante']];
    if ($partido['games_s3_local'] !== null) $sets_display[] = ['l' => $partido['games_s3_local'], 'v' => $partido['games_s3_visitante']];
    ?>
    <div class="rr-resultado-actual">
      <span class="rr-label-mini">Resultado registrado</span>
      <div class="rr-sets">
        <?php foreach ($sets_display as $i => $sv): ?>
        <div class="rr-set-chip">
          <span class="rr-set-num">Set <?= $i+1 ?></span>
          <span class="rr-set-score"><?= $sv['l'] ?>–<?= $sv['v'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ($partido['ganador_nombre']): ?>
      <div class="rr-ganador">🏆 Ganador: <strong><?= epl_h($partido['ganador_nombre']) ?></strong></div>
      <?php endif; ?>
    </div>

    <!-- Countdown -->
    <div class="rr-countdown">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      Tiempo para reclamar: <strong><?= $horas_restantes ?>h <?= $minutos_restantes ?>min</strong>
    </div>
  </div>

  <!-- Formulario de reclamo -->
  <div class="rr-form-card">
    <h3 class="rr-form-title">¿Qué está mal en el resultado?</h3>
    <p class="rr-form-sub">Describe el error con detalle: sets correctos, games, etc. Los admins necesitan esta información para resolverlo.</p>

    <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:1rem"><?= epl_h($error) ?></div>
    <?php endif; ?>

    <form method="post" id="formReclamo">
      <input type="hidden" name="partido_id" value="<?= $partido_id ?>">

      <textarea name="motivo" class="rr-textarea" rows="5"
        placeholder="Ej: El marcador correcto fue 6-4 / 4-6 / 10-7 (super tiebreak). El equipo rival anotó mal el tercer set..."
        required minlength="10"><?= epl_h($_POST['motivo'] ?? '') ?></textarea>

      <button type="submit" class="rr-submit-btn"
        data-confirm="¿Confirmas que quieres enviar este reclamo? Los administradores serán notificados."
        data-confirm-title="Enviar reclamo"
        data-confirm-ok="Sí, reclamar"
        data-confirm-danger="false">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        Enviar Reclamo
      </button>
    </form>

    <p class="rr-aviso">Una vez enviado el reclamo, los administradores serán notificados inmediatamente por la app y por correo electrónico.</p>
  </div>

  <?php endif; ?>

</main>
</div>

<style>
.rr-empty, .rr-success, .rr-blocked {
  text-align: center; padding: 4rem 2rem;
  animation: rr-fade-up .4s ease both;
}
.rr-empty-icon, .rr-success-icon, .rr-blocked-icon { font-size: 3rem; margin-bottom: 1rem; }
.rr-empty h3, .rr-success h3, .rr-blocked h3 {
  font-family: var(--font-head); text-transform: uppercase;
  color: var(--navy); margin: 0 0 .5rem;
}
.rr-empty p, .rr-success p, .rr-blocked p { color: var(--gray-400); max-width: 420px; margin: 0 auto; line-height: 1.6; }
.rr-success { background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius: 20px; border: 1px solid #86efac; }
.rr-blocked { background: linear-gradient(135deg, #fffbeb, #fef3c7); border-radius: 20px; border: 1px solid #fde68a; }
.rr-blocked h3 { color: #92400e; }
.rr-blocked p { color: #78350f; }

/* Match card */
.rr-match-card {
  background: var(--white); border-radius: 20px;
  border: 1px solid var(--gray-100); margin-bottom: 1.5rem;
  box-shadow: 0 4px 24px rgba(0,0,0,.07);
  overflow: hidden;
  animation: rr-fade-up .35s ease both;
}
.rr-match-header {
  display: grid; grid-template-columns: 1fr auto 1fr;
  align-items: center; gap: 1rem;
  background: linear-gradient(135deg, #0f1f38, var(--navy));
  padding: 1.75rem 1.5rem;
}
.rr-team {
  font-family: var(--font-head); font-size: clamp(.8rem, 2vw, 1rem);
  text-transform: uppercase; color: var(--white); line-height: 1.2;
}
.rr-team-right { text-align: right; }
.rr-vs-badge {
  background: linear-gradient(135deg, var(--gold), #b8975a);
  color: var(--navy); font-size: .62rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .12em;
  border-radius: 20px; padding: .4rem .9rem; white-space: nowrap;
  box-shadow: 0 4px 14px rgba(201,167,98,.4);
}

.rr-resultado-actual { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--gray-100); }
.rr-label-mini {
  font-size: .58rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: .12em; color: var(--gray-400); display: block; margin-bottom: .6rem;
}
.rr-sets { display: flex; gap: .5rem; flex-wrap: wrap; }
.rr-set-chip {
  background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
  border-radius: 10px; padding: .4rem .75rem;
  display: flex; align-items: center; gap: .4rem;
}
.rr-set-num { font-size: .65rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase; }
.rr-set-score { font-family: var(--font-head); font-size: 1rem; color: var(--navy); }
.rr-ganador { margin-top: .6rem; font-size: .85rem; font-weight: 600; color: var(--navy); }

.rr-countdown {
  padding: .85rem 1.5rem;
  background: linear-gradient(135deg, #fffbeb, #fef3c7);
  display: flex; align-items: center; gap: .5rem;
  font-size: .82rem; font-weight: 600; color: #92400e;
}
.rr-countdown svg { flex-shrink: 0; stroke: #d97706; }

/* Form card */
.rr-form-card {
  background: var(--white); border-radius: 20px;
  border: 1px solid var(--gray-100); padding: 1.75rem;
  box-shadow: 0 4px 24px rgba(0,0,0,.06);
  animation: rr-fade-up .35s .08s ease both;
}
.rr-form-title {
  font-family: var(--font-head); font-size: 1rem;
  text-transform: uppercase; color: var(--navy);
  margin: 0 0 .4rem; letter-spacing: .04em;
}
.rr-form-sub { font-size: .82rem; color: var(--gray-400); margin: 0 0 1.25rem; line-height: 1.5; }
.rr-textarea {
  width: 100%; border: 2px solid var(--gray-200); border-radius: 14px;
  padding: 1rem 1.1rem; font-family: var(--font-body, 'Montserrat', sans-serif);
  font-size: .88rem; color: var(--navy); resize: vertical; min-height: 120px;
  transition: border-color .2s, box-shadow .2s; box-sizing: border-box;
  background: linear-gradient(135deg, #fafafa, var(--white));
  margin-bottom: 1.25rem;
}
.rr-textarea:focus {
  outline: none; border-color: var(--gold);
  box-shadow: 0 0 0 4px rgba(201,167,98,.15);
}
.rr-submit-btn {
  width: 100%; display: flex; align-items: center; justify-content: center; gap: .6rem;
  background: linear-gradient(135deg, #dc2626, #b91c1c);
  color: var(--white); border: none; border-radius: 14px; padding: 1.1rem;
  font-family: var(--font-head); font-size: .9rem; text-transform: uppercase;
  letter-spacing: .08em; cursor: pointer;
  transition: all .25s cubic-bezier(.4,0,.2,1);
  box-shadow: 0 6px 20px rgba(220,38,38,.25);
}
.rr-submit-btn:hover {
  transform: translateY(-2px); box-shadow: 0 10px 28px rgba(220,38,38,.35);
  filter: brightness(1.08);
}
.rr-aviso {
  margin-top: 1rem; font-size: .75rem; color: var(--gray-400);
  text-align: center; line-height: 1.5;
}

@keyframes rr-fade-up {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}
@media(max-width:480px){
  .rr-match-header { padding: 1.1rem; gap: .5rem; }
  .rr-team { font-size: .78rem; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
