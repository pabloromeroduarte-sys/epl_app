<?php
require_once __DIR__ . '/includes/functions.php';

$token = trim($_GET['t'] ?? $_POST['t'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/i', $token)) {
    http_response_code(404);
    $error_msg = 'Enlace inválido.';
    $partido = null;
} else {
    $db = epl_db();
    $st = $db->prepare("
        SELECT p.id, p.fecha_programada, p.fecha_original, p.estado,
               p.baja_confirmada_at, p.baja_confirmada_por,
               l.nombre AS liga_nombre,
               el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
               r.nombre AS recinto_nuevo, ro.nombre AS recinto_original
        FROM partidos p
        JOIN ligas l    ON l.id = p.liga_id
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN recintos r  ON r.id  = p.recinto_id
        LEFT JOIN recintos ro ON ro.id = p.recinto_original_id
        WHERE p.baja_token = ?
        LIMIT 1
    ");
    $st->execute([$token]);
    $partido = $st->fetch(PDO::FETCH_ASSOC);
    $error_msg = $partido ? '' : 'No encontramos esta solicitud. Pedile al admin un link nuevo.';
}

$confirmado = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $partido && !$partido['baja_confirmada_at']) {
    $quien = trim($_POST['quien'] ?? '');
    $db->prepare("UPDATE partidos SET baja_confirmada_at = NOW(), baja_confirmada_por = ? WHERE id = ?")
       ->execute([$quien ?: 'Club', $partido['id']]);

    // Notificar al admin
    try {
        $admins = epl_admins_ids();
        $msg = "El club confirmó la baja de la reserva del partido {$partido['local_nombre']} vs {$partido['visitante_nombre']}.";
        if ($quien) $msg .= " Confirmó: $quien.";
        foreach ($admins as $admin_id) {
            epl_notif_crear((int)$admin_id, 'admin', '✅ Baja de cancha confirmada', $msg, epl_url('admin/dashboard_repro.php'), true);
        }
    } catch (Throwable $e) {}

    $confirmado = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Confirmar baja de cancha — Elite Padel League</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Montserrat',sans-serif; background:linear-gradient(135deg,#1c2f48 0%,#0f1e30 100%); min-height:100vh; padding:1rem; display:flex; align-items:center; justify-content:center; }
    .card { background:#fff; max-width:480px; width:100%; border-radius:18px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.3); }
    .hdr { background:linear-gradient(135deg,#1c2f48,#0f1e30); padding:1.5rem; color:#fff; text-align:center; }
    .hdr h1 { font-family:'Anton',sans-serif; font-size:1.5rem; text-transform:uppercase; }
    .hdr .gold { color:#C9A762; }
    .body { padding:1.5rem; }
    .info { background:#f8fafc; border-radius:10px; padding:1rem; margin-bottom:1rem; font-size:.88rem; }
    .info-row { display:flex; justify-content:space-between; padding:.4rem 0; border-bottom:1px solid #f1f5f9; }
    .info-row:last-child { border-bottom:none; }
    .info-row span:first-child { color:#64748b; font-weight:600; font-size:.78rem; }
    .info-row span:last-child { color:#1c2f48; font-weight:700; font-size:.85rem; text-align:right; }
    .alert { padding:1rem; border-radius:10px; margin-bottom:1rem; font-size:.88rem; }
    .alert-ok    { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
    .alert-warn  { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
    .alert-info  { background:#dbeafe; color:#1e40af; border:1px solid #93c5fd; }
    label { display:block; font-size:.78rem; color:#1c2f48; font-weight:800; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.35rem; }
    input { width:100%; padding:.85rem 1rem; border:1.5px solid #e2e8f0; border-radius:10px; font-size:.95rem; font-family:inherit; }
    input:focus { outline:none; border-color:#C9A762; }
    .btn { display:block; width:100%; padding:1rem; border-radius:12px; border:none; font-family:inherit; font-size:.85rem; font-weight:900; text-transform:uppercase; letter-spacing:.06em; cursor:pointer; margin-top:1rem; text-decoration:none; text-align:center; }
    .btn-primary { background:#C9A762; color:#1c2f48; }
    .btn-primary:hover { background:#1c2f48; color:#C9A762; }
    .check-big { font-size:4rem; text-align:center; margin:1rem 0; }
  </style>
</head>
<body>
  <div class="card">
    <div class="hdr">
      <h1>Elite <span class="gold">Padel</span> League</h1>
      <p style="color:#C9A762;font-size:.7rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;margin-top:.4rem">Confirmar baja de cancha</p>
    </div>
    <div class="body">

    <?php if ($error_msg): ?>
      <div class="alert alert-warn"><?= epl_h($error_msg) ?></div>

    <?php elseif ($partido['baja_confirmada_at']): ?>
      <div class="check-big">✅</div>
      <div class="alert alert-ok">
        <strong>Esta baja ya fue confirmada</strong><br>
        <?= date('d/m/Y H:i', strtotime($partido['baja_confirmada_at'])) ?>
        <?php if ($partido['baja_confirmada_por']): ?>
          por <?= epl_h($partido['baja_confirmada_por']) ?>
        <?php endif; ?>
      </div>

    <?php elseif ($confirmado): ?>
      <div class="check-big">🎾</div>
      <div class="alert alert-ok">
        <strong>¡Gracias!</strong> Confirmaste la baja correctamente. El admin de Elite Padel League fue notificado.
      </div>

    <?php else: ?>
      <p style="text-align:center;color:#64748b;font-size:.88rem;margin-bottom:1rem">
        Te pedimos confirmar la baja de la siguiente reserva:
      </p>
      <div class="info">
        <div class="info-row">
          <span>Partido</span>
          <span><?= epl_h($partido['local_nombre']) ?> vs <?= epl_h($partido['visitante_nombre']) ?></span>
        </div>
        <div class="info-row">
          <span>Liga</span>
          <span><?= epl_h($partido['liga_nombre']) ?></span>
        </div>
        <?php if ($partido['fecha_original']): ?>
        <div class="info-row">
          <span>🚫 Baja en</span>
          <span style="color:#dc2626"><?= date('d/m/Y H:i', strtotime($partido['fecha_original'])) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($partido['recinto_original']): ?>
        <div class="info-row">
          <span>Cancha original</span>
          <span style="color:#dc2626"><?= epl_h($partido['recinto_original']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($partido['fecha_programada'] && date('Y-m-d',strtotime($partido['fecha_programada'])) !== '2026-12-31'): ?>
        <div class="info-row">
          <span>✅ Nueva fecha</span>
          <span style="color:#15803d"><?= date('d/m/Y H:i', strtotime($partido['fecha_programada'])) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($partido['recinto_nuevo']): ?>
        <div class="info-row">
          <span>Nueva cancha</span>
          <span style="color:#15803d"><?= epl_h($partido['recinto_nuevo']) ?></span>
        </div>
        <?php endif; ?>
      </div>

      <form method="post">
        <input type="hidden" name="t" value="<?= epl_h($token) ?>">
        <label>Tu nombre (opcional)</label>
        <input type="text" name="quien" placeholder="Ej: Hugo, encargado" maxlength="100">
        <button type="submit" class="btn btn-primary">✅ Confirmar baja</button>
      </form>

      <p style="text-align:center;font-size:.72rem;color:#94a3b8;margin-top:1rem;line-height:1.4">
        Al confirmar, el admin de EPL queda notificado automáticamente.
      </p>
    <?php endif; ?>

    </div>
  </div>
</body>
</html>
