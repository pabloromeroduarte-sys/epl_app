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
            <select name="partido_id" class="form-control" required onchange="actualizarRivales(this)">
              <option value="">— Selecciona un partido —</option>
              <?php 
                $pre_id = (int)($_GET['partido_id'] ?? 0);
                foreach ($partidos_pendientes as $p): 
                  $sel = ($p['id'] == $pre_id) ? 'selected' : '';
                  // Codificar rivales
                  $esLocal = ($p['equipo_local_id'] == $equipo['id']);
                  $rivales = [];
                  if ($esLocal) {
                    $rivales[] = ['n'=>$p['v1n'], 'a'=>$p['jv1a'], 't'=>$p['v1t']];
                    $rivales[] = ['n'=>$p['v2n'], 'a'=>$p['jv2a'], 't'=>$p['v2t']];
                  } else {
                    $rivales[] = ['n'=>$p['l1n'], 'a'=>$p['l1a'], 't'=>$p['l1t']];
                    $rivales[] = ['n'=>$p['l2n'], 'a'=>$p['l2a'], 't'=>$p['l2t']];
                  }
                  $dataRivales = base64_encode(json_encode($rivales));
              ?>
                <option value="<?= $p['id'] ?>" <?= $sel ?> data-rivales="<?= $dataRivales ?>">
                  <?= epl_h($p['local_nombre'].' vs '.$p['visitante_nombre']) ?>
                  <?= $p['fecha_programada'] ? ' — '.date('d/m/Y', strtotime($p['fecha_programada'])) : ' — Sin fecha' ?>
                  <?= $p['estado']==='reprogramado' ? ' (reprogramado)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Contactar Rivales -->
          <div id="seccionRivales" style="display:none; background:#F0F9FF; border-radius:12px; padding:1.25rem; margin-bottom:1.5rem; border:1px solid #BAE6FD">
            <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:1rem">
              <svg style="width:18px; height:18px; color:#0369A1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
              <span style="font-family:var(--font-head); font-size:.82rem; text-transform:uppercase; color:#0369A1; letter-spacing:.05em">Contactar Rivales (Coordinación)</span>
            </div>
            <div id="listaRivales" style="display:flex; flex-direction:column; gap:.75rem"></div>
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

function actualizarRivales(sel) {
  const wrapper = document.getElementById('seccionRivales');
  const lista = document.getElementById('listaRivales');
  const opt = sel.options[sel.selectedIndex];
  
  if (!opt.value || !opt.dataset.rivales) {
    wrapper.style.display = 'none';
    return;
  }
  
  const rivales = JSON.parse(atob(opt.dataset.rivales));
  lista.innerHTML = '';
  
  rivales.forEach(r => {
    if (!r.n) return;
    const item = document.createElement('div');
    item.style.display = 'flex';
    item.style.alignItems = 'center';
    item.style.justifyContent = 'space-between';
    item.style.background = '#fff';
    item.style.padding = '.75rem 1rem';
    item.style.borderRadius = '10px';
    item.style.border = '1px solid #E0F2FE';
    
    const name = r.n + ' ' + (r.a || '');
    const tel = r.t ? r.t.replace(/\D/g, '') : '';
    const cleanTel = tel.startsWith('56') ? tel : '56' + tel;
    const msg = encodeURIComponent('Hola ' + r.n + ', te contacto por el partido de la Elite Padel League. ¿Podemos coordinar la reprogramación?');
    const wsp = tel ? `https://wa.me/${cleanTel}?text=${msg}` : null;
    
    let html = `<span style="font-weight:600; color:var(--navy); font-size:.9rem">${name}</span>`;
    if (wsp) {
      html += `<a href="${wsp}" target="_blank" class="btn btn-sm" style="background:#22C55E; color:#fff; border:none; padding:.4rem .8rem; font-size:.7rem; font-weight:700">ESCRIBIR <svg style="width:14px;height:14px;display:inline-block;vertical-align:middle;margin-left:4px" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.588-5.946 0-6.556 5.332-11.888 11.888-11.888 3.176 0 6.161 1.237 8.404 3.48s3.481 5.229 3.481 8.404c0 6.556-5.332 11.888-11.888 11.888-2.003 0-3.963-.505-5.698-1.465l-6.305 1.693zm6.443-4.045c1.474.873 3.103 1.332 4.775 1.332 5.054 0 9.163-4.109 9.163-9.163s-4.109-9.163-9.163-9.163-9.163 4.109-9.163 9.163c0 1.95.623 3.856 1.799 5.437l-1.002 3.659 3.743-.999zm10.742-5.466c-.303-.151-1.788-.882-2.067-.981-.278-.099-.481-.151-.683.151-.202.303-.783.981-.96 1.183-.177.202-.354.227-.657.076-.303-.151-1.28-.471-2.438-1.504-.901-.803-1.508-1.796-1.685-2.098-.177-.302-.019-.465.132-.615.136-.135.303-.354.455-.53.151-.177.202-.303.303-.505.101-.202.051-.379-.025-.53-.076-.151-.683-1.643-.935-2.249-.245-.59-.495-.51-.683-.52l-.582-.01c-.202 0-.531.076-.809.379-.278.303-1.062 1.037-1.062 2.529 0 1.492 1.087 2.932 1.239 3.134.151.202 2.14 3.268 5.184 4.582.724.312 1.29.499 1.731.639.727.231 1.388.199 1.911.121.582-.087 1.788-.731 2.041-1.439.253-.708.253-1.313.177-1.439-.076-.126-.278-.202-.581-.353z"/></svg></a>`;
    } else {
      html += `<span style="font-size:.7rem; color:var(--gray-400); font-style:italic">Sin teléfono</span>`;
    }
    
    item.innerHTML = html;
    lista.appendChild(item);
  });
  
  wrapper.style.display = 'block';
}

// Ejecutar si ya hay un partido seleccionado (por GET)
document.addEventListener('DOMContentLoaded', () => {
  const sel = document.querySelector('select[name="partido_id"]');
  if (sel && sel.value) actualizarRivales(sel);
});
</script>

<?php require_once 'includes/footer.php'; ?>
