<?php
$page_title = 'Admin — Ligas y Torneos';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db  = epl_db();
epl_ensure_ligas_columnas_mp_precio();
$_flash = epl_flash_get();
$ok     = ($_flash && $_flash['tipo']==='ok') ? $_flash['msg'] : '';
$err    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'crear') {
        $nombre    = trim($_POST['nombre']    ?? '');
        $tipo      = in_array($_POST['tipo']??'liga',['liga','torneo']) ? $_POST['tipo'] : 'liga';
        $formato   = in_array($_POST['formato']??'',['liga_regular','mata_mata','grupos_mata_mata','liga_playoff']) ? $_POST['formato'] : 'liga_regular';
        $cat       = (int)($_POST['categoria'] ?? 0);
        $estado    = in_array($_POST['estado']??'inscripcion',['proximamente','inscripcion','activa','finalizada']) ? $_POST['estado'] : 'inscripcion';
        $recinto_id = (int)($_POST['recinto_id'] ?? 0) ?: null;
        $sede_txt  = trim($_POST['recinto'] ?? '');
        $url_maps  = trim($_POST['url_maps']  ?? '');
        $f_inicio  = trim($_POST['fecha_inicio'] ?? '') ?: null;
        $f_fin     = trim($_POST['fecha_fin']    ?? '') ?: null;
        $i_inicio  = trim($_POST['inscripcion_inicio'] ?? '') ?: null;
        $i_fin     = trim($_POST['inscripcion_fin']    ?? '') ?: null;
        $p1        = max(0,(int)($_POST['puntos_1']      ?? 100));
        $p2        = max(0,(int)($_POST['puntos_2']      ?? 70));
        $p3        = max(0,(int)($_POST['puntos_3']      ?? 50));
        $p4        = max(0,(int)($_POST['puntos_4']      ?? 30));
        $pg        = max(0,(int)($_POST['puntos_grupos'] ?? 10));
        $sexo      = in_array($_POST['sexo']??'', ['masculino','femenino','mixto']) ? $_POST['sexo'] : 'masculino';

        $pErr = '';
        $parsed = epl_liga_precio_desde_post($_POST, $pErr);

        if (!$nombre) {
            $err = 'El nombre es obligatorio.';
        } elseif ($parsed === null) {
            $err = $pErr !== '' ? $pErr : 'Revisa los datos del precio.';
        } else {
            [$precio, $precio_neto_deseado, $mp_comision_pct_db] = $parsed;
            $db->prepare("INSERT INTO ligas
                (nombre,tipo,sexo,formato,categoria,estado,precio,precio_neto_deseado,mp_comision_pct,sede,url_maps,
                 fecha_inicio,fecha_fin,inscripcion_inicio,inscripcion_fin,
                 puntos_1,puntos_2,puntos_3,puntos_4,puntos_grupos,recinto_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$nombre,$tipo,$sexo,$formato,$cat?:null,$estado,$precio,$precio_neto_deseado,$mp_comision_pct_db,
                          $sede_txt ?: null,$url_maps?:null,$f_inicio,$f_fin,$i_inicio,$i_fin,
                          $p1,$p2,$p3,$p4,$pg,$recinto_id]);
            epl_redirect_ok('Competición '.htmlspecialchars($nombre).' creada.');
        }

    } elseif ($action === 'cambiar_estado') {
        $id  = (int)($_POST['id']  ?? 0);
        $est = $_POST['estado'] ?? '';
        if ($id && in_array($est,['proximamente','inscripcion','activa','finalizada'])) {
            $db->prepare("UPDATE ligas SET estado=? WHERE id=?")->execute([$est,$id]);
            epl_redirect_ok('Estado actualizado.');
        }

    } elseif ($action === 'eliminar') {
        $id  = (int)($_POST['id'] ?? 0);
        if ($id) {
            try {
                $db->exec("SET FOREIGN_KEY_CHECKS=0");
                $db->prepare("DELETE FROM clasificacion       WHERE liga_id=?")->execute([$id]);
                $db->prepare("DELETE FROM liga_equipos        WHERE liga_id=?")->execute([$id]);
                $db->prepare("DELETE FROM inscripciones       WHERE liga_id=?")->execute([$id]);
                $db->prepare("DELETE FROM ranking_puntos      WHERE liga_id=?")->execute([$id]);
                $db->prepare("DELETE FROM partidos            WHERE liga_id=?")->execute([$id]);
                $db->prepare("DELETE FROM ligas               WHERE id=?")->execute([$id]);
                $db->exec("SET FOREIGN_KEY_CHECKS=1");
                epl_redirect_ok('Competición eliminada.');
            } catch (Throwable $e) {
                $db->exec("SET FOREIGN_KEY_CHECKS=1");
                $err = 'Error al eliminar: ' . $e->getMessage();
            }
        }
    }
}

try {
    $ligas = $db->query("
        SELECT l.*,
               r.nombre AS recinto_nombre,
               rs.nombre AS recinto_superior_nombre,
               COUNT(DISTINCT le.equipo_id) AS total_equipos,
               COUNT(DISTINCT p.id) AS total_partidos
        FROM ligas l
        LEFT JOIN recintos r ON r.id = l.recinto_id
        LEFT JOIN recintos rs ON rs.id = r.superior_id
        LEFT JOIN liga_equipos le ON le.liga_id = l.id
        LEFT JOIN partidos p ON p.liga_id = l.id
        GROUP BY l.id
        ORDER BY l.id DESC
    ")->fetchAll();
} catch (PDOException $e) {
    // Fallback sin JOIN de recinto si la columna aún no existe
    $ligas = $db->query("
        SELECT l.*,
               NULL AS recinto_nombre,
               NULL AS recinto_superior_nombre,
               COUNT(DISTINCT le.equipo_id) AS total_equipos,
               COUNT(DISTINCT p.id) AS total_partidos
        FROM ligas l
        LEFT JOIN liga_equipos le ON le.liga_id = l.id
        LEFT JOIN partidos p ON p.liga_id = l.id
        GROUP BY l.id
        ORDER BY l.id DESC
    ")->fetchAll();
}

$tipo_label  = ['liga'=>'Liga','torneo'=>'Americano'];
$fmt_label   = [
    'liga_regular'=>'Liga regular',
    'liga_playoff'=>'Liga + Playoff',
    'mata_mata'=>'Llaves',
    'grupos_mata_mata'=>'Fase de grupos + Llaves'
];
$estado_badge= ['proximamente'=>'badge-reprog','inscripcion'=>'badge-pendiente','activa'=>'badge-jugado','finalizada'=>'badge-walkover'];
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header flex justify-between items-center" style="flex-wrap:wrap;gap:.75rem">
      <h1 class="dash-title">Ligas y Torneos</h1>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;width:100%;max-width:max-content">
        <button onclick="document.getElementById('modalCrear').style.display='flex'" class="btn btn-primary btn-sm" style="flex:1;text-align:center">+ Nueva competición</button>
      </div>
    </div>

    <?php if ($ok): ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>

    <div class="card">
      <div style="overflow-x:auto">
        <table class="admin-table-cards" style="width:100%;border-collapse:collapse;font-size:.84rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Competición</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Tipo</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Recinto</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Fechas</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Equipos</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Estado</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ligas as $l): ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td data-label="Competición" style="padding:.7rem 1rem">
                <div style="font-weight:700;color:var(--navy)"><?= epl_h($l['nombre']) ?></div>
                <div style="font-size:.7rem;color:var(--gray-400);margin-top:.15rem"><?= $fmt_label[$l['formato']] ?? '' ?> · <?= $l['categoria'] ? $l['categoria'].'ª cat.' : '' ?></div>
              </td>
              <td data-label="Tipo" class="hide-mobile" style="padding:.7rem 1rem;text-align:center">
                <span class="badge <?= $l['tipo']==='liga'?'badge-pendiente':'badge-reprog' ?>">
                  <?= $tipo_label[$l['tipo']] ?>
                </span>
              </td>
              <td data-label="Recinto" class="hide-mobile" style="padding:.7rem 1rem;font-size:.8rem;color:var(--gray-600)">
                <?php if ($l['recinto_nombre']): ?>
                  <div style="font-weight:600;color:var(--navy)"><?= epl_h($l['recinto_nombre']) ?></div>
                  <?php if ($l['recinto_superior_nombre']): ?>
                    <div style="font-size:.7rem;color:var(--gray-400)"><?= epl_h($l['recinto_superior_nombre']) ?></div>
                  <?php endif; ?>
                <?php elseif ($l['sede']): ?>
                  <?= epl_h($l['sede']) ?>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td data-label="Fechas" class="hide-mobile" style="padding:.7rem 1rem;font-size:.78rem;color:var(--gray-600);white-space:nowrap">
                <?= $l['fecha_inicio'] ? date('d/m/Y', strtotime($l['fecha_inicio'])) : '—' ?>
                <?php if ($l['fecha_fin']): ?>
                  <br><?= date('d/m/Y', strtotime($l['fecha_fin'])) ?>
                <?php endif; ?>
              </td>
              <td data-label="Equipos" class="hide-mobile" style="padding:.7rem 1rem;text-align:center;font-weight:700;color:var(--navy)">
                <?= $l['total_equipos'] ?>
                <div style="font-size:.7rem;font-weight:400;color:var(--gray-400)"><?= $l['total_partidos'] ?> partidos</div>
              </td>
              <td data-label="Estado" style="padding:.7rem 1rem;text-align:center">
                <?php
                $est_auto = epl_get_liga_status($l);
                $labels = [
                  'proximamente'=>'Próximamente',
                  'inscripcion'=>'Inscripción',
                  'activa'=>'Activa',
                  'finalizada'=>'Finalizada'
                ];
                ?>
                <span class="badge <?= $estado_badge[$est_auto] ?? '' ?>" title="Calculado automáticamente">
                  <?= $labels[$est_auto] ?? $est_auto ?>
                </span>
              </td>
              <td data-label="Acciones" style="padding:.7rem 1rem;text-align:center;display:flex;gap:.4rem;justify-content:center;flex-wrap:wrap">
                <a href="liga_detalle.php?id=<?= $l['id'] ?>" class="btn btn-sm" style="background:var(--navy);color:#fff;font-size:.72rem">
                  Gestionar →
                </a>
                <form method="post" onsubmit="return confirm('¿Eliminar «<?= epl_h(addslashes($l['nombre'])) ?>» y todos sus datos? Esta acción no se puede deshacer.')">
                  <input type="hidden" name="action" value="eliminar">
                  <input type="hidden" name="id" value="<?= $l['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:#dc2626;color:#fff;font-size:.72rem;border:none;cursor:pointer">
                    Eliminar
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($ligas)): ?>
              <tr><td colspan="7" style="padding:2rem;text-align:center;color:var(--gray-400)">No hay competiciones creadas aún.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Modal crear competición -->
<div id="modalCrear" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:560px;max-height:92vh;overflow-y:auto">
    <div class="card-head" style="position:sticky;top:0;background:#fff;z-index:1">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Nueva Competición</h3>
      <button onclick="document.getElementById('modalCrear').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="crear">

        <div class="form-group">
          <label class="form-label">Nombre *</label>
          <input type="text" name="nombre" class="form-control" required placeholder="Liga EPL 5ta · Apertura 2026">
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Tipo</label>
            <select name="tipo" id="crearTipo" class="form-control" onchange="toggleFormato()">
              <option value="liga">Liga</option>
              <option value="torneo">Americano</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Sexo</label>
            <select name="sexo" class="form-control">
              <option value="masculino">Masculino</option>
              <option value="femenino">Femenino</option>
              <option value="mixto">Mixto</option>
            </select>
          </div>
        </div>
        
        <div class="grid-2">
          <div class="form-group" id="wrapFormato">
            <label class="form-label">Formato</label>
            <select name="formato" id="crearFormato" class="form-control">
              <option value="liga_regular">Liga regular</option>
              <option value="liga_playoff">Liga + Playoff</option>
              <option value="mata_mata" style="display:none">Llaves</option>
              <option value="grupos_mata_mata" style="display:none">Fase de grupos + Llaves</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Categoría</label>
            <select name="categoria" class="form-control">
              <?php for ($n=1;$n<=8;$n++): ?>
                <option value="<?= $n ?>" <?= $n===5?'selected':'' ?>><?= $n ?>ª categoría</option>
              <?php endfor; ?>
            </select>
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Fecha inicio liga</label>
            <input type="date" name="fecha_inicio" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Fecha fin liga</label>
            <input type="date" name="fecha_fin" class="form-control">
          </div>
        </div>

        <p style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--navy);margin:.5rem 0 .75rem">Inscripciones (Automático)</p>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Apertura inscrip.</label>
            <input type="date" name="inscripcion_inicio" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Cierre inscrip.</label>
            <input type="date" name="inscripcion_fin" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Recinto / Club</label>
          <select name="recinto_id" class="form-control">
            <option value="">— Selecciona un recinto —</option>
            <?php 
            // Fetch root clubs and their direct children (sedes)
            $stR = $db->query("SELECT id, nombre, superior_id FROM recintos WHERE superior_id IS NULL OR superior_id IN (SELECT id FROM recintos WHERE superior_id IS NULL) ORDER BY COALESCE(superior_id, id), superior_id IS NOT NULL, nombre");
            $all_r = $stR->fetchAll();
            foreach ($all_r as $r): 
              $prefix = $r['superior_id'] ? '   📍 ' : '🏛 ';
            ?>
              <option value="<?= $r['id'] ?>"><?= $prefix . epl_h($r['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="recinto" value=""> <!-- Keep for compat if needed, but we'll use recinto_id -->
        </div>

        <div class="form-group">
          <label class="form-label">URL Google Maps</label>
          <input type="url" name="url_maps" class="form-control" placeholder="https://maps.google.com/...">
        </div>

        <div class="form-group">
          <label class="form-label">Estado inicial</label>
          <select name="estado" class="form-control">
            <option value="proximamente">Próximamente</option>
            <option value="inscripcion" selected>Inscripción abierta</option>
            <option value="activa">Activa</option>
          </select>
        </div>

        <?php
          $pct_modal     = json_encode(epl_mp_comision_porcentaje_defecto());
          $mp_step_modal = (int) max(1, epl_mp_redondeo_escalon_clp());
        ?>
        <p style="font-size:.72rem;color:var(--gray-600);margin:-.25rem 0 .5rem;line-height:1.45">
          Mercado Pago: comisión global <strong><?= epl_h((string) epl_mp_comision_porcentaje_defecto()) ?>%</strong> y redondeo <strong>↑ a $<?= number_format($mp_step_modal, 0, ',', '.') ?></strong> (Configuración → Integraciones).
        </p>
        <div class="form-group" style="margin-bottom:.35rem">
          <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.84rem;font-weight:600;color:var(--navy)">
            <input type="checkbox" name="precio_usar_liquido_mp" id="crear_precioLiquidoMp" value="1" style="width:18px;height:18px">
            Calcular cobro desde líquido (comisión MP)
          </label>
        </div>
        <div id="crear_bloqueLiquidoMp" style="display:none;margin-bottom:.5rem">
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Comisión MP esta liga (%)</label>
              <input type="number" step="0.01" min="0" max="99" name="mp_comision_pct" id="crear_mp_comision_pct" class="form-control" placeholder="Vacío → global <?= epl_h((string) epl_mp_comision_porcentaje_defecto()) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Líquido deseado (CLP)</label>
              <input type="number" step="1" min="1" name="precio_neto_deseado" id="crear_precio_neto_deseado" class="form-control" placeholder="Ej: 45000">
            </div>
          </div>
          <div style="font-size:.78rem;background:#f8fafc;border:1px solid var(--gray-200);padding:.55rem;border-radius:8px">
            Cobro al jugador (redondeo superior): $<span id="crear_previewBrutoMp">—</span> CLP</div>
        </div>
        <div id="crear_wrapPrecioFijo" class="form-group">
          <label class="form-label">Precio por jugador (CLP) — fijo</label>
          <input type="number" name="precio" id="crear_precio_manual" class="form-control" min="0" placeholder="45000 o 0 gratis">
        </div>
        <script>
        (function(){
          var chk = document.getElementById('crear_precioLiquidoMp');
          var bloq = document.getElementById('crear_bloqueLiquidoMp');
          var wrapF = document.getElementById('crear_wrapPrecioFijo');
          var pctE = document.getElementById('crear_mp_comision_pct');
          var netoE = document.getElementById('crear_precio_neto_deseado');
          var out = document.getElementById('crear_previewBrutoMp');
          var defP = <?= $pct_modal ?>;
          var stepMp = <?= json_encode($mp_step_modal) ?>;
          function pPct(){ var r=(pctE.value||'').replace(',','.'); if(r===''||isNaN(r)) return defP; var x=parseFloat(r); return (x>0&&x<100)?x:defP }
          function br(){ var n=parseFloat(netoE.value)||0,p=pPct(); if(n<=0||p>=100) return 0; var raw=n/(1-p/100); return Math.ceil(raw/stepMp)*stepMp; }
          function rf(){ out.textContent = br() ? br().toLocaleString('es-CL') : '—'; }
          function tg(){ var on=chk.checked; bloq.style.display = on?'block':'none'; wrapF.style.display = on?'none':'block'; rf(); }
          chk.addEventListener('change', tg);
          pctE.addEventListener('input', rf);
          netoE.addEventListener('input', rf);
          tg();
        })();
        </script>

        <!-- Puntos de ranking -->
        <p style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--navy);margin:1rem 0 .75rem;border-top:1px solid var(--gray-200);padding-top:1rem">
          Puntos de ranking (ventana 52 semanas)
        </p>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">🥇 1° lugar</label>
            <input type="number" name="puntos_1" class="form-control" min="0" value="100">
          </div>
          <div class="form-group">
            <label class="form-label">🥈 2° lugar</label>
            <input type="number" name="puntos_2" class="form-control" min="0" value="70">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">🥉 3° lugar</label>
            <input type="number" name="puntos_3" class="form-control" min="0" value="50">
          </div>
          <div class="form-group">
            <label class="form-label">4° lugar</label>
            <input type="number" name="puntos_4" class="form-control" min="0" value="30">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Participación (5° en adelante)</label>
          <input type="number" name="puntos_grupos" class="form-control" min="0" value="10">
          <span class="form-hint">Puntos por participar sin llegar al top 4</span>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem">Crear competición</button>
      </form>
    </div>
  </div>
</div>

<script>
function toggleFormato() {
  const tipo = document.getElementById('crearTipo').value;
  const fmt  = document.getElementById('crearFormato');
  const opts = fmt.options;
  
  if (tipo === 'liga') {
    opts[0].style.display = ''; // liga_regular
    opts[1].style.display = ''; // liga_playoff
    opts[2].style.display = 'none'; // mata_mata
    opts[3].style.display = 'none'; // grupos_mata_mata
    if (fmt.value === 'mata_mata' || fmt.value === 'grupos_mata_mata') fmt.value = 'liga_regular';
  } else {
    opts[0].style.display = 'none';
    opts[1].style.display = 'none';
    opts[2].style.display = ''; // mata_mata
    opts[3].style.display = ''; // grupos_mata_mata
    if (fmt.value === 'liga_regular' || fmt.value === 'liga_playoff') fmt.value = 'mata_mata';
  }
}
</script>

<?php require_once '../includes/footer.php'; ?>
