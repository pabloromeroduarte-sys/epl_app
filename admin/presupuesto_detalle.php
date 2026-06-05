<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();

// Asegurar tablas
$db->exec("CREATE TABLE IF NOT EXISTS presupuestos (
    id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(200) NOT NULL,
    tipo VARCHAR(60) NOT NULL DEFAULT 'torneo', referencia VARCHAR(200) NULL,
    notas TEXT NULL, estado VARCHAR(20) NOT NULL DEFAULT 'borrador',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS presupuesto_items (
    id INT AUTO_INCREMENT PRIMARY KEY, presupuesto_id INT NOT NULL,
    tipo ENUM('ingreso','egreso') NOT NULL, categoria VARCHAR(100) NOT NULL DEFAULT '',
    descripcion VARCHAR(200) NOT NULL DEFAULT '',
    cantidad DECIMAL(10,2) NOT NULL DEFAULT 1,
    valor_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    orden INT NOT NULL DEFAULT 0,
    INDEX idx_pres (presupuesto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pid   = (int)($_GET['id'] ?? 0);
$isPdf = isset($_GET['pdf']);
$pres  = null;
$items = [];

if ($pid) {
    $st = $db->prepare("SELECT * FROM presupuestos WHERE id=?");
    $st->execute([$pid]);
    $pres = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pres) { header('Location: presupuestos.php'); exit; }
    $si = $db->prepare("SELECT * FROM presupuesto_items WHERE presupuesto_id=? ORDER BY tipo DESC, orden ASC, id ASC");
    $si->execute([$pid]);
    $items = $si->fetchAll(PDO::FETCH_ASSOC);
}

$err = '';
$ok  = '';

// ── Guardar ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre    = trim($_POST['nombre']     ?? '');
    $tipo      = in_array($_POST['tipo']??'', ['torneo','liga','evento','otro']) ? $_POST['tipo'] : 'torneo';
    $referencia= trim($_POST['referencia'] ?? '');
    $notas     = trim($_POST['notas']      ?? '');
    $estado    = in_array($_POST['estado']??'', ['borrador','cerrado']) ? $_POST['estado'] : 'borrador';

    if (!$nombre) { $err = 'El nombre es obligatorio.'; }
    else {
        // Upsert presupuesto
        if ($pid) {
            $db->prepare("UPDATE presupuestos SET nombre=?,tipo=?,referencia=?,notas=?,estado=?,updated_at=NOW() WHERE id=?")
               ->execute([$nombre,$tipo,$referencia?:null,$notas?:null,$estado,$pid]);
        } else {
            $db->prepare("INSERT INTO presupuestos (nombre,tipo,referencia,notas,estado) VALUES (?,?,?,?,?)")
               ->execute([$nombre,$tipo,$referencia?:null,$notas?:null,$estado]);
            $pid = (int)$db->lastInsertId();
        }

        // Reemplazar items
        $db->prepare("DELETE FROM presupuesto_items WHERE presupuesto_id=?")->execute([$pid]);
        $tipos  = $_POST['item_tipo']  ?? [];
        $cats   = $_POST['item_cat']   ?? [];
        $descs  = $_POST['item_desc']  ?? [];
        $cants  = $_POST['item_cant']  ?? [];
        $vals   = $_POST['item_val']   ?? [];
        $st = $db->prepare("INSERT INTO presupuesto_items (presupuesto_id,tipo,categoria,descripcion,cantidad,valor_unitario,orden) VALUES (?,?,?,?,?,?,?)");
        foreach ($tipos as $i => $t) {
            if (!in_array($t, ['ingreso','egreso'])) continue;
            $desc = trim($descs[$i] ?? '');
            if ($desc === '') continue;
            // Parsear número: soporta "25000", "25000.50", "25.000" (miles CLP), "25.000,50"
            $cant_raw = trim((string)($cants[$i] ?? '1'));
            $val_raw  = trim((string)($vals[$i]  ?? '0'));
            // Si tiene punto de miles X.XXX sin coma → eliminar punto de miles
            $val_raw  = preg_replace('/^(\d{1,3})\.(\d{3})$/', '$1$2', $val_raw);
            $val_raw  = str_replace(',', '.', $val_raw);
            $cant_raw = str_replace(',', '.', $cant_raw);
            $cant = max(0, (float)$cant_raw);
            $val  = max(0, (float)$val_raw);
            $st->execute([$pid, $t, trim($cats[$i]??''), $desc, $cant, $val, $i]);
        }

        epl_redirect_ok('Presupuesto guardado correctamente.', "presupuesto_detalle.php?id={$pid}");
    }
}

// ── PDF: render limpio y salir ────────────────────────────────────────────────
if ($isPdf && $pres) {
    $ingresos = array_filter($items, fn($i) => $i['tipo']==='ingreso');
    $egresos  = array_filter($items, fn($i) => $i['tipo']==='egreso');
    $tot_ing  = array_sum(array_map(fn($i) => $i['cantidad']*$i['valor_unitario'], $ingresos));
    $tot_egr  = array_sum(array_map(fn($i) => $i['cantidad']*$i['valor_unitario'], $egresos));
    $ganancia = $tot_ing - $tot_egr;
    $fmtCLP = fn($n) => '$' . number_format($n,0,',','.');
    include __DIR__ . '/presupuesto_pdf_view.php';
    exit;
}

$page_title = $pres ? 'Editar presupuesto' : 'Nuevo presupuesto';

// Items por defecto para nuevo presupuesto
$itemsJson = json_encode($items ?: [
    ['tipo'=>'ingreso','categoria'=>'Inscripciones','descripcion'=>'Jugadores','cantidad'=>1,'valor_unitario'=>0],
    ['tipo'=>'egreso', 'categoria'=>'Cancha',       'descripcion'=>'Alquiler canchas','cantidad'=>1,'valor_unitario'=>0],
    ['tipo'=>'egreso', 'categoria'=>'Premios',      'descripcion'=>'Premios ganadores','cantidad'=>1,'valor_unitario'=>0],
    ['tipo'=>'egreso', 'categoria'=>'Pelotas',      'descripcion'=>'Pelotas de juego','cantidad'=>1,'valor_unitario'=>0],
], JSON_UNESCAPED_UNICODE);
?>
<?php require_once '../includes/header.php'; ?>
<style>
.pb-wrap { max-width:900px }
.pb-section { background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;overflow:hidden;margin-bottom:1.25rem }
.pb-section-head { display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.1rem;border-bottom:1.5px solid #e2e8f0 }
.pb-section-title { font-weight:800;font-size:.88rem;text-transform:uppercase;letter-spacing:.07em;color:#1C2F48 }
.pb-table { width:100%;border-collapse:collapse }
.pb-table th { padding:.55rem .8rem;font-size:.7rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.06em;background:#f8fafc;border-bottom:1.5px solid #e2e8f0;text-align:left }
.pb-table td { padding:.5rem .65rem;border-bottom:1px solid #f1f5f9;font-size:.85rem;vertical-align:middle }
.pb-table tr:last-child td { border-bottom:none }
.pb-input { width:100%;padding:.38rem .6rem;border:1.5px solid #e2e8f0;border-radius:7px;font-size:.83rem;background:#fff;color:#1C2F48;font-family:inherit }
.pb-input:focus { outline:none;border-color:#6366f1 }
.pb-input.num { text-align:right }
.pb-total-row td { font-weight:800;background:#f8fafc;font-size:.88rem }
.pb-summary { display:grid;grid-template-columns:repeat(3,1fr);gap:1rem }
@media(max-width:600px){ .pb-summary{grid-template-columns:1fr} }
.pb-sum-card { border-radius:12px;padding:1rem 1.25rem;text-align:center }
.pb-sum-num { font-family:var(--font-head);font-size:1.7rem;font-weight:900;line-height:1 }
.pb-sum-lbl { font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-top:.3rem;opacity:.75 }
.btn-add-row { display:inline-flex;align-items:center;gap:.4rem;font-size:.78rem;font-weight:700;padding:.38rem .85rem;border-radius:8px;border:1.5px dashed;cursor:pointer;background:transparent;font-family:inherit }
</style>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="dash-main">
  <div class="pb-wrap">

    <!-- Breadcrumb -->
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap">
      <a href="presupuestos.php" style="color:#64748b;font-size:.83rem;text-decoration:none">← Presupuestos</a>
      <h1 class="dash-title" style="margin:0"><?= $pres ? epl_h($pres['nombre']) : 'Nuevo presupuesto' ?></h1>
      <?php if ($pres): ?>
        <a href="presupuesto_detalle.php?id=<?= $pid ?>&pdf=1" target="_blank"
           style="margin-left:auto;display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1rem;background:#16a34a;color:#fff;border-radius:8px;font-size:.8rem;font-weight:700;text-decoration:none">
          📄 Descargar PDF
        </a>
      <?php endif; ?>
    </div>

    <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>

    <form method="POST" id="pbForm">

    <!-- Cabecera del presupuesto -->
    <div class="pb-section">
      <div class="pb-section-head"><span class="pb-section-title">📋 Datos generales</span></div>
      <div style="padding:1rem 1.1rem;display:grid;grid-template-columns:1fr 1fr;gap:.85rem">
        <div style="grid-column:1/-1">
          <label style="font-size:.78rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.3rem">Nombre del presupuesto *</label>
          <input type="text" name="nombre" class="pb-input" required placeholder="Ej: Torneo Apertura Junio 2026"
            value="<?= epl_h($pres['nombre'] ?? '') ?>">
        </div>
        <div>
          <label style="font-size:.78rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.3rem">Tipo</label>
          <select name="tipo" class="pb-input">
            <?php foreach (['torneo'=>'Torneo','liga'=>'Liga','evento'=>'Evento','otro'=>'Otro'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($pres['tipo']??'torneo')===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:.78rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.3rem">Referencia / Liga</label>
          <input type="text" name="referencia" class="pb-input" placeholder="Ej: EPL Liga 5ta Apertura 26"
            value="<?= epl_h($pres['referencia'] ?? '') ?>">
        </div>
        <div>
          <label style="font-size:.78rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.3rem">Estado</label>
          <select name="estado" class="pb-input">
            <option value="borrador" <?= ($pres['estado']??'borrador')==='borrador'?'selected':'' ?>>Borrador</option>
            <option value="cerrado"  <?= ($pres['estado']??'')==='cerrado'?'selected':'' ?>>Cerrado / Final</option>
          </select>
        </div>
        <div style="grid-column:1/-1">
          <label style="font-size:.78rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.3rem">Notas internas</label>
          <textarea name="notas" class="pb-input" rows="2" placeholder="Observaciones, acuerdos, pendientes..."><?= epl_h($pres['notas'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Resumen dinámico -->
    <div class="pb-summary" id="pbSummary" style="margin-bottom:1.25rem">
      <div class="pb-sum-card" style="background:#f0fdf4;border:1.5px solid #bbf7d0">
        <div class="pb-sum-num" style="color:#16a34a" id="sumIng">$0</div>
        <div class="pb-sum-lbl" style="color:#15803d">Total ingresos</div>
      </div>
      <div class="pb-sum-card" style="background:#fef2f2;border:1.5px solid #fecaca">
        <div class="pb-sum-num" style="color:#dc2626" id="sumEgr">$0</div>
        <div class="pb-sum-lbl" style="color:#b91c1c">Total costos</div>
      </div>
      <div class="pb-sum-card" id="sumGanCard" style="background:#f0fdf4;border:1.5px solid #bbf7d0">
        <div class="pb-sum-num" id="sumGan" style="color:#16a34a">$0</div>
        <div class="pb-sum-lbl" id="sumGanLbl" style="color:#15803d">Ganancia neta</div>
      </div>
    </div>

    <!-- Tabla de ingresos -->
    <div class="pb-section">
      <div class="pb-section-head">
        <span class="pb-section-title" style="color:#16a34a">💚 Ingresos</span>
        <button type="button" class="btn-add-row" style="color:#16a34a;border-color:#86efac" onclick="addRow('ingreso')">+ Agregar ítem</button>
      </div>
      <div style="overflow-x:auto">
        <table class="pb-table">
          <thead><tr>
            <th style="width:22%">Categoría</th>
            <th>Descripción</th>
            <th style="width:10%;text-align:right">Cantidad</th>
            <th style="width:18%;text-align:right">Valor unit.</th>
            <th style="width:18%;text-align:right">Total</th>
            <th style="width:40px"></th>
          </tr></thead>
          <tbody id="tbodyIngreso"></tbody>
          <tfoot><tr class="pb-total-row">
            <td colspan="4" style="text-align:right;padding-right:1rem">Total ingresos</td>
            <td style="text-align:right;color:#16a34a" id="footIng">$0</td>
            <td></td>
          </tr></tfoot>
        </table>
      </div>
    </div>

    <!-- Tabla de egresos -->
    <div class="pb-section">
      <div class="pb-section-head">
        <span class="pb-section-title" style="color:#dc2626">❤️ Costos</span>
        <button type="button" class="btn-add-row" style="color:#dc2626;border-color:#fca5a5" onclick="addRow('egreso')">+ Agregar ítem</button>
      </div>
      <div style="overflow-x:auto">
        <table class="pb-table">
          <thead><tr>
            <th style="width:22%">Categoría</th>
            <th>Descripción</th>
            <th style="width:10%;text-align:right">Cantidad</th>
            <th style="width:18%;text-align:right">Valor unit.</th>
            <th style="width:18%;text-align:right">Total</th>
            <th style="width:40px"></th>
          </tr></thead>
          <tbody id="tbodyEgreso"></tbody>
          <tfoot><tr class="pb-total-row">
            <td colspan="4" style="text-align:right;padding-right:1rem">Total costos</td>
            <td style="text-align:right;color:#dc2626" id="footEgr">$0</td>
            <td></td>
          </tr></tfoot>
        </table>
      </div>
    </div>

    <!-- Resultado final -->
    <div id="resultadoFinal" style="background:linear-gradient(135deg,#1C2F48,#2a4365);border-radius:16px;padding:1.25rem 1.5rem;margin-bottom:1.25rem">
      <div style="font-family:var(--font-head);font-size:.75rem;font-weight:900;text-transform:uppercase;letter-spacing:.12em;color:#C9A762;margin-bottom:.9rem">📊 Resultado final</div>
      <div style="display:grid;gap:.5rem;max-width:360px;margin-left:auto">
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:.88rem;color:rgba(255,255,255,.75)">
          <span>Total ingresos</span>
          <span style="font-weight:700;color:#4ade80" id="rfIng">$0</span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:.88rem;color:rgba(255,255,255,.75)">
          <span>Total costos</span>
          <span style="font-weight:700;color:#f87171" id="rfEgr">$0</span>
        </div>
        <div style="border-top:1px solid rgba(255,255,255,.2);margin:.2rem 0"></div>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span style="font-family:var(--font-head);font-size:.8rem;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:#C9A762" id="rfLabel">Utilidad neta</span>
          <span style="font-family:var(--font-head);font-size:1.6rem;font-weight:900;color:#fff" id="rfUti">$0</span>
        </div>
      </div>
    </div>

    <!-- Botón guardar -->
    <div style="display:flex;gap:.75rem;justify-content:flex-end;padding-top:.5rem">
      <a href="presupuestos.php" class="btn" style="background:#f1f5f9;color:#475569;font-weight:700">Cancelar</a>
      <button type="submit" class="btn btn-primary" style="min-width:180px">💾 Guardar presupuesto</button>
    </div>

    <!-- Hidden inputs generados por JS -->
    <div id="hiddenContainer"></div>

    </form>
  </div>
  </main>
</div>

<script>
const CATS_INGRESO = ['Inscripciones','Sponsors','Otros ingresos'];
const CATS_EGRESO  = ['Cancha','Premios','Pelotas','Árbitro','Marketing','Logística','Otros costos'];

// Items iniciales desde PHP
var INIT_ITEMS = <?= $itemsJson ?>;

function fmtCLP(n) {
  if (isNaN(n)) return '$0';
  return '$' + Math.round(n).toLocaleString('es-CL');
}
function parseCLP(s) {
  var str = String(s).trim();
  // "25.000,50" → tiene ambos → punto=miles, coma=decimal
  if (/\d\.\d{3}/.test(str) && str.indexOf(',') !== -1) {
    return parseFloat(str.replace(/\./g,'').replace(',','.')) || 0;
  }
  // "25.000" → punto como miles (exactamente 3 dígitos tras el punto al final)
  if (/^\d{1,3}(\.\d{3})+$/.test(str)) {
    return parseFloat(str.replace(/\./g,'')) || 0;
  }
  // "25000,50" → coma como decimal
  if (str.indexOf(',') !== -1) {
    return parseFloat(str.replace(',','.')) || 0;
  }
  // "25000" o "25000.50" → punto como decimal (caso normal)
  return parseFloat(str) || 0;
}

function makeRow(tipo, data) {
  data = data || {};
  var tr = document.createElement('tr');
  tr.dataset.tipo = tipo;

  // Categoría
  var cats = tipo === 'ingreso' ? CATS_INGRESO : CATS_EGRESO;
  var catSel = '<select class="pb-input" name="item_cat[]" onchange="recalc()">';
  cats.forEach(function(c) {
    catSel += '<option value="'+c+'"'+(data.categoria===c?' selected':'')+'>'+c+'</option>';
  });
  catSel += '<option value="Otro"'+(cats.indexOf(data.categoria)<0&&data.categoria?' selected':'')+'>Otro</option></select>';

  // Descripción
  var desc = '<input type="text" class="pb-input" name="item_desc[]" placeholder="Descripción" value="'+escHtml(data.descripcion||'')+'" oninput="recalc()" required>';

  // Cantidad
  var cantNum = parseFloat(data.cantidad || 1);
  var cantStr = Number.isInteger(cantNum) ? String(cantNum) : String(cantNum);
  var cant = '<input type="number" class="pb-input num" name="item_cant[]" min="0" step="1" value="'+cantStr+'" oninput="recalc()" style="max-width:70px">';

  // Valor
  // Mostrar valor sin decimales si es entero (evita "25000.00")
  var valNum = parseFloat(data.valor_unitario || 0);
  var valStr = valNum > 0 ? (Number.isInteger(valNum) ? String(valNum) : String(valNum)) : '';
  var val = '<input type="text" class="pb-input num" name="item_val[]" placeholder="0" value="'+valStr+'" oninput="recalc()" style="max-width:110px">';

  // Total
  var tot = '<span class="row-total" style="font-weight:700">$0</span>';

  // Input hidden tipo
  var hidTipo = '<input type="hidden" name="item_tipo[]" value="'+tipo+'">';

  // Botón eliminar
  var del = '<button type="button" onclick="this.closest(\'tr\').remove();recalc()" style="background:#fef2f2;color:#dc2626;border:none;border-radius:6px;width:26px;height:26px;cursor:pointer;font-size:.85rem;display:flex;align-items:center;justify-content:center">×</button>';

  tr.innerHTML = '<td>'+hidTipo+catSel+'</td><td>'+desc+'</td><td style="text-align:right">'+cant+'</td><td style="text-align:right">'+val+'</td><td style="text-align:right">'+tot+'</td><td>'+del+'</td>';
  return tr;
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function addRow(tipo) {
  var tbody = document.getElementById('tbody' + (tipo==='ingreso'?'Ingreso':'Egreso'));
  tbody.appendChild(makeRow(tipo, {}));
  recalc();
}

function recalc() {
  var totIng = 0, totEgr = 0;
  document.querySelectorAll('#tbodyIngreso tr, #tbodyEgreso tr').forEach(function(tr) {
    var tipo = tr.dataset.tipo;
    var cant = parseFloat(tr.querySelector('[name="item_cant[]"]').value) || 0;
    var val  = parseCLP(tr.querySelector('[name="item_val[]"]').value);
    var tot  = cant * val;
    tr.querySelector('.row-total').textContent = fmtCLP(tot);
    if (tipo==='ingreso') totIng += tot;
    else                  totEgr += tot;
  });
  document.getElementById('sumIng').textContent   = fmtCLP(totIng);
  document.getElementById('sumEgr').textContent   = fmtCLP(totEgr);
  document.getElementById('footIng').textContent  = fmtCLP(totIng);
  document.getElementById('footEgr').textContent  = fmtCLP(totEgr);
  var gan = totIng - totEgr;
  var el  = document.getElementById('sumGan');
  var lbl = document.getElementById('sumGanLbl');
  var card= document.getElementById('sumGanCard');
  el.textContent  = fmtCLP(Math.abs(gan));
  if (gan >= 0) {
    el.style.color   = '#16a34a';
    lbl.style.color  = '#15803d';
    lbl.textContent  = 'Ganancia neta';
    card.style.background   = '#f0fdf4';
    card.style.borderColor  = '#bbf7d0';
  } else {
    el.style.color   = '#dc2626';
    lbl.style.color  = '#b91c1c';
    lbl.textContent  = '⚠ Pérdida';
    card.style.background   = '#fef2f2';
    card.style.borderColor  = '#fecaca';
    el.textContent  = '-' + fmtCLP(Math.abs(gan));
  }

  // Resultado final (bloque oscuro)
  var rfI = document.getElementById('rfIng');
  var rfE = document.getElementById('rfEgr');
  var rfU = document.getElementById('rfUti');
  var rfL = document.getElementById('rfLabel');
  if (rfI) rfI.textContent = fmtCLP(totIng);
  if (rfE) rfE.textContent = fmtCLP(totEgr);
  if (rfU) {
    rfU.textContent = (gan < 0 ? '-' : '') + fmtCLP(Math.abs(gan));
    rfU.style.color = gan >= 0 ? '#fff' : '#f87171';
  }
  if (rfL) rfL.textContent = gan >= 0 ? 'Utilidad neta' : '⚠ Pérdida';
}

// Inicializar con items existentes
(function(){
  var tibody = document.getElementById('tbodyIngreso');
  var tebody = document.getElementById('tbodyEgreso');
  INIT_ITEMS.forEach(function(item) {
    var tr = makeRow(item.tipo, item);
    if (item.tipo==='ingreso') tibody.appendChild(tr);
    else                       tebody.appendChild(tr);
  });
  // Si no hay items, poner una fila vacía de cada tipo
  if (!INIT_ITEMS.length) {
    tibody.appendChild(makeRow('ingreso',{}));
    tebody.appendChild(makeRow('egreso', {}));
  }
  recalc();
})();
</script>

<?php require_once '../includes/footer.php'; ?>
