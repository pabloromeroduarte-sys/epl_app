<?php
$page_title = 'Presupuestos de torneos';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();

// ── Migración ──────────────────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS presupuestos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(200) NOT NULL,
    tipo        VARCHAR(60)  NOT NULL DEFAULT 'torneo',
    referencia  VARCHAR(200) NULL,
    notas       TEXT         NULL,
    estado      VARCHAR(20)  NOT NULL DEFAULT 'borrador',
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS presupuesto_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    presupuesto_id  INT            NOT NULL,
    tipo            ENUM('ingreso','egreso','fase') NOT NULL,
    categoria       VARCHAR(100)   NOT NULL DEFAULT '',
    descripcion     VARCHAR(200)   NOT NULL DEFAULT '',
    cantidad        DECIMAL(10,2)  NOT NULL DEFAULT 1,
    valor_unitario  DECIMAL(12,2)  NOT NULL DEFAULT 0,
    orden           INT            NOT NULL DEFAULT 0,
    INDEX idx_pres (presupuesto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$_flash = epl_flash_get();
$ok  = ($_flash && $_flash['tipo']==='ok')    ? $_flash['msg'] : '';
$err = ($_flash && $_flash['tipo']==='error') ? $_flash['msg'] : '';

// ── Acciones POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'eliminar') {
        $pid = (int)($_POST['id'] ?? 0);
        if ($pid) {
            $db->prepare("DELETE FROM presupuesto_items WHERE presupuesto_id=?")->execute([$pid]);
            $db->prepare("DELETE FROM presupuestos WHERE id=?")->execute([$pid]);
            epl_redirect_ok('Presupuesto eliminado.', 'presupuestos.php');
        }
    }
}

// ── Lista ──────────────────────────────────────────────────────────────────────
$lista = $db->query("
    SELECT p.*,
        COALESCE((SELECT SUM(cantidad*valor_unitario) FROM presupuesto_items WHERE presupuesto_id=p.id AND tipo='ingreso'),0) AS total_ingresos,
        COALESCE((SELECT SUM(cantidad*valor_unitario) FROM presupuesto_items WHERE presupuesto_id=p.id AND tipo='egreso'), 0) AS total_egresos
    FROM presupuestos p
    ORDER BY p.updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once '../includes/header.php'; ?>
<style>
.pres-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:1rem;margin-top:1.25rem }
.pres-card { background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.6rem;transition:box-shadow .15s,border-color .15s }
.pres-card:hover { box-shadow:0 4px 18px rgba(0,0,0,.07);border-color:#cbd5e1 }
.pres-tipo { display:inline-flex;align-items:center;gap:.35rem;font-size:.7rem;font-weight:800;padding:.2rem .6rem;border-radius:999px;text-transform:uppercase;letter-spacing:.05em }
.pres-ganancia { font-family:var(--font-head);font-size:1.4rem;font-weight:900 }
</style>
<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
      <div>
        <h1 class="dash-title">💰 Presupuestos</h1>
        <p style="color:#64748b;font-size:.88rem;margin:.25rem 0 0">Planificá ingresos y costos por torneo. Guardá y descargá en PDF para revisar con tu socio.</p>
      </div>
      <a href="presupuesto_detalle.php" class="btn btn-primary">+ Nuevo presupuesto</a>
    </div>

    <?php if ($ok): ?><div class="alert alert-success"><?= epl_h($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>

    <?php if (empty($lista)): ?>
    <div style="background:#f8fafc;border:2px dashed #e2e8f0;border-radius:14px;text-align:center;padding:3.5rem 1rem;margin-top:1.5rem">
      <div style="font-size:2.5rem;margin-bottom:.5rem">📊</div>
      <p style="font-weight:700;color:#1C2F48;margin:0 0 .35rem">Sin presupuestos todavía</p>
      <p style="color:#64748b;font-size:.85rem;margin:0 0 1.25rem">Creá el primero para empezar a planificar tus torneos.</p>
      <a href="presupuesto_detalle.php" class="btn btn-primary">+ Crear presupuesto</a>
    </div>
    <?php else: ?>
    <div class="pres-grid">
      <?php foreach ($lista as $p):
        $gan = (float)$p['total_ingresos'] - (float)$p['total_egresos'];
        $tipoColors = ['torneo'=>['#6366f1','#eef2ff'],'liga'=>['#0891b2','#ecfeff'],'evento'=>['#d97706','#fffbeb'],'otro'=>['#64748b','#f1f5f9']];
        [$tc,$tb] = $tipoColors[$p['tipo']] ?? ['#64748b','#f1f5f9'];
        $estadoStyle = $p['estado']==='cerrado' ? 'background:#f0fdf4;color:#15803d' : 'background:#fffbeb;color:#92400e';
      ?>
      <div class="pres-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem">
          <div>
            <div style="font-weight:800;font-size:.95rem;color:#1C2F48"><?= epl_h($p['nombre']) ?></div>
            <?php if ($p['referencia']): ?>
              <div style="font-size:.75rem;color:#64748b;margin-top:.15rem"><?= epl_h($p['referencia']) ?></div>
            <?php endif; ?>
          </div>
          <span class="pres-tipo" style="background:<?= $tb ?>;color:<?= $tc ?>"><?= epl_h($p['tipo']) ?></span>
        </div>

        <div style="display:flex;gap:.75rem;padding:.75rem 0;border-top:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9">
          <div style="flex:1;text-align:center">
            <div style="font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">Ingresos</div>
            <div style="font-weight:800;color:#16a34a;font-size:.95rem">$<?= number_format((float)$p['total_ingresos'],0,',','.') ?></div>
          </div>
          <div style="flex:1;text-align:center">
            <div style="font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">Costos</div>
            <div style="font-weight:800;color:#dc2626;font-size:.95rem">$<?= number_format((float)$p['total_egresos'],0,',','.') ?></div>
          </div>
          <div style="flex:1;text-align:center">
            <div style="font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">Ganancia</div>
            <div class="pres-ganancia" style="color:<?= $gan >= 0 ? '#16a34a' : '#dc2626' ?>;font-size:.95rem">
              <?= $gan >= 0 ? '' : '-' ?>$<?= number_format(abs($gan),0,',','.') ?>
            </div>
          </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem">
          <span style="font-size:.72rem;font-weight:700;padding:.2rem .6rem;border-radius:999px;<?= $estadoStyle ?>"><?= ucfirst($p['estado']) ?></span>
          <span style="font-size:.72rem;color:#94a3b8"><?= date('d/m/Y', strtotime($p['updated_at'])) ?></span>
        </div>

        <div style="display:flex;gap:.4rem;margin-top:.25rem">
          <a href="presupuesto_detalle.php?id=<?= (int)$p['id'] ?>" style="flex:1;text-align:center;padding:.45rem .5rem;background:#eff6ff;color:#1e40af;border-radius:8px;font-size:.78rem;font-weight:700;text-decoration:none">✏ Editar</a>
          <a href="presupuesto_detalle.php?id=<?= (int)$p['id'] ?>&pdf=1" target="_blank" style="flex:1;text-align:center;padding:.45rem .5rem;background:#f0fdf4;color:#15803d;border-radius:8px;font-size:.78rem;font-weight:700;text-decoration:none">📄 PDF</a>
          <form method="POST" onsubmit="return confirm('¿Eliminar este presupuesto?')">
            <input type="hidden" name="action" value="eliminar">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit" style="padding:.45rem .65rem;background:#fef2f2;color:#dc2626;border:none;border-radius:8px;font-size:.78rem;cursor:pointer">🗑</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>
</div>
<?php require_once '../includes/footer.php'; ?>
