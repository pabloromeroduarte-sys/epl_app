<?php
$page_title = 'Reprogramar Partido';
$player_tab = 'reprogramar';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/mail.php';
epl_require_login();

$jugador = epl_jugador_actual();
$db      = epl_db();
$liga    = epl_liga_activa();
$equipo  = $liga ? epl_equipo_del_jugador($jugador['id'], $liga['id']) : null;

$_flash = epl_flash_get();
$ok     = ($_flash && $_flash['tipo']==='ok');
$error  = ($_flash && $_flash['tipo']==='error') ? $_flash['msg'] : '';

$partidos_pendientes = [];
if ($equipo) {
    $stP = $db->prepare("
        SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
               jl1.nombre AS l1n, jl1.apellido AS l1a, jl1.telefono AS l1t,
               jl2.nombre AS l2n, jl2.apellido AS l2a, jl2.telefono AS l2t,
               jv1.nombre AS v1n, jv1.apellido AS jv1a, jv1.telefono AS v1t,
               jv2.nombre AS v2n, jv2.apellido AS v2a, jv2.telefono AS v2t
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN jugadores jl1 ON jl1.id = el.jugador1_id
        LEFT JOIN jugadores jl2 ON jl2.id = el.jugador2_id
        LEFT JOIN jugadores jv1 ON jv1.id = ev.jugador1_id
        LEFT JOIN jugadores jv2 ON jv2.id = ev.jugador2_id
        WHERE p.liga_id=? AND (p.equipo_local_id=? OR p.equipo_visitante_id=?)
          AND p.estado IN ('pendiente','reprogramado')
          AND (
            p.fecha_programada IS NULL
            OR p.fecha_programada < NOW()
            OR p.fecha_programada >= DATE_ADD(NOW(), INTERVAL 48 HOUR)
          )
        ORDER BY p.fecha_programada ASC
    ");
    $stP->execute([$liga['id'], $equipo['id'], $equipo['id']]);
    $partidos_pendientes = $stP->fetchAll();
}

$mis_solicitudes = [];
if ($equipo) {
    $stS = $db->prepare("
        SELECT sr.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre
        FROM solicitudes_reprogramacion sr
        JOIN partidos p  ON p.id = sr.partido_id
        JOIN equipos el  ON el.id = p.equipo_local_id
        JOIN equipos ev  ON ev.id = p.equipo_visitante_id
        WHERE sr.solicitante_id=?
        ORDER BY sr.created_at DESC LIMIT 10
    ");
    $stS->execute([$jugador['id']]);
    $mis_solicitudes = $stS->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $equipo) {
    $partido_id      = (int)($_POST['partido_id']       ?? 0);
    $fecha_propuesta = trim($_POST['fecha_propuesta']   ?? '');
    $motivo          = trim($_POST['motivo']             ?? '');
    $mutuo_acuerdo   = isset($_POST['mutuo_acuerdo'])    ? 1 : 0;
    $rival_no_resp   = isset($_POST['rival_no_responde']) ? 1 : 0;

    $stVal = $db->prepare("
        SELECT p.*, el.jugador1_id AS l1, el.jugador2_id AS l2, ev.jugador1_id AS v1, ev.jugador2_id AS v2,
               el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
               r.nombre AS recinto_nombre, rs.nombre AS recinto_superior, rss.nombre AS recinto_raiz
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN recintos r   ON r.id   = p.recinto_id
        LEFT JOIN recintos rs  ON rs.id  = r.superior_id
        LEFT JOIN recintos rss ON rss.id = rs.superior_id
        WHERE p.id=? AND (p.equipo_local_id=? OR p.equipo_visitante_id=?)
          AND p.estado IN ('pendiente','reprogramado')
          AND (
            p.fecha_programada IS NULL
            OR p.fecha_programada < NOW()
            OR p.fecha_programada >= DATE_ADD(NOW(), INTERVAL 48 HOUR)
          )
    ");
    $stVal->execute([$partido_id, $equipo['id'], $equipo['id']]);
    $partido = $stVal->fetch();

    if (!$partido) {
        $error = 'Partido no válido o no autorizado.';
    } elseif (!$motivo) {
        $error = 'Debes ingresar el motivo.';
    } elseif (!$rival_no_resp && !$fecha_propuesta) {
        $error = 'Debes proponer una fecha, o marcar que el rival no respondió.';
    } elseif (!$rival_no_resp && !$mutuo_acuerdo) {
        $error = 'Debes confirmar que coordinaste con el equipo rival.';
    } else {
        $fecha_final = $rival_no_resp ? null : $fecha_propuesta;

        $db->prepare("
            INSERT INTO solicitudes_reprogramacion
              (partido_id, solicitante_id, motivo, fecha_propuesta, rival_no_responde, mutuo_acuerdo)
            VALUES (?,?,?,?,?,?)
        ")->execute([$partido_id, $jugador['id'], $motivo, $fecha_final, $rival_no_resp, $mutuo_acuerdo]);

        $db->prepare("UPDATE partidos SET estado='reprogramado' WHERE id=?")->execute([$partido_id]);

        // Notificar in-app + email (SMTP) a jugadores del partido rival y a los admins
        $solicitante_nombre = $jugador['nombre'] . ' ' . $jugador['apellido'];
        $partido_str = $partido['local_nombre'] . ' vs ' . $partido['visitante_nombre'];
        $fecha_str   = $fecha_final ? date('d/m/Y H:i', strtotime($fecha_final)) : 'a coordinar';

        // Recinto: construir ruta completa (raíz › sede › cancha)
        $recinto_partes = array_filter([
            $partido['recinto_raiz']     ?? null,
            $partido['recinto_superior'] ?? null,
            $partido['recinto_nombre']   ?? null,
        ]);
        $recinto_txt = $recinto_partes ? implode(' › ', $recinto_partes) : 'Sin recinto asignado';

        // Jornada / fecha del torneo
        $jornada_txt = '';
        if (!empty($partido['jornada']))      $jornada_txt .= 'Jornada ' . $partido['jornada'];
        if (!empty($partido['nombre_fecha'])) $jornada_txt .= ($jornada_txt ? ' — ' : '') . $partido['nombre_fecha'];

        // Fecha original del partido
        $fecha_original_txt = $partido['fecha_programada']
            ? date('d/m/Y H:i', strtotime($partido['fecha_programada']))
            : 'Sin fecha asignada';

        $lineas = [
            "Partido: {$partido_str}",
            "Liga: {$liga['nombre']}",
        ];
        if ($jornada_txt)  $lineas[] = "Jornada: {$jornada_txt}";
        $lineas[] = "Fecha original: {$fecha_original_txt}";
        $lineas[] = "Recinto: {$recinto_txt}";
        $lineas[] = "Fecha propuesta: {$fecha_str}";
        if ($motivo)       $lineas[] = "Motivo: {$motivo}";

        $mensaje_notif = "{$solicitante_nombre} solicitó reprogramar el partido.\n\n" . implode("\n", $lineas);

        epl_notif_partido(
            $partido_id,
            'reprogramacion',
            "📅 Solicitud de reprogramación: {$partido_str}",
            $mensaje_notif,
            epl_url('mis_torneos.php'),
            true,                       // incluir admins
            [(int)$jugador['id']],      // excluir al solicitante
            true                        // skip_email: enviamos visual por separado
        );

        // ── Email visual a jugadores del partido (excluye al solicitante) ──
        $filas_repr = array_values(array_filter([
            ['icon' => '🏆', 'label' => 'Liga',             'valor' => $liga['nombre']],
            $jornada_txt ? ['icon' => '🗓️',  'label' => 'Jornada',         'valor' => $jornada_txt]       : null,
            ['icon' => '📅', 'label' => 'Fecha original',   'valor' => $fecha_original_txt],
            ['icon' => '🕐', 'label' => 'Fecha propuesta',  'valor' => $fecha_str],
            ['icon' => '🏟️', 'label' => 'Recinto',          'valor' => $recinto_txt],
            $motivo ? ['icon' => '💬', 'label' => 'Motivo', 'valor' => $motivo] : null,
        ]));
        $asunto_repr = "📅 Solicitud de reprogramación: {$partido_str}";

        $st_jug_repr = $db->prepare("
            SELECT DISTINCT j.id
            FROM partidos p
            JOIN equipos el ON el.id = p.equipo_local_id
            JOIN equipos ev ON ev.id = p.equipo_visitante_id
            JOIN jugadores j ON j.id IN (el.jugador1_id, el.jugador2_id, ev.jugador1_id, ev.jugador2_id)
            WHERE p.id = ? AND j.id != ?
        ");
        $st_jug_repr->execute([$partido_id, (int)$jugador['id']]);
        // También admins
        $admins_repr = $db->query("SELECT id FROM jugadores WHERE rol='admin' AND estado='activo'")->fetchAll();
        $ids_repr    = array_unique(array_merge(
            array_column($st_jug_repr->fetchAll(), 'id'),
            array_filter(array_column($admins_repr, 'id'), fn($aid) => (int)$aid !== (int)$jugador['id'])
        ));
        foreach ($ids_repr as $jid_r) {
            epl_mail_partido_visual(
                (int)$jid_r,
                $asunto_repr,
                $partido['local_nombre'],
                $partido['visitante_nombre'],
                $filas_repr,
                "{$solicitante_nombre} solicitó reprogramar el partido.",
                '⏳ El administrador revisará la solicitud y confirmará la nueva fecha.',
                epl_url('mis_torneos.php')
            );
        }

        epl_redirect_ok('solicitud_ok');
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main">

  <div class="dash-header">
    <h1 class="dash-title">Reprogramar Partido</h1>
    <p style="color:var(--gray-400);font-size:.88rem">Coordina con tu rival y propone una nueva fecha.</p>
  </div>

  <?php if ($ok): ?>
  <div class="rp-success">
    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <div><strong>Solicitud enviada.</strong> El administrador la revisará y confirmará la nueva fecha.</div>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-error"><?= epl_h($error) ?></div>
  <?php endif; ?>

  <?php if (!$equipo): ?>
    <div class="alert alert-info">No estás inscrito en ningún equipo activo en esta liga.</div>
  <?php elseif (empty($partidos_pendientes)): ?>
  <div class="rp-empty">
    <div style="font-size:2.5rem;margin-bottom:.75rem">📅</div>
    <h3>Sin partidos para reprogramar</h3>
    <p>No tienes partidos pendientes o todos tienen fecha con más de 48h de anticipación.</p>
  </div>
  <?php else: ?>

  <form method="post" id="formReprog">

    <!-- Paso 1: Seleccionar partido -->
    <div class="rp-card">
      <div class="rp-card-header">
        <span class="rp-step-dot">1</span>
        <div>
          <div class="rp-card-title">Selecciona el partido</div>
          <div class="rp-card-sub">Partidos pendientes o con fecha vencida.</div>
        </div>
      </div>
      <input type="hidden" name="partido_id" id="hiddenPartidoIdRp">
      <div class="rp-partidos-list" style="margin-top:1rem" id="rpListaPartidos">
        <?php
          $pre_id = (int)($_GET['partido_id'] ?? 0);
          foreach ($partidos_pendientes as $p):
            $esLocal = ($p['equipo_local_id'] == $equipo['id']);
            $rivales = [];
            if ($esLocal) {
              $rivales[] = ['n'=>$p['v1n'],  'a'=>$p['jv1a'], 't'=>$p['v1t']];
              $rivales[] = ['n'=>$p['v2n'],  'a'=>$p['v2a'],  't'=>$p['v2t']];
            } else {
              $rivales[] = ['n'=>$p['l1n'],  'a'=>$p['l1a'],  't'=>$p['l1t']];
              $rivales[] = ['n'=>$p['l2n'],  'a'=>$p['l2a'],  't'=>$p['l2t']];
            }
            $dataRivales = htmlspecialchars(base64_encode(json_encode($rivales)), ENT_QUOTES);
            $checked = ($p['id'] == $pre_id) ? 'checked' : '';
        ?>
        <label class="rp-partido-option" for="rpp<?= $p['id'] ?>">
          <input type="radio" name="_rp_partido_pick" id="rpp<?= $p['id'] ?>" value="<?= $p['id'] ?>"
                 data-rivales="<?= $dataRivales ?>" <?= $checked ?>
                 onchange="rpSelPartido(this)">
          <div class="rp-partido-content">
            <div class="rp-partido-fecha">
              <?php if ($p['fecha_programada']): ?>
                <span class="rp-dia"><?= date('d', strtotime($p['fecha_programada'])) ?></span>
                <span class="rp-mes"><?= strtoupper(date('M', strtotime($p['fecha_programada']))) ?></span>
              <?php else: ?>
                <span class="rp-dia">?</span><span class="rp-mes">TBD</span>
              <?php endif; ?>
            </div>
            <div class="rp-partido-teams">
              <span><?= epl_h($p['local_nombre']) ?></span>
              <span class="rp-vs">VS</span>
              <span><?= epl_h($p['visitante_nombre']) ?></span>
            </div>
            <div class="rp-partido-meta">
              <span class="rp-jornada">F.<?= $p['jornada'] ?? '?' ?></span>
              <?php if ($p['estado']==='reprogramado'): ?>
                <span class="badge badge-walkover" style="font-size:.55rem">Reprog.</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="rp-partido-check">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Paso 2: Contactar rivales -->
    <div id="seccionRivales" class="rp-card rp-card-rivales" style="display:none">
      <div class="rp-card-header">
        <span class="rp-step-dot" style="background:#0369A1">2</span>
        <div>
          <div class="rp-card-title" style="color:#0369A1">Contacta a tus rivales</div>
          <div class="rp-card-sub">Coordina la nueva fecha antes de enviar la solicitud.</div>
        </div>
      </div>
      <div id="listaRivales" style="display:flex;flex-direction:column;gap:.6rem;margin-top:1rem"></div>
    </div>

    <!-- Paso 3: Motivo -->
    <div class="rp-card">
      <div class="rp-card-header">
        <span class="rp-step-dot">3</span>
        <div>
          <div class="rp-card-title">Motivo de la reprogramación</div>
        </div>
      </div>
      <textarea name="motivo" class="form-control" rows="3" required
                placeholder="Explica brevemente el motivo del cambio..."
                style="margin-top:1rem;resize:vertical"></textarea>
    </div>

    <!-- Paso 4: Rival no responde -->
    <div class="rp-card">
      <div class="rp-card-header">
        <span class="rp-step-dot">4</span>
        <div>
          <div class="rp-card-title">¿El rival no responde?</div>
        </div>
      </div>
      <label class="rp-toggle-row" style="margin-top:1rem">
        <div class="rp-toggle-info">
          <div style="font-weight:700;font-size:.9rem;color:var(--navy)">El rival no responde ni coordina</div>
          <div style="font-size:.78rem;color:var(--gray-500);margin-top:.2rem">El partido quedará sin fecha y la organización tomará acción.</div>
        </div>
        <label class="rp-switch">
          <input type="checkbox" name="rival_no_responde" id="chkRivalNoResp" onchange="toggleRivalNoResp(this)">
          <span class="rp-slider"></span>
        </label>
      </label>
    </div>

    <!-- Paso 5: Nueva fecha + mutuo acuerdo -->
    <div id="seccionFecha" class="rp-card rp-card-fecha">
      <div class="rp-card-header">
        <span class="rp-step-dot" style="background:#1d4ed8">5</span>
        <div>
          <div class="rp-card-title" style="color:#1d4ed8">Nueva fecha propuesta</div>
          <div class="rp-card-sub">Mínimo 48 horas desde ahora.</div>
        </div>
      </div>
      <input type="datetime-local" name="fecha_propuesta" class="form-control"
             min="<?= date('Y-m-d\TH:i', strtotime('+48 hours')) ?>"
             style="margin-top:1rem;max-width:280px">

      <label class="rp-acuerdo-row">
        <input type="checkbox" name="mutuo_acuerdo" id="chkMutuo" required>
        <div>
          <div style="font-weight:700;font-size:.88rem;color:#1e40af">Confirmo mutuo acuerdo</div>
          <div style="font-size:.78rem;color:#3b82f6;margin-top:.1rem">He coordinado esta fecha con el equipo rival y ambos estamos de acuerdo.</div>
        </div>
      </label>
    </div>

    <button type="submit" class="rp-submit-btn">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
      Enviar solicitud
    </button>

  </form>
  <?php endif; ?>

  <!-- Historial de solicitudes -->
  <?php if ($mis_solicitudes): ?>
  <div style="margin-top:2.5rem">
    <h3 style="font-family:var(--font-head);font-size:.85rem;text-transform:uppercase;letter-spacing:.08em;color:var(--navy);margin-bottom:1rem">Mis solicitudes anteriores</h3>
    <div style="display:flex;flex-direction:column;gap:.6rem">
      <?php foreach ($mis_solicitudes as $s):
        $badgeCls = match($s['estado']) { 'aprobada'=>'badge-jugado', 'rechazada'=>'badge-walkover', default=>'badge-pendiente' };
      ?>
      <div class="rp-historial-row">
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;font-size:.88rem;color:var(--navy)"><?= epl_h($s['local_nombre'].' vs '.$s['visitante_nombre']) ?></div>
          <div style="font-size:.75rem;color:var(--gray-400);margin-top:.2rem">
            <?= $s['fecha_propuesta'] ? date('d/m/Y H:i', strtotime($s['fecha_propuesta'])) : 'Sin fecha' ?>
            <?php if ($s['rival_no_responde']): ?><span class="badge badge-walkover" style="font-size:.6rem;margin-left:.4rem">Rival no respondió</span><?php endif; ?>
            <?php if ($s['mutuo_acuerdo']): ?><span class="badge badge-jugado" style="font-size:.6rem;margin-left:.4rem">Mutuo acuerdo</span><?php endif; ?>
          </div>
          <?php if ($s['fecha_aprobada'] && $s['estado']==='aprobada'): ?>
            <div style="font-size:.75rem;color:#22c55e;margin-top:.2rem;font-weight:600">✓ <?= date('d/m/Y H:i', strtotime($s['fecha_aprobada'])) ?> <?= $s['cancha_aprobada']?'· '.$s['cancha_aprobada']:'' ?></div>
          <?php endif; ?>
          <div style="font-size:.72rem;color:var(--gray-500);margin-top:.2rem;font-style:italic"><?= epl_h(mb_strimwidth($s['motivo'], 0, 80, '...')) ?></div>
        </div>
        <span class="badge <?= $badgeCls ?>" style="flex-shrink:0;align-self:flex-start"><?= ucfirst($s['estado']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</main>
</div>

<style>
@keyframes rp-fade-up {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes rp-pop {
  0%   { transform: scale(.9); opacity: 0; }
  60%  { transform: scale(1.05); }
  100% { transform: scale(1); opacity: 1; }
}

.rp-success {
  display: flex; align-items: flex-start; gap: 1rem;
  background: linear-gradient(135deg, #f0fdf4, #dcfce7);
  border: 1px solid #86efac; border-radius: 14px;
  padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; color: #166534;
  box-shadow: 0 4px 16px rgba(34,197,94,.12);
  animation: rp-fade-up .35s ease both;
}
.rp-empty { text-align: center; padding: 3rem 1rem; color: var(--gray-400); animation: rp-fade-up .4s ease both; }
.rp-empty h3 { font-family: var(--font-head); text-transform: uppercase; color: var(--navy); margin: 0 0 .5rem; }

/* Stepper layout */
.rp-stepper { position: relative; }
.rp-card {
  background: var(--white); border-radius: 20px;
  border: 1px solid var(--gray-100); padding: 1.5rem; margin-bottom: 1rem;
  box-shadow: 0 4px 20px rgba(0,0,0,.04);
  transition: box-shadow .2s;
  animation: rp-fade-up .35s ease both;
}
.rp-card:hover { box-shadow: 0 6px 28px rgba(0,0,0,.08); }
.rp-card-rivales { background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border-color: #bae6fd; }
.rp-card-fecha   { background: linear-gradient(135deg, #eff6ff, #dbeafe); border-color: #bfdbfe; }
.rp-card-header  { display: flex; align-items: flex-start; gap: 1rem; }
.rp-step-dot {
  width: 36px; height: 36px; border-radius: 50%;
  background: linear-gradient(135deg, var(--navy), #1a3a64);
  color: var(--white); font-weight: 800; font-size: .85rem;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(28,47,72,.25);
}
.rp-card-title { font-weight: 800; font-size: .95rem; color: var(--navy); }
.rp-card-sub   { font-size: .78rem; color: var(--gray-500); margin-top: .2rem; }

/* Partido radio cards */
.rp-partidos-list {
  display: flex; flex-direction: column; gap: .5rem;
  max-height: 280px; overflow-y: auto;
  padding-right: .25rem;
  scrollbar-width: thin; scrollbar-color: var(--gray-200) transparent;
}
.rp-partidos-list::-webkit-scrollbar { width: 5px; }
.rp-partidos-list::-webkit-scrollbar-track { background: transparent; }
.rp-partidos-list::-webkit-scrollbar-thumb { background: var(--gray-200); border-radius: 4px; }
.rp-partido-option {
  display: flex; align-items: center; gap: .85rem;
  border: 1.5px solid var(--gray-100); border-radius: 14px;
  padding: .85rem 1rem; cursor: pointer;
  transition: all .22s cubic-bezier(.4,0,.2,1); background: var(--white);
}
.rp-partido-option:hover {
  border-color: var(--gold); background: rgba(201,167,98,.04);
  transform: translateX(2px); box-shadow: 0 2px 10px rgba(201,167,98,.12);
}
.rp-partido-option input[type=radio] { display: none; }
.rp-partido-option:has(input:checked) {
  border-color: var(--navy); background: linear-gradient(135deg, rgba(28,47,72,.04), rgba(28,47,72,.07));
  box-shadow: 0 4px 14px rgba(28,47,72,.1);
}
.rp-partido-content { display: flex; align-items: center; gap: .85rem; flex: 1; min-width: 0; }
.rp-partido-fecha {
  background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
  border-radius: 10px; padding: .4rem .6rem; text-align: center; min-width: 42px; flex-shrink: 0;
  transition: all .22s;
}
.rp-dia { display: block; font-family: var(--font-head); font-size: 1.1rem; color: var(--navy); line-height: 1; }
.rp-mes { display: block; font-size: .5rem; font-weight: 800; color: var(--gray-400); letter-spacing: .08em; text-transform: uppercase; }
.rp-partido-option:has(input:checked) .rp-partido-fecha { background: linear-gradient(135deg, var(--navy), #1e3a5f); }
.rp-partido-option:has(input:checked) .rp-dia,
.rp-partido-option:has(input:checked) .rp-mes { color: var(--gold); }
.rp-partido-teams { flex: 1; display: flex; align-items: center; gap: .55rem; font-size: .82rem; font-weight: 700; color: var(--navy); min-width: 0; flex-wrap: wrap; }
.rp-vs { background: linear-gradient(135deg, var(--navy), #2d5a8e); color: var(--gold); border-radius: 6px; padding: .12rem .4rem; font-size: .6rem; font-weight: 800; flex-shrink: 0; }
.rp-partido-option:has(input:checked) .rp-vs { background: linear-gradient(135deg, var(--gold), #b8975a); color: var(--navy); }
.rp-partido-meta { display: flex; flex-direction: column; align-items: flex-end; gap: .25rem; flex-shrink: 0; }
.rp-jornada { font-size: .62rem; font-weight: 700; color: var(--gray-400); }
.rp-partido-check {
  width: 28px; height: 28px; border-radius: 50%; border: 2px solid var(--gray-200);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  color: transparent; transition: all .22s;
}
.rp-partido-option:has(input:checked) .rp-partido-check {
  background: linear-gradient(135deg, var(--navy), #1e3a5f);
  border-color: var(--navy); color: var(--white);
  box-shadow: 0 3px 10px rgba(28,47,72,.3);
  animation: rp-pop .3s ease both;
}

/* Toggle switch */
.rp-toggle-row { display: flex; align-items: center; gap: 1rem; cursor: pointer; padding: .5rem 0; }
.rp-toggle-info { flex: 1; }
.rp-switch { position: relative; display: inline-block; width: 52px; height: 28px; flex-shrink: 0; }
.rp-switch input { opacity: 0; width: 0; height: 0; }
.rp-slider {
  position: absolute; cursor: pointer; inset: 0;
  background: var(--gray-200); border-radius: 28px; transition: .3s;
  box-shadow: inset 0 2px 4px rgba(0,0,0,.1);
}
.rp-slider:before {
  position: absolute; content: ""; height: 22px; width: 22px;
  left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .3s;
  box-shadow: 0 2px 6px rgba(0,0,0,.18);
}
.rp-switch input:checked + .rp-slider { background: linear-gradient(135deg, #ef4444, #dc2626); }
.rp-switch input:checked + .rp-slider:before { transform: translateX(24px); }

/* Mutuo acuerdo */
.rp-acuerdo-row {
  display: flex; align-items: flex-start; gap: .85rem; margin-top: 1rem; cursor: pointer;
  background: linear-gradient(135deg, #dbeafe, #bfdbfe);
  border-radius: 14px; padding: 1rem; border: 1px solid #bfdbfe;
}
.rp-acuerdo-row input[type=checkbox] { width: 18px; height: 18px; margin-top: .15rem; flex-shrink: 0; accent-color: #1d4ed8; }

.rp-submit-btn {
  width: 100%; display: flex; align-items: center; justify-content: center; gap: .7rem;
  background: linear-gradient(135deg, var(--navy), #1a3a64);
  color: var(--white); border: none; border-radius: 16px; padding: 1.2rem;
  font-family: var(--font-head); font-size: 1rem; text-transform: uppercase; letter-spacing: .08em;
  cursor: pointer; transition: all .25s cubic-bezier(.4,0,.2,1); margin-bottom: 1rem;
  box-shadow: 0 6px 20px rgba(28,47,72,.25);
}
.rp-submit-btn:hover { background: linear-gradient(135deg, var(--gold), #b8975a); color: var(--navy); transform: translateY(-2px); box-shadow: 0 10px 28px rgba(201,167,98,.4); }
.rp-submit-btn:active { transform: translateY(0); }

.rp-historial-row {
  background: var(--white); border: 1px solid var(--gray-100);
  border-radius: 14px; padding: 1rem 1.25rem;
  display: flex; align-items: flex-start; gap: 1rem;
  transition: box-shadow .2s;
}
.rp-historial-row:hover { box-shadow: 0 4px 16px rgba(0,0,0,.07); }

/* Rival cards creadas en JS */
.rp-rival-card {
  display: flex; align-items: center; justify-content: space-between;
  background: white; border: 1px solid #bae6fd; border-radius: 12px;
  padding: .9rem 1rem; gap: .75rem;
  transition: box-shadow .18s;
}
.rp-rival-card:hover { box-shadow: 0 3px 12px rgba(3,105,161,.1); }
.rp-rival-name { font-weight: 700; color: var(--navy); font-size: .9rem; }
.rp-rival-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: linear-gradient(135deg, var(--navy), #1a3a64);
  color: var(--white); font-weight: 800; font-size: .8rem;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
</style>

<script>
const WA_SVG = `<svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.588-5.946 0-6.556 5.332-11.888 11.888-11.888 3.176 0 6.161 1.237 8.404 3.48s3.481 5.229 3.481 8.404c0 6.556-5.332 11.888-11.888 11.888-2.003 0-3.963-.505-5.698-1.465l-6.305 1.693zm6.443-4.045c1.474.873 3.103 1.332 4.775 1.332 5.054 0 9.163-4.109 9.163-9.163s-4.109-9.163-9.163-9.163-9.163 4.109-9.163 9.163c0 1.95.623 3.856 1.799 5.437l-1.002 3.659 3.743-.999zm10.742-5.466c-.303-.151-1.788-.882-2.067-.981-.278-.099-.481-.151-.683.151-.202.303-.783.981-.96 1.183-.177.202-.354.227-.657.076-.303-.151-1.28-.471-2.438-1.504-.901-.803-1.508-1.796-1.685-2.098-.177-.302-.019-.465.132-.615.136-.135.303-.354.455-.53.151-.177.202-.303.303-.505.101-.202.051-.379-.025-.53-.076-.151-.683-1.643-.935-2.249-.245-.59-.495-.51-.683-.52l-.582-.01c-.202 0-.531.076-.809.379-.278.303-1.062 1.037-1.062 2.529 0 1.492 1.087 2.932 1.239 3.134.151.202 2.14 3.268 5.184 4.582.724.312 1.29.499 1.731.639.727.231 1.388.199 1.911.121.582-.087 1.788-.731 2.041-1.439.253-.708.253-1.313.177-1.439-.076-.126-.278-.202-.581-.353z"/></svg>`;

function toggleRivalNoResp(chk) {
  const sec = document.getElementById('seccionFecha');
  const chkMutuo = document.getElementById('chkMutuo');
  if (chk.checked) {
    sec.style.display = 'none';
    chkMutuo.removeAttribute('required');
  } else {
    sec.style.display = 'block';
    chkMutuo.setAttribute('required', '');
  }
}

function rpSelPartido(radio) {
  document.getElementById('hiddenPartidoIdRp').value = radio.value;
  cargarRivales(radio.dataset.rivales);
}

function cargarRivales(b64) {
  const wrapper = document.getElementById('seccionRivales');
  const lista   = document.getElementById('listaRivales');
  if (!b64) { wrapper.style.display = 'none'; return; }

  const rivales = JSON.parse(atob(b64));
  lista.innerHTML = '';
  let alguno = false;

  rivales.forEach(r => {
    if (!r.n) return;
    alguno = true;
    const initials = ((r.n||'').charAt(0) + (r.a||'').charAt(0)).toUpperCase();
    const tel   = (r.t || '').replace(/\D/g,'');
    const clean = tel.startsWith('56') ? tel : '56' + tel;
    const msg   = encodeURIComponent('Hola ' + r.n + ', te contacto por el partido de la Elite Padel League. ¿Podemos coordinar la reprogramación?');
    const wsp   = tel ? `https://wa.me/${clean}?text=${msg}` : null;

    const div = document.createElement('div');
    div.className = 'rp-rival-card';
    div.innerHTML =
      `<div class="rp-rival-avatar">${initials}</div>
       <div style="flex:1"><span class="rp-rival-name">${r.n} ${r.a||''}</span></div>` +
      (wsp
        ? `<a href="${wsp}" target="_blank" class="btn btn-sm" style="background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;border:none;gap:.4rem;font-size:.72rem;box-shadow:0 3px 10px rgba(22,163,74,.3)">${WA_SVG} WhatsApp</a>`
        : `<span style="font-size:.75rem;color:var(--gray-400);font-style:italic">Sin teléfono</span>`);
    lista.appendChild(div);
  });

  wrapper.style.display = alguno ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', () => {
  const checked = document.querySelector('input[name="_rp_partido_pick"]:checked');
  if (checked) {
    document.getElementById('hiddenPartidoIdRp').value = checked.value;
    cargarRivales(checked.dataset.rivales);
    // Scroll suave al partido pre-seleccionado
    setTimeout(() => {
      checked.closest('.rp-partido-option')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 200);
  }
});
</script>

<?php require_once 'includes/footer.php'; ?>
