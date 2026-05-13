<?php
$page_title = 'Reprogramar Partido';
$player_tab = 'reprogramar';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
epl_require_login();

$jugador = epl_jugador_actual();
$db      = epl_db();
$liga    = epl_liga_activa();
$equipo  = $liga ? epl_equipo_del_jugador($jugador['id'], $liga['id']) : null;

$ok    = false;
$error = '';

$partidos_pendientes = [];
if ($equipo) {
    $stP = $db->prepare("
        SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        WHERE p.liga_id=? AND (p.equipo_local_id=? OR p.equipo_visitante_id=?)
          AND p.estado IN ('pendiente','reprogramado')
          AND (p.fecha_programada >= DATE_ADD(NOW(), INTERVAL 48 HOUR) OR p.fecha_programada IS NULL)
        ORDER BY p.fecha_programada ASC
    ");
    $stP->execute([$liga['id'], $equipo['id'], $equipo['id']]);
    $partidos_pendientes = $stP->fetchAll();
}

// Mis solicitudes previas (últimas 10)
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
    $partido_id      = (int)($_POST['partido_id']      ?? 0);
    $fecha_propuesta = trim($_POST['fecha_propuesta']  ?? '');
    $motivo          = trim($_POST['motivo']            ?? '');
    $mutuo_acuerdo   = isset($_POST['mutuo_acuerdo'])   ? 1 : 0;
    $rival_no_resp   = isset($_POST['rival_no_responde']) ? 1 : 0;

    $stVal = $db->prepare("
        SELECT p.*, el.jugador1_id AS l1, el.jugador2_id AS l2, ev.jugador1_id AS v1, ev.jugador2_id AS v2,
               el.nombre AS local_nombre, ev.nombre AS visitante_nombre
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        WHERE p.id=? AND (p.equipo_local_id=? OR p.equipo_visitante_id=?) 
          AND p.estado IN ('pendiente','reprogramado')
          AND (p.fecha_programada >= DATE_ADD(NOW(), INTERVAL 48 HOUR) OR p.fecha_programada IS NULL)
    ");
    $stVal->execute([$partido_id, $equipo['id'], $equipo['id']]);
    $partido = $stVal->fetch();

    if (!$partido) {
        $error = 'Partido no válido o fuera de plazo (mínimo 48h).';
    } elseif (!$motivo) {
        $error = 'Debes ingresar el motivo.';
    } elseif (!$rival_no_resp && !$fecha_propuesta) {
        $error = 'Debes proponer una fecha, o marcar que el rival no respondió.';
    } elseif (!$rival_no_resp && !$mutuo_acuerdo) {
        $error = 'Debes confirmar que coordinaste con el equipo rival.';
    } else {
        // Regla "rival no responde": dejamos fecha vacía (NULL)
        $fecha_final = $rival_no_resp ? null : $fecha_propuesta;

        $db->prepare("
            INSERT INTO solicitudes_reprogramacion
              (partido_id, solicitante_id, motivo, fecha_propuesta, rival_no_responde, mutuo_acuerdo)
            VALUES (?,?,?,?,?,?)
        ")->execute([$partido_id, $jugador['id'], $motivo, $fecha_final, $rival_no_resp, $mutuo_acuerdo]);

        $db->prepare("UPDATE partidos SET estado='reprogramado' WHERE id=?")->execute([$partido_id]);

        // --- ENVÍO DE CORREOS ---
        $destinatarios = [];
        
        // 1. Otros 3 jugadores del partido
        $jugadores_ids = array_unique([$partido['l1'], $partido['l2'], $partido['v1'], $partido['v2']]);
        foreach($jugadores_ids as $jid) {
            if ($jid != $jugador['id']) {
                $stJ = $db->prepare("SELECT email FROM jugadores WHERE id=?");
                $stJ->execute([$jid]);
                if ($row = $stJ->fetch()) $destinatarios[] = $row['email'];
            }
        }

        // 2. Administradores
        $stA = $db->query("SELECT email FROM jugadores WHERE rol='admin' AND estado='activo'");
        while ($row = $stA->fetch()) $destinatarios[] = $row['email'];

        $destinatarios = array_unique($destinatarios);
        
        if ($destinatarios) {
            $to = implode(', ', $destinatarios);
            $subject = "Solicitud de Reprogramación: " . $partido['local_nombre'] . " vs " . $partido['visitante_nombre'];
            $cuerpo = "Hola,\n\nSe ha solicitado una reprogramación para el partido:\n";
            $cuerpo .= "Partido: " . $partido['local_nombre'] . " vs " . $partido['visitante_nombre'] . "\n";
            $cuerpo .= "Solicitante: " . $jugador['nombre'] . " " . $jugador['apellido'] . "\n";
            $cuerpo .= "Motivo: " . $motivo . "\n";
            if ($rival_no_resp) {
                $cuerpo .= "Estado: El rival no responde ni coordina.\n";
            } else {
                $cuerpo .= "Nueva fecha propuesta: " . date('d/m/Y H:i', strtotime($fecha_propuesta)) . "\n";
                $cuerpo .= "Acuerdo: Confirmado por mutuo acuerdo.\n";
            }
            $cuerpo .= "\nLa organización revisará la solicitud a la brevedad.\n\nAtentamente,\nElite Padel League";
            
            $headers = "From: Elite Padel League <no-reply@elitepadelleague.com>\r\n";
            $headers .= "Reply-To: no-reply@elitepadelleague.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            @mail($to, $subject, $cuerpo, $headers);
        }

        $ok = true;

        // Recargar listas
        $stP->execute([$liga['id'], $equipo['id'], $equipo['id']]);
        $partidos_pendientes = $stP->fetchAll();
        $stS->execute([$jugador['id']]);
        $mis_solicitudes = $stS->fetchAll();
    }
}
?>
<?php require_once 'includes/header.php'; ?>


<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">Reprogramar Partido</h1>
      <p style="color:var(--gray-600);margin-top:.25rem;font-size:.88rem">Coordina con tu rival antes de solicitar. Mínimo 48h de anticipación.</p>
    </div>

    <?php if ($ok): ?>
      <div class="alert alert-success">Solicitud enviada. El administrador la revisará y confirmará la fecha.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= epl_h($error) ?></div>
    <?php endif; ?>

    <?php if (!$equipo): ?>
      <div class="alert alert-info">No estás inscrito en ningún equipo activo en esta liga.</div>
    <?php elseif (empty($partidos_pendientes)): ?>
      <div class="alert alert-info">No tienes partidos pendientes de reprogramación.</div>
    <?php else: ?>
    <div class="card mb-4">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Solicitar reprogramación</h3>
      </div>
      <div class="card-body">
        <form method="post" id="formReprog">
          <div class="form-group">
            <label class="form-label">Partido *</label>
            <select name="partido_id" class="form-control" required>
              <option value="">— Selecciona un partido —</option>
              <?php 
                $pre_id = (int)($_GET['partido_id'] ?? 0);
                foreach ($partidos_pendientes as $p): 
                  $sel = ($p['id'] == $pre_id) ? 'selected' : '';
              ?>
                <option value="<?= $p['id'] ?>" <?= $sel ?>>
                  <?= epl_h($p['local_nombre'].' vs '.$p['visitante_nombre']) ?>
                  <?= $p['fecha_programada'] ? ' — '.date('d/m/Y', strtotime($p['fecha_programada'])) : ' — Sin fecha' ?>
                  <?= $p['estado']==='reprogramado' ? ' (reprogramado)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Motivo *</label>
            <textarea name="motivo" class="form-control" rows="3" required placeholder="Explica brevemente el motivo del cambio..."></textarea>
          </div>

          <!-- Checkbox rival no responde -->
          <div style="background:var(--gray-100);border-radius:8px;padding:1rem;margin-bottom:1rem">
            <label style="display:flex;align-items:flex-start;gap:.75rem;cursor:pointer">
              <input type="checkbox" name="rival_no_responde" id="chkRivalNoResp" onchange="toggleRivalNoResp(this)" style="margin-top:.15rem;width:16px;height:16px;flex-shrink:0">
              <div>
                <span style="font-weight:700;font-size:.88rem;color:var(--navy)">El rival no responde ni coordina</span>
                <p style="font-size:.78rem;color:var(--gray-600);margin-top:.2rem">Si marcas esto, el partido quedará sin fecha definida y la organización tomará acción para asignar una fecha obligatoria o declarar WO.</p>
              </div>
            </label>
          </div>

          <!-- Sección fecha (se oculta si rival no responde) -->
          <div id="seccionFecha">
            <div class="form-group">
              <label class="form-label">Fecha y hora propuesta *</label>
              <input type="datetime-local" name="fecha_propuesta" class="form-control"
                     min="<?= date('Y-m-d\TH:i', strtotime('+48 hours')) ?>">
              <span class="form-hint">Mínimo 48 horas desde ahora.</span>
            </div>

            <div style="background:#DBEAFE;border-radius:8px;padding:1rem;margin-bottom:1rem">
              <label style="display:flex;align-items:flex-start;gap:.75rem;cursor:pointer">
                <input type="checkbox" name="mutuo_acuerdo" id="chkMutuo" style="margin-top:.15rem;width:16px;height:16px;flex-shrink:0" required>
                <div>
                  <span style="font-weight:700;font-size:.88rem;color:#1E40AF">Confirmo mutuo acuerdo</span>
                  <p style="font-size:.78rem;color:#3B82F6;margin-top:.2rem">He coordinado esta nueva fecha con el equipo rival y ambos estamos de acuerdo.</p>
                </div>
              </label>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Enviar solicitud</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Historial de solicitudes -->
    <?php if ($mis_solicitudes): ?>
    <div class="card">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Mis solicitudes</h3>
      </div>
      <div class="card-body" style="padding:0">
        <?php foreach ($mis_solicitudes as $s):
          $badgeCls = match($s['estado']) {
            'aprobada'  => 'badge-jugado',
            'rechazada' => 'badge-walkover',
            default     => 'badge-pendiente'
          };
        ?>
        <div style="padding:.9rem 1.5rem;border-bottom:1px solid var(--gray-100)">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
            <div style="flex:1;min-width:0">
              <div style="font-weight:700;font-size:.88rem;color:var(--navy)"><?= epl_h($s['local_nombre'].' vs '.$s['visitante_nombre']) ?></div>
              <div style="font-size:.75rem;color:var(--gray-400);margin-top:.15rem">
                Fecha propuesta: <?= $s['fecha_propuesta'] ? date('d/m/Y H:i', strtotime($s['fecha_propuesta'])) : '—' ?>
                <?php if ($s['rival_no_responde']): ?>
                  <span class="badge badge-walkover" style="font-size:.62rem;margin-left:.4rem">Rival no respondió</span>
                <?php elseif ($s['mutuo_acuerdo']): ?>
                  <span class="badge badge-jugado" style="font-size:.62rem;margin-left:.4rem">Mutuo acuerdo</span>
                <?php endif; ?>
              </div>
              <?php if ($s['fecha_aprobada'] && $s['estado']==='aprobada'): ?>
                <div style="font-size:.75rem;color:#22c55e;margin-top:.15rem;font-weight:600">
                  ✓ Confirmada: <?= date('d/m/Y H:i', strtotime($s['fecha_aprobada'])) ?>
                  <?= $s['cancha_aprobada'] ? '· '.$s['cancha_aprobada'] : '' ?>
                </div>
              <?php endif; ?>
              <div style="font-size:.73rem;color:var(--gray-600);margin-top:.15rem;font-style:italic"><?= epl_h(mb_strimwidth($s['motivo'], 0, 80, '...')) ?></div>
            </div>
            <span class="badge <?= $badgeCls ?>" style="white-space:nowrap;flex-shrink:0"><?= ucfirst($s['estado']) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

</main>
</div>

<script>
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
</script>

<?php require_once 'includes/footer.php'; ?>
