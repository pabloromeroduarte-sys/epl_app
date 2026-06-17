<?php
// Partial de calendario reutilizable (club y admin).
// Requiere definidas antes de incluir:
//   $cal_liga_ids       : int[]   universo de ligas a considerar
//   $cal_ligas_opciones : array   filas ['id'=>, 'nombre'=>] para el selector
// Lee de la URL: ?mes=YYYY-MM y ?liga=ID (0 = todas)

if (!function_exists('cal_recinto')) {
    function cal_recinto(array $p): string {
        $n = trim((string)($p['recinto_nombre'] ?? ''));
        if ($n === '') return 'Por confirmar';
        $sup = trim((string)($p['recinto_superior_nombre'] ?? ''));
        $abu = trim((string)($p['recinto_abuelo_nombre'] ?? ''));
        if ($abu && $sup) return "$abu $sup - $n";
        if ($sup) return "$sup - $n";
        return $n;
    }
}
if (!function_exists('cal_pareja')) {
    function cal_pareja(?string $n1, ?string $a1, ?string $n2, ?string $a2): string {
        $p1 = trim(($n1 ?? '') . ' ' . ($a1 ?? ''));
        $p2 = trim(($n2 ?? '') . ' ' . ($a2 ?? ''));
        return $p2 ? "$p1 & $p2" : $p1;
    }
}

$cal_liga_ids       = array_values(array_map('intval', $cal_liga_ids ?? []));
$cal_ligas_opciones = $cal_ligas_opciones ?? [];
$cal_editable       = $cal_editable ?? false; // true en admin: muestra botón Editar partido

// Filtro por liga
$liga_sel = isset($_GET['liga']) ? (int)$_GET['liga'] : 0;
if ($liga_sel && !in_array($liga_sel, $cal_liga_ids, true)) $liga_sel = 0;
$ligas_efectivas = $liga_sel ? [$liga_sel] : $cal_liga_ids;

// Mes
$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');
$primero  = DateTime::createFromFormat('Y-m-d', $mes . '-01');
$dias_mes = (int)$primero->format('t');
$dow_prim = (int)$primero->format('N');
$mes_prev = (clone $primero)->modify('-1 month')->format('Y-m');
$mes_next = (clone $primero)->modify('+1 month')->format('Y-m');
$hoy      = date('Y-m-d');
$meses_es = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$mes_label = $meses_es[(int)$primero->format('n')] . ' ' . $primero->format('Y');

$partidos = $ligas_efectivas
    ? epl_partidos_club($ligas_efectivas, $mes . '-01 00:00:00', $primero->format('Y-m-t') . ' 23:59:59')
    : [];

$por_dia = [];
$detalle = [];
foreach ($partidos as $p) {
    if (empty($p['fecha_programada'])) continue;
    $d = date('Y-m-d', strtotime($p['fecha_programada']));
    if ($d === '2026-12-31') continue;
    $por_dia[$d] = ($por_dia[$d] ?? 0) + 1;
    $jugado = $p['estado'] === 'jugado';
    $sets = [];
    for ($s=1;$s<=3;$s++){ $gl=$p["games_s{$s}_local"]; $gv=$p["games_s{$s}_visitante"]; if($gl!==null) $sets[]="$gl-$gv"; }
    $item = [
        'hora'      => date('H:i', strtotime($p['fecha_programada'])),
        'liga'      => $p['liga_nombre'],
        'cancha'    => cal_recinto($p),
        'local'     => $p['local_nombre'],
        'visitante' => $p['visitante_nombre'],
        'jug_local' => cal_pareja($p['jl1_n']??'', $p['jl1_a']??'', $p['jl2_n']??'', $p['jl2_a']??''),
        'jug_vis'   => cal_pareja($p['jv1_n']??'', $p['jv1_a']??'', $p['jv2_n']??'', $p['jv2_a']??''),
        'estado'    => $p['estado'],
        'resultado' => $jugado ? ((int)$p['sets_local'] . '–' . (int)$p['sets_visitante']) : '',
        'sets'      => $sets ? implode(' · ', $sets) : '',
    ];
    if ($cal_editable) {
        $item['edit'] = base64_encode(json_encode($p, JSON_UNESCAPED_UNICODE) ?: '{}');
    }
    $detalle[$d][] = $item;
}
?>
<style>
.cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:6px }
.cal-dow { text-align:center; font-size:.66rem; font-weight:800; text-transform:uppercase; color:var(--gray-400); padding:.3rem 0 }
.cal-cell { min-height:74px; border:1px solid var(--gray-100); border-radius:10px; padding:.4rem; background:var(--white); position:relative }
.cal-empty { border:none; background:transparent }
.cal-num { font-size:.8rem; font-weight:700; color:var(--gray-500) }
.cal-today .cal-num { background:var(--navy); color:#fff; border-radius:50%; width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center }
.cal-has { cursor:pointer; border-color:var(--gold); background:rgba(201,167,98,.08); transition:transform .12s, box-shadow .12s }
.cal-has:hover { transform:translateY(-2px); box-shadow:0 4px 14px rgba(201,167,98,.25) }
.cal-badge { position:absolute; bottom:.4rem; left:.4rem; right:.4rem; background:var(--navy); color:var(--gold); border-radius:6px; font-size:.62rem; font-weight:800; text-align:center; padding:.15rem }
@media(max-width:600px){ .cal-cell{ min-height:58px } .cal-badge{ font-size:.55rem } }
</style>

<?php if (count($cal_ligas_opciones) > 1): ?>
<form method="get" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem">
  <input type="hidden" name="mes" value="<?= epl_h($mes) ?>">
  <label style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--navy)">Filtrar por liga</label>
  <select name="liga" class="form-control" style="width:auto" onchange="this.form.submit()">
    <option value="0">Todas las ligas</option>
    <?php foreach ($cal_ligas_opciones as $l): ?>
      <option value="<?= $l['id'] ?>" <?= $liga_sel==$l['id']?'selected':'' ?>><?= epl_h($l['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
</form>
<?php endif; ?>

<div class="card" style="padding:1rem 1.25rem 1.4rem">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
    <a href="?mes=<?= $mes_prev ?>&liga=<?= $liga_sel ?>" class="btn btn-sm" style="border:1px solid var(--gray-200);color:var(--navy)">‹</a>
    <h2 style="font-family:var(--font-head);font-size:1.15rem;color:var(--navy);margin:0;text-transform:uppercase;letter-spacing:.04em"><?= $mes_label ?></h2>
    <a href="?mes=<?= $mes_next ?>&liga=<?= $liga_sel ?>" class="btn btn-sm" style="border:1px solid var(--gray-200);color:var(--navy)">›</a>
  </div>

  <div class="cal-grid">
    <?php foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d): ?><div class="cal-dow"><?= $d ?></div><?php endforeach; ?>
    <?php for ($i=1;$i<$dow_prim;$i++): ?><div class="cal-cell cal-empty"></div><?php endfor; ?>
    <?php for ($dia=1;$dia<=$dias_mes;$dia++):
      $fecha = sprintf('%s-%02d', $mes, $dia);
      $cnt = $por_dia[$fecha] ?? 0;
      $cls = 'cal-cell' . ($cnt ? ' cal-has' : '') . ($fecha===$hoy ? ' cal-today' : '');
    ?>
      <div class="<?= $cls ?>" <?= $cnt ? 'onclick="abrirDia(\''.$fecha.'\')"' : '' ?>>
        <span class="cal-num"><?= $dia ?></span>
        <?php if ($cnt): ?><span class="cal-badge"><?= $cnt ?> 🎾</span><?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<div id="panelDia" style="margin-top:1.25rem"></div>

<script>
var PARTIDOS = <?= json_encode($detalle, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
var MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
function esc(s){ return (s==null?'':String(s)).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function abrirDia(fecha){
  var lista = PARTIDOS[fecha] || [];
  var partes = fecha.split('-');
  var titulo = parseInt(partes[2],10) + ' de ' + MESES[parseInt(partes[1],10)-1] + ' de ' + partes[0];
  var html = '<div class="card" style="padding:1rem 1.25rem">'
           + '<h3 style="font-family:var(--font-head);font-size:1.05rem;color:var(--navy);text-transform:uppercase;margin:0 0 .85rem">'+titulo+' · '+lista.length+' partido'+(lista.length!==1?'s':'')+'</h3>';
  lista.forEach(function(p){
    var res = p.estado==='jugado'
      ? '<span style="font-family:var(--font-head);font-weight:700;color:#16a34a">'+esc(p.resultado)+'</span>'+(p.sets?'<span style="font-size:.7rem;color:#94a3b8;margin-left:.4rem">'+esc(p.sets)+'</span>':'')
      : '<span style="font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase">'+esc(p.estado)+'</span>';
    html += '<div style="border:1px solid #e2e8f0;border-radius:10px;padding:.7rem .9rem;margin-bottom:.6rem">'
         + '<div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-bottom:.4rem">'
         + '<span style="font-weight:800;color:var(--navy);font-size:.9rem">🕐 '+esc(p.hora)+'</span>'+res+'</div>'
         + '<div style="font-size:.8rem;color:var(--navy);font-weight:700;margin-bottom:.2rem">'+esc(p.local)+' <span style="color:#cbd5e1">vs</span> '+esc(p.visitante)+'</div>'
         + '<div style="font-size:.74rem;color:#64748b;line-height:1.5">👥 '+esc(p.jug_local)+'  <span style="color:#cbd5e1">|</span>  '+esc(p.jug_vis)+'</div>'
         + '<div style="font-size:.74rem;color:#64748b;margin-top:.2rem">📍 '+esc(p.cancha)+'  ·  🏆 '+esc(p.liga)+'</div>'
         + (p.edit ? '<div style="margin-top:.55rem;text-align:right"><button type="button" class="btn btn-sm" style="border:1px solid var(--navy);color:var(--navy);font-size:.7rem;font-weight:700" data-partido="'+p.edit+'" onclick="window.editarPartido(this)">✏ Editar / ver info</button></div>' : '')
         + '</div>';
  });
  html += '</div>';
  var panel = document.getElementById('panelDia');
  panel.innerHTML = html;
  panel.scrollIntoView({behavior:'smooth', block:'nearest'});
}
</script>
