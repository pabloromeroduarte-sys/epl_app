<?php
$page_title = 'ERP Financiero';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db  = epl_db();
$ok  = '';
$err = '';
$tab = $_GET['tab'] ?? 'ingresos';

// =========================================================
// PROCESADOR POST
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Forzar pago como completado ---
    if ($action === 'forzar_pago') {
        $pid = (int)($_POST['pago_id'] ?? 0);
        if ($pid) {
            $db->prepare("UPDATE pagos SET estado='completado', metodo='Transferencia / Efectivo', mp_payment_id='Forzado por admin' WHERE id=?")
               ->execute([$pid]);
            $ok = 'Pago marcado como completado.';
        }
        $tab = 'ingresos';
    }

    // --- Ingreso manual ---
    if ($action === 'ingreso_manual') {
        $liga_id  = (int)($_POST['m_liga']    ?? 0) ?: null;
        $concepto = trim($_POST['m_concepto'] ?? '');
        $monto    = (float)($_POST['m_monto']   ?? 0);
        $rol      = in_array($_POST['m_rol']??'manual',['capitan','partner','global','manual']) ? $_POST['m_rol'] : 'manual';
        $notas    = trim($_POST['m_notas']    ?? '');
        if ($concepto && $monto > 0) {
            epl_pago_crear([
                'liga_id'  => $liga_id,
                'concepto' => $concepto,
                'rol'      => $rol,
                'monto'    => $monto,
                'estado'   => 'completado',
                'metodo'   => 'Manual',
                'notas'    => $notas,
            ]);
            $ok = 'Ingreso manual registrado.';
        } else {
            $err = 'Concepto y monto son obligatorios.';
        }
        $tab = 'ingresos';
    }

    // --- Eliminar ingreso ---
    if ($action === 'eliminar_pago') {
        $pid = (int)($_POST['pago_id'] ?? 0);
        if ($pid) {
            $db->prepare("DELETE FROM pagos WHERE id=?")->execute([$pid]);
            $ok = 'Registro eliminado.';
        }
        $tab = 'ingresos';
    }

    // --- Agregar gasto ---
    if ($action === 'agregar_gasto') {
        $liga_id   = (int)($_POST['g_liga']      ?? 0) ?: null;
        $concepto  = trim($_POST['g_concepto']   ?? '');
        $categoria = trim($_POST['g_categoria']  ?? '');
        $monto     = (float)($_POST['g_monto']   ?? 0);
        $fecha     = trim($_POST['g_fecha']      ?? '') ?: date('Y-m-d');
        $notas     = trim($_POST['g_notas']      ?? '');
        if ($concepto && $monto > 0) {
            $db->prepare("INSERT INTO gastos (liga_id, concepto, categoria, monto, fecha, notas) VALUES (?,?,?,?,?,?)")
               ->execute([$liga_id, $concepto, $categoria ?: null, $monto, $fecha, $notas ?: null]);
            $ok = 'Gasto registrado.';
        } else {
            $err = 'Concepto y monto son obligatorios.';
        }
        $tab = 'gastos';
    }

    // --- Eliminar gasto ---
    if ($action === 'eliminar_gasto') {
        $gid = (int)($_POST['gasto_id'] ?? 0);
        if ($gid) {
            $db->prepare("DELETE FROM gastos WHERE id=?")->execute([$gid]);
            $ok = 'Gasto eliminado.';
        }
        $tab = 'gastos';
    }

    // --- Agregar movimiento club ---
    if ($action === 'agregar_saldo') {
        $liga_id = (int)($_POST['s_liga']  ?? 0) ?: null;
        $club    = trim($_POST['s_club']   ?? '');
        $tipo    = in_array($_POST['s_tipo']??'ingreso',['ingreso','egreso']) ? $_POST['s_tipo'] : 'ingreso';
        $monto   = (float)($_POST['s_monto'] ?? 0);
        $fecha   = trim($_POST['s_fecha']  ?? '') ?: date('Y-m-d');
        $notas   = trim($_POST['s_notas']  ?? '');
        if ($club && $monto > 0) {
            $db->prepare("INSERT INTO saldos_clubes (liga_id, club, tipo, monto, fecha, notas) VALUES (?,?,?,?,?,?)")
               ->execute([$liga_id, $club, $tipo, $monto, $fecha, $notas ?: null]);
            $ok = 'Movimiento registrado.';
        } else {
            $err = 'Club y monto son obligatorios.';
        }
        $tab = 'saldos';
    }

    // --- Eliminar movimiento club ---
    if ($action === 'eliminar_saldo') {
        $sid = (int)($_POST['saldo_id'] ?? 0);
        if ($sid) {
            $db->prepare("DELETE FROM saldos_clubes WHERE id=?")->execute([$sid]);
            $ok = 'Movimiento eliminado.';
        }
        $tab = 'saldos';
    }
}

// =========================================================
// DATOS
// =========================================================
$ligas  = $db->query("SELECT id, nombre FROM ligas ORDER BY id DESC")->fetchAll();
$pagos  = $db->query("
    SELECT p.*, l.nombre AS liga_nombre, j.nombre AS j_nombre, j.apellido AS j_apellido
    FROM pagos p
    LEFT JOIN ligas l     ON l.id = p.liga_id
    LEFT JOIN jugadores j ON j.id = p.jugador_id
    ORDER BY p.fecha DESC
")->fetchAll();
$gastos = $db->query("
    SELECT g.*, l.nombre AS liga_nombre
    FROM gastos g
    LEFT JOIN ligas l ON l.id = g.liga_id
    ORDER BY g.fecha DESC
")->fetchAll();
$saldos = $db->query("
    SELECT s.*, l.nombre AS liga_nombre
    FROM saldos_clubes s
    LEFT JOIN ligas l ON l.id = s.liga_id
    ORDER BY s.fecha DESC
")->fetchAll();

// Cálculos resumen
$total_ingresos  = array_sum(array_column(array_filter($pagos, fn($p) => $p['estado']==='completado'), 'monto'));
$total_pendiente = array_sum(array_column(array_filter($pagos, fn($p) => $p['estado']==='pendiente'),  'monto'));
$total_gastos    = array_sum(array_column($gastos, 'monto'));
$saldo_neto      = $total_ingresos - $total_gastos;


require_once '../includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">

    <div class="dash-header">
      <div>
        <h1 class="dash-title">ERP Financiero</h1>
        <p class="dash-subtitle">Control de ingresos, gastos y estado de resultados.</p>
      </div>
    </div>

    <?php if ($ok): ?><div class="alert alert-success"><?= epl_h($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>

    <!-- Tarjetas resumen -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem">
      <div class="card" style="padding:1.25rem;text-align:center">
        <div style="font-size:.72rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.4rem">Ingresos cobrados</div>
        <div style="font-size:1.6rem;font-weight:800;color:#16a34a">$<?= number_format($total_ingresos,0,',','.') ?></div>
      </div>
      <div class="card" style="padding:1.25rem;text-align:center">
        <div style="font-size:.72rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.4rem">Pendiente cobro</div>
        <div style="font-size:1.6rem;font-weight:800;color:#d97706">$<?= number_format($total_pendiente,0,',','.') ?></div>
      </div>
      <div class="card" style="padding:1.25rem;text-align:center">
        <div style="font-size:.72rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.4rem">Total gastos</div>
        <div style="font-size:1.6rem;font-weight:800;color:#dc2626">$<?= number_format($total_gastos,0,',','.') ?></div>
      </div>
      <div class="card" style="padding:1.25rem;text-align:center">
        <div style="font-size:.72rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.4rem">Saldo neto</div>
        <div style="font-size:1.6rem;font-weight:800;color:<?= $saldo_neto>=0?'#1C2F48':'#dc2626' ?>">$<?= number_format($saldo_neto,0,',','.') ?></div>
      </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem">
      <?php foreach(['ingresos'=>'Ingresos','gastos'=>'Gastos','saldos'=>'Saldos Clubes','resultado'=>'Estado Resultados'] as $t => $label): ?>
        <a href="?tab=<?= $t ?>" style="padding:.45rem 1rem;border-radius:8px;font-size:.83rem;font-weight:700;text-decoration:none;
           background:<?= $tab===$t?'var(--navy)':'#f1f5f9' ?>;
           color:<?= $tab===$t?'#fff':'var(--gray-600)' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- ===== TAB: INGRESOS ===== -->
    <?php if ($tab === 'ingresos'): ?>
    <div class="card" style="margin-bottom:1.25rem">
      <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
        <h2 style="font-size:1rem;font-weight:700;color:var(--navy);margin:0">Ingresos / Pagos recibidos</h2>
        <button onclick="document.getElementById('modalIngreso').style.display='flex'" class="btn btn-primary btn-sm">+ Ingreso manual</button>
      </div>
      <div style="overflow-x:auto">
        <table class="admin-table-cards" style="width:100%;border-collapse:collapse;font-size:.83rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.6rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Concepto / Jugador</th>
              <th class="hide-mobile" style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Liga</th>
              <th class="hide-mobile" style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Rol</th>
              <th style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Monto</th>
              <th style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Estado</th>
              <th class="hide-mobile" style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Fecha</th>
              <th style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Acc.</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($pagos)): ?>
              <tr><td colspan="7" style="padding:2rem;text-align:center;color:var(--gray-400)">No hay registros aún.</td></tr>
            <?php endif; ?>
            <?php foreach ($pagos as $p): ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td data-label="Concepto" style="padding:.6rem 1rem">
                <div style="font-weight:700;color:var(--navy)"><?= epl_h($p['concepto']) ?></div>
                <?php if ($p['j_nombre']): ?>
                  <div style="font-size:.75rem;color:var(--gray-400)"><?= epl_h($p['j_nombre'].' '.$p['j_apellido']) ?></div>
                <?php endif; ?>
                <?php if ($p['metodo']): ?>
                  <div style="font-size:.72rem;color:var(--gray-500)"><?= epl_h($p['metodo']) ?></div>
                <?php endif; ?>
              </td>
              <td data-label="Liga" class="hide-mobile" style="padding:.6rem 1rem;font-size:.8rem;color:var(--gray-600)"><?= epl_h($p['liga_nombre'] ?? '—') ?></td>
              <td data-label="Rol" class="hide-mobile" style="padding:.6rem 1rem;text-align:center">
                <?php
                  $rol_badge = ['capitan'=>'badge-jugado','partner'=>'badge-pendiente','global'=>'badge-reprog','manual'=>''];
                  echo '<span class="badge '.($rol_badge[$p['rol']]??'').'">'.$p['rol'].'</span>';
                ?>
              </td>
              <td data-label="Monto" style="padding:.6rem 1rem;font-weight:700;color:var(--navy)">$<?= number_format($p['monto'],0,',','.') ?></td>
              <td data-label="Estado" style="padding:.6rem 1rem">
                <?php if ($p['estado']==='completado'): ?>
                  <span class="badge badge-jugado">✓ Pagado</span>
                <?php elseif ($p['estado']==='pendiente'): ?>
                  <span class="badge badge-pendiente">Pendiente</span>
                  <form method="POST" style="display:inline;margin-left:.35rem">
                    <input type="hidden" name="action"   value="forzar_pago">
                    <input type="hidden" name="pago_id"  value="<?= $p['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="font-size:.7rem;padding:.25rem .6rem;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0" onclick="return confirm('Marcar como pagado por transferencia?')">Forzar ✓</button>
                  </form>
                <?php else: ?>
                  <span class="badge badge-walkover"><?= epl_h($p['estado']) ?></span>
                <?php endif; ?>
              </td>
              <td data-label="Fecha" class="hide-mobile" style="padding:.6rem 1rem;font-size:.78rem;color:var(--gray-500)"><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
              <td data-label="Acción" style="padding:.6rem 1rem">
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action"  value="eliminar_pago">
                  <input type="hidden" name="pago_id" value="<?= $p['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;font-size:.72rem;padding:.2rem .55rem" onclick="return confirm('Eliminar permanentemente?')">✕</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ===== TAB: GASTOS ===== -->
    <?php if ($tab === 'gastos'): ?>
    <div class="card" style="margin-bottom:1.25rem">
      <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
        <h2 style="font-size:1rem;font-weight:700;color:var(--navy);margin:0">Registro de Gastos</h2>
        <button onclick="document.getElementById('modalGasto').style.display='flex'" class="btn btn-primary btn-sm">+ Nuevo gasto</button>
      </div>
      <div style="overflow-x:auto">
        <table class="admin-table-cards" style="width:100%;border-collapse:collapse;font-size:.83rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.6rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Concepto</th>
              <th class="hide-mobile" style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Liga</th>
              <th class="hide-mobile" style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Categoría</th>
              <th style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Monto</th>
              <th class="hide-mobile" style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Fecha</th>
              <th style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Acc.</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($gastos)): ?>
              <tr><td colspan="6" style="padding:2rem;text-align:center;color:var(--gray-400)">No hay gastos registrados.</td></tr>
            <?php endif; ?>
            <?php foreach ($gastos as $g): ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td data-label="Concepto" style="padding:.6rem 1rem">
                <div style="font-weight:700;color:var(--navy)"><?= epl_h($g['concepto']) ?></div>
                <?php if ($g['notas']): ?><div style="font-size:.75rem;color:var(--gray-400)"><?= epl_h($g['notas']) ?></div><?php endif; ?>
              </td>
              <td data-label="Liga" class="hide-mobile" style="padding:.6rem 1rem;font-size:.8rem;color:var(--gray-600)"><?= epl_h($g['liga_nombre'] ?? '—') ?></td>
              <td data-label="Categoría" class="hide-mobile" style="padding:.6rem 1rem;font-size:.8rem">
                <?php if ($g['categoria']): ?><span class="badge badge-reprog"><?= epl_h($g['categoria']) ?></span><?php else: ?>—<?php endif; ?>
              </td>
              <td data-label="Monto" style="padding:.6rem 1rem;font-weight:700;color:#dc2626">$<?= number_format($g['monto'],0,',','.') ?></td>
              <td data-label="Fecha" class="hide-mobile" style="padding:.6rem 1rem;font-size:.78rem;color:var(--gray-500)"><?= date('d/m/Y', strtotime($g['fecha'])) ?></td>
              <td data-label="Acción" style="padding:.6rem 1rem">
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action"   value="eliminar_gasto">
                  <input type="hidden" name="gasto_id" value="<?= $g['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;font-size:.72rem;padding:.2rem .55rem" onclick="return confirm('Eliminar gasto?')">✕</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ===== TAB: SALDOS CLUBES ===== -->
    <?php if ($tab === 'saldos'): ?>
    <div class="card" style="margin-bottom:1.25rem">
      <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
        <h2 style="font-size:1rem;font-weight:700;color:var(--navy);margin:0">Saldos en Clubes</h2>
        <button onclick="document.getElementById('modalSaldo').style.display='flex'" class="btn btn-primary btn-sm">+ Movimiento</button>
      </div>
      <div style="overflow-x:auto">
        <table class="admin-table-cards" style="width:100%;border-collapse:collapse;font-size:.83rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.6rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Club</th>
              <th class="hide-mobile" style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Liga</th>
              <th style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Tipo</th>
              <th style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Monto</th>
              <th class="hide-mobile" style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Fecha</th>
              <th style="padding:.6rem 1rem;font-size:.72rem;text-transform:uppercase">Acc.</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($saldos)): ?>
              <tr><td colspan="6" style="padding:2rem;text-align:center;color:var(--gray-400)">No hay movimientos.</td></tr>
            <?php endif; ?>
            <?php foreach ($saldos as $s): ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td data-label="Club" style="padding:.6rem 1rem">
                <div style="font-weight:700;color:var(--navy)"><?= epl_h($s['club']) ?></div>
                <?php if ($s['notas']): ?><div style="font-size:.75rem;color:var(--gray-400)"><?= epl_h($s['notas']) ?></div><?php endif; ?>
              </td>
              <td data-label="Liga" class="hide-mobile" style="padding:.6rem 1rem;font-size:.8rem;color:var(--gray-600)"><?= epl_h($s['liga_nombre'] ?? '—') ?></td>
              <td data-label="Tipo" style="padding:.6rem 1rem">
                <span class="badge <?= $s['tipo']==='ingreso'?'badge-jugado':'badge-walkover' ?>"><?= $s['tipo'] ?></span>
              </td>
              <td data-label="Monto" style="padding:.6rem 1rem;font-weight:700;color:<?= $s['tipo']==='ingreso'?'#16a34a':'#dc2626' ?>">$<?= number_format($s['monto'],0,',','.') ?></td>
              <td data-label="Fecha" class="hide-mobile" style="padding:.6rem 1rem;font-size:.78rem;color:var(--gray-500)"><?= date('d/m/Y', strtotime($s['fecha'])) ?></td>
              <td data-label="Acción" style="padding:.6rem 1rem">
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action"   value="eliminar_saldo">
                  <input type="hidden" name="saldo_id" value="<?= $s['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;font-size:.72rem;padding:.2rem .55rem" onclick="return confirm('Eliminar movimiento?')">✕</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ===== TAB: ESTADO DE RESULTADOS ===== -->
    <?php if ($tab === 'resultado'): ?>
    <?php
      // Agrupar ingresos por liga
      $ing_por_liga = [];
      foreach ($pagos as $p) {
          if ($p['estado'] !== 'completado') continue;
          $key = $p['liga_nombre'] ?? 'Sin liga';
          $ing_por_liga[$key] = ($ing_por_liga[$key] ?? 0) + $p['monto'];
      }
      arsort($ing_por_liga);

      $gas_por_cat = [];
      foreach ($gastos as $g) {
          $key = $g['categoria'] ?? 'Sin categoría';
          $gas_por_cat[$key] = ($gas_por_cat[$key] ?? 0) + $g['monto'];
      }
      arsort($gas_por_cat);
    ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.25rem">

      <div class="card">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100)">
          <h2 style="font-size:.95rem;font-weight:700;color:#16a34a;margin:0">Ingresos por Liga</h2>
        </div>
        <div style="padding:1rem">
          <?php if (empty($ing_por_liga)): ?>
            <p style="color:var(--gray-400);font-size:.85rem">Sin ingresos aún.</p>
          <?php endif; ?>
          <?php foreach ($ing_por_liga as $lig => $monto): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.45rem 0;border-bottom:1px solid var(--gray-100);font-size:.85rem">
              <span style="font-weight:600;color:var(--navy)"><?= epl_h($lig) ?></span>
              <span style="font-weight:700;color:#16a34a">$<?= number_format($monto,0,',','.') ?></span>
            </div>
          <?php endforeach; ?>
          <div style="display:flex;justify-content:space-between;padding:.6rem 0;font-weight:800;font-size:.9rem;margin-top:.5rem">
            <span>TOTAL INGRESOS</span>
            <span style="color:#16a34a">$<?= number_format($total_ingresos,0,',','.') ?></span>
          </div>
        </div>
      </div>

      <div class="card">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100)">
          <h2 style="font-size:.95rem;font-weight:700;color:#dc2626;margin:0">Gastos por Categoría</h2>
        </div>
        <div style="padding:1rem">
          <?php if (empty($gas_por_cat)): ?>
            <p style="color:var(--gray-400);font-size:.85rem">Sin gastos aún.</p>
          <?php endif; ?>
          <?php foreach ($gas_por_cat as $cat => $monto): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.45rem 0;border-bottom:1px solid var(--gray-100);font-size:.85rem">
              <span style="font-weight:600;color:var(--navy)"><?= epl_h($cat) ?></span>
              <span style="font-weight:700;color:#dc2626">$<?= number_format($monto,0,',','.') ?></span>
            </div>
          <?php endforeach; ?>
          <div style="display:flex;justify-content:space-between;padding:.6rem 0;font-weight:800;font-size:.9rem;margin-top:.5rem">
            <span>TOTAL GASTOS</span>
            <span style="color:#dc2626">$<?= number_format($total_gastos,0,',','.') ?></span>
          </div>
        </div>
      </div>

      <div class="card">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100)">
          <h2 style="font-size:.95rem;font-weight:700;color:var(--navy);margin:0">Resumen Final</h2>
        </div>
        <div style="padding:1.25rem">
          <div style="display:flex;justify-content:space-between;padding:.5rem 0;font-size:.9rem">
            <span style="color:var(--gray-600)">Total ingresos cobrados</span>
            <span style="font-weight:700;color:#16a34a">+$<?= number_format($total_ingresos,0,',','.') ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;padding:.5rem 0;font-size:.9rem;border-bottom:2px solid var(--gray-100)">
            <span style="color:var(--gray-600)">Total gastos</span>
            <span style="font-weight:700;color:#dc2626">−$<?= number_format($total_gastos,0,',','.') ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;padding:.75rem 0;font-size:1.1rem;font-weight:800">
            <span style="color:var(--navy)">SALDO NETO</span>
            <span style="color:<?= $saldo_neto>=0?'#16a34a':'#dc2626' ?>">$<?= number_format($saldo_neto,0,',','.') ?></span>
          </div>
          <?php if ($total_pendiente > 0): ?>
          <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.75rem;margin-top:.5rem;font-size:.83rem">
            <strong style="color:#92400e">Por cobrar:</strong> $<?= number_format($total_pendiente,0,',','.') ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
    <?php endif; ?>

  </main>
</div>

<!-- Modal: Ingreso Manual -->
<div id="modalIngreso" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:1rem">
  <div style="background:#fff;border-radius:16px;padding:1.75rem;width:100%;max-width:480px;max-height:90vh;overflow-y:auto">
    <h3 style="font-size:1.1rem;font-weight:700;color:var(--navy);margin:0 0 1.25rem">Registrar Ingreso Manual</h3>
    <form method="POST">
      <input type="hidden" name="action" value="ingreso_manual">
      <div style="margin-bottom:.9rem">
        <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Liga / Torneo</label>
        <select name="m_liga" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
          <option value="">— Sin liga —</option>
          <?php foreach ($ligas as $l): ?>
            <option value="<?= $l['id'] ?>"><?= epl_h($l['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:.9rem">
        <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Concepto *</label>
        <input type="text" name="m_concepto" required placeholder="Ej: Inscripción Juan Pérez" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.9rem">
        <div>
          <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Monto (CLP) *</label>
          <input type="number" name="m_monto" required min="1" placeholder="50000" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
        </div>
        <div>
          <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Rol</label>
          <select name="m_rol" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
            <option value="manual">Manual</option>
            <option value="capitan">Capitán</option>
            <option value="partner">Partner</option>
            <option value="global">Global/Lote</option>
          </select>
        </div>
      </div>
      <div style="margin-bottom:1.25rem">
        <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Notas (opcional)</label>
        <textarea name="m_notas" rows="2" placeholder="Transferencia #123..." style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem;resize:vertical"></textarea>
      </div>
      <div style="display:flex;gap:.75rem">
        <button type="submit" class="btn btn-primary" style="flex:1">Guardar</button>
        <button type="button" onclick="document.getElementById('modalIngreso').style.display='none'" class="btn" style="flex:1;background:#f1f5f9;color:var(--gray-600)">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Gasto -->
<div id="modalGasto" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:1rem">
  <div style="background:#fff;border-radius:16px;padding:1.75rem;width:100%;max-width:480px;max-height:90vh;overflow-y:auto">
    <h3 style="font-size:1.1rem;font-weight:700;color:var(--navy);margin:0 0 1.25rem">Registrar Gasto</h3>
    <form method="POST">
      <input type="hidden" name="action" value="agregar_gasto">
      <div style="margin-bottom:.9rem">
        <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Liga / Torneo</label>
        <select name="g_liga" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
          <option value="">— Sin liga —</option>
          <?php foreach ($ligas as $l): ?>
            <option value="<?= $l['id'] ?>"><?= epl_h($l['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:.9rem">
        <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Concepto *</label>
        <input type="text" name="g_concepto" required placeholder="Ej: Pelotas, Trofeos, Árbitros" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.9rem">
        <div>
          <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Categoría</label>
          <input type="text" name="g_categoria" placeholder="Material deportivo" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
        </div>
        <div>
          <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Monto (CLP) *</label>
          <input type="number" name="g_monto" required min="1" placeholder="15000" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
        </div>
      </div>
      <div style="margin-bottom:.9rem">
        <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Fecha *</label>
        <input type="date" name="g_fecha" value="<?= date('Y-m-d') ?>" required style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
      </div>
      <div style="margin-bottom:1.25rem">
        <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Notas</label>
        <textarea name="g_notas" rows="2" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem;resize:vertical"></textarea>
      </div>
      <div style="display:flex;gap:.75rem">
        <button type="submit" class="btn btn-primary" style="flex:1">Guardar</button>
        <button type="button" onclick="document.getElementById('modalGasto').style.display='none'" class="btn" style="flex:1;background:#f1f5f9;color:var(--gray-600)">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Saldo Club -->
<div id="modalSaldo" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:1rem">
  <div style="background:#fff;border-radius:16px;padding:1.75rem;width:100%;max-width:480px;max-height:90vh;overflow-y:auto">
    <h3 style="font-size:1.1rem;font-weight:700;color:var(--navy);margin:0 0 1.25rem">Movimiento en Club</h3>
    <form method="POST">
      <input type="hidden" name="action" value="agregar_saldo">
      <div style="margin-bottom:.9rem">
        <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Liga / Torneo</label>
        <select name="s_liga" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
          <option value="">— Sin liga —</option>
          <?php foreach ($ligas as $l): ?>
            <option value="<?= $l['id'] ?>"><?= epl_h($l['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:.9rem">
        <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Club / Recinto *</label>
        <input type="text" name="s_club" required placeholder="Ej: Club Padel Santiago" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.9rem">
        <div>
          <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Tipo</label>
          <select name="s_tipo" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
            <option value="ingreso">Ingreso</option>
            <option value="egreso">Egreso</option>
          </select>
        </div>
        <div>
          <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Monto (CLP) *</label>
          <input type="number" name="s_monto" required min="1" placeholder="50000" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
        </div>
      </div>
      <div style="margin-bottom:.9rem">
        <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Fecha *</label>
        <input type="date" name="s_fecha" value="<?= date('Y-m-d') ?>" required style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
      </div>
      <div style="margin-bottom:1.25rem">
        <label style="font-size:.82rem;font-weight:700;color:var(--navy);display:block;margin-bottom:.35rem">Notas</label>
        <textarea name="s_notas" rows="2" style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem;resize:vertical"></textarea>
      </div>
      <div style="display:flex;gap:.75rem">
        <button type="submit" class="btn btn-primary" style="flex:1">Guardar</button>
        <button type="button" onclick="document.getElementById('modalSaldo').style.display='none'" class="btn" style="flex:1;background:#f1f5f9;color:var(--gray-600)">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
