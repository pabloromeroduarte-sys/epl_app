<?php
$page_title = 'Admin — Inscripciones';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db  = epl_db();
$ok  = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($id && $action === 'aprobar') {
        // Aprobar inscripción: también asignar equipo a liga si aplica
        $stI = $db->prepare("SELECT * FROM inscripciones WHERE id=?");
        $stI->execute([$id]);
        $insc = $stI->fetch();

        if ($insc) {
            $db->prepare("UPDATE inscripciones SET estado='aprobada' WHERE id=?")->execute([$id]);

            // Si tiene equipo, inscribirlo en la liga
            if ($insc['equipo_id']) {
                $db->prepare("INSERT IGNORE INTO liga_equipos (liga_id, equipo_id) VALUES (?,?)")
                   ->execute([$insc['liga_id'], $insc['equipo_id']]);
                // Asegurar clasificación
                $db->prepare("INSERT IGNORE INTO clasificacion (liga_id, equipo_id) VALUES (?,?)")
                   ->execute([$insc['liga_id'], $insc['equipo_id']]);
            }
            $ok = 'Inscripción aprobada.';
        }
    } elseif ($id && $action === 'rechazar') {
        $db->prepare("UPDATE inscripciones SET estado='rechazada' WHERE id=?")->execute([$id]);
        $ok = 'Inscripción rechazada.';
    } elseif ($id && $action === 'marcar_pagado') {
        $db->prepare("UPDATE inscripciones SET pago_estado='pagado' WHERE id=?")->execute([$id]);
        $ok = 'Pago registrado.';
    } elseif ($id && $action === 'marcar_exento') {
        $db->prepare("UPDATE inscripciones SET pago_estado='exento' WHERE id=?")->execute([$id]);
        $ok = 'Exención registrada.';
    }
}

// Filtro por liga
$filtro_liga = (int)($_GET['liga_id'] ?? 0);
$ligas = $db->query("SELECT id, nombre, temporada FROM ligas ORDER BY id DESC")->fetchAll();

$where = $filtro_liga ? "WHERE i.liga_id=$filtro_liga" : '';
$inscripciones = $db->query("
    SELECT i.*,
           j.nombre AS j_nombre, j.apellido AS j_apellido, j.email AS j_email,
           l.nombre AS liga_nombre, l.temporada,
           e.nombre AS equipo_nombre
    FROM inscripciones i
    JOIN jugadores j ON j.id = i.jugador_id
    JOIN ligas     l ON l.id = i.liga_id
    LEFT JOIN equipos e ON e.id = i.equipo_id
    $where
    ORDER BY i.fecha DESC
")->fetchAll();
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header flex justify-between items-center">
      <h1 class="dash-title">Inscripciones</h1>
    </div>

    <?php if ($ok): ?><div class="alert alert-success"><?= epl_h($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>

    <!-- Filtro liga -->
    <div class="card mb-4" style="padding:1rem 1.5rem">
      <form method="get" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
        <label style="font-size:.8rem;font-weight:700;text-transform:uppercase;color:var(--navy)">Filtrar por liga:</label>
        <select name="liga_id" class="form-control" style="width:auto" onchange="this.form.submit()">
          <option value="">Todas</option>
          <?php foreach ($ligas as $l): ?>
            <option value="<?= $l['id'] ?>" <?= $filtro_liga==$l['id']?'selected':'' ?>>
              <?= epl_h($l['nombre']) ?> <?= $l['temporada']?'('.$l['temporada'].')':'' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <div class="card">
      <div style="overflow-x:auto">
        <table class="admin-table-cards" style="width:100%;border-collapse:collapse;font-size:.85rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Jugador</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Liga</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Equipo</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Fecha</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Estado</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Pago</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($inscripciones)): ?>
            <tr><td colspan="7" style="padding:2rem;text-align:center;color:var(--gray-400)">No hay inscripciones.</td></tr>
            <?php endif; ?>
            <?php foreach ($inscripciones as $insc):
              $estBadge = 'badge-pendiente';
              switch($insc['estado']) {
                case 'aprobada':  $estBadge = 'badge-jugado'; break;
                case 'rechazada': $estBadge = 'badge-walkover'; break;
              }
              $pagoColor = $insc['pago_estado'] === 'pagado' ? '#22c55e' : ($insc['pago_estado'] === 'exento' ? 'var(--gold)' : '#ef4444');
            ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td data-label="Jugador" style="padding:.7rem 1rem">
                <div style="font-weight:700;color:var(--navy)"><?= epl_h($insc['j_nombre'].' '.$insc['j_apellido']) ?></div>
                <div style="font-size:.72rem;color:var(--gray-400)"><?= epl_h($insc['j_email']) ?></div>
              </td>
              <td data-label="Liga" class="hide-mobile" style="padding:.7rem 1rem;text-align:center;color:var(--gray-600);font-size:.82rem"><?= epl_h($insc['liga_nombre']) ?></td>
              <td data-label="Equipo" class="hide-mobile" style="padding:.7rem 1rem;text-align:center;color:var(--gray-600);font-size:.82rem"><?= $insc['equipo_nombre'] ? epl_h($insc['equipo_nombre']) : '<span style="color:var(--gray-400)">—</span>' ?></td>
              <td data-label="Fecha" class="hide-mobile" style="padding:.7rem 1rem;text-align:center;color:var(--gray-400);font-size:.78rem"><?= date('d/m/Y', strtotime($insc['fecha'])) ?></td>
              <td data-label="Estado" style="padding:.7rem 1rem;text-align:center">
                <span class="badge <?= $estBadge ?>"><?= ucfirst($insc['estado']) ?></span>
              </td>
              <td data-label="Pago" style="padding:.7rem 1rem;text-align:center;font-size:.78rem;font-weight:700;color:<?= $pagoColor ?>">
                <?= ucfirst($insc['pago_estado']) ?>
              </td>
              <td data-label="Acciones" style="padding:.7rem 1rem;text-align:center">
                <div style="display:flex;gap:.4rem;justify-content:center;flex-wrap:wrap">
                  <?php if ($insc['estado'] === 'pendiente'): ?>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="id" value="<?= $insc['id'] ?>">
                      <input type="hidden" name="action" value="aprobar">
                      <button class="btn btn-primary btn-sm">Aprobar</button>
                    </form>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="id" value="<?= $insc['id'] ?>">
                      <input type="hidden" name="action" value="rechazar">
                      <button class="btn btn-sm" style="border:1.5px solid var(--red);color:var(--red)">Rechazar</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($insc['pago_estado'] === 'pendiente' && $insc['estado'] !== 'rechazada'): ?>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="id" value="<?= $insc['id'] ?>">
                      <input type="hidden" name="action" value="marcar_pagado">
                      <button class="btn btn-sm" style="border:1.5px solid #22c55e;color:#22c55e">Pagado</button>
                    </form>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="id" value="<?= $insc['id'] ?>">
                      <input type="hidden" name="action" value="marcar_exento">
                      <button class="btn btn-sm" style="border:1.5px solid var(--gold);color:var(--gold-dark)">Exento</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<?php require_once '../includes/footer.php'; ?>
