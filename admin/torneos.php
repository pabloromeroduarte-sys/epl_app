<?php
declare(strict_types=1);
$page_title = 'Admin — Torneo Copa';
$page_css   = 'torneos'; // assets/css/torneos.css — se carga desde el <head>
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
epl_require_admin();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php require __DIR__ . '/partials/sidebar.php'; ?>
  <main class="dash-main torneo-main">

    <section class="torneo-hero">
      <div class="eyebrow">Nuevo formato</div>
      <h1>Torneo Copa</h1>
      <p>Fase de grupos + eliminación. Elegí el tamaño, poné tu precio de cobranza y mirá cuánto ganás. Arma el esquema para presentar.</p>
    </section>

    <div class="trn-wrap">
      <div class="trn-label">Cantidad de grupos (parejas)</div>
      <div class="trn-fmt">
        <button type="button" class="trn-fmt-btn" data-g="2" onclick="trnSet(2)">2 grupos · 8 parejas</button>
        <button type="button" class="trn-fmt-btn" data-g="3" onclick="trnSet(3)">3 grupos · 12</button>
        <button type="button" class="trn-fmt-btn" data-g="4" onclick="trnSet(4)">4 grupos · 16</button>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:.6rem;flex-wrap:wrap;margin-top:.75rem">
        <button type="button" class="trn-pdf-btn" onclick="trnPDF(false)">📄 Descargar PDF</button>
        <button type="button" class="trn-pdf-btn trn-pdf-btn2" onclick="trnPDF(true)">🔒 Descarga interna</button>
      </div>

      <!-- ESQUEMA VISUAL (para presentar) -->
      <section class="trn-scheme">
        <div class="trn-scheme-h">Fase de grupos</div>
        <div class="trn-groups" id="trnGroups"></div>
        <div class="trn-scheme-h">Llave final</div>
        <div class="trn-bracket-wrap"><div class="trn-bracket" id="trnBracket"></div></div>
        <div class="trn-third" id="trnThird"></div>
        <div class="trn-rule-line" id="trnRule"></div>
      </section>

      <!-- PRECIO DE COBRANZA + CUOTA MÍNIMA -->
      <div class="trn-money-grid">
        <div class="trn-mcard">
          <small>Cuota mínima por pareja (financia todo)</small>
          <div class="trn-min" id="trnPerPair">—</div>
        </div>
        <div class="trn-mcard">
          <small>Precio de cobranza por pareja</small>
          <input id="trnPrecio" type="number" step="1000" value="30000" oninput="trnRender()">
        </div>
      </div>

      <!-- GANANCIA -->
      <div class="trn-profit">
        <div class="p-item"><small>Ingresos (precio × parejas)</small><b id="trnIngresos">—</b></div>
        <div class="p-item"><small>Costo total del torneo</small><b id="trnCostoTot">—</b></div>
        <div class="p-item p-gan"><small>Tu ganancia</small><b id="trnGanancia">—</b></div>
      </div>

      <div class="trn-metrics" id="trnMetrics"></div>

      <div class="trn-card"><div id="trnBreakdown"></div></div>

      <div class="trn-label">Ajustar costos · todo en negro, el IVA no se recupera</div>
      <div class="trn-costs">
        <div><label>Bloque cancha (1h30 = 3 partidos)</label><input id="trnBloque" type="number" step="500" value="26000" oninput="trnRender()"></div>
        <div><label>Galvano neto c/u</label><input id="trnGalvano" type="number" step="100" value="13900" oninput="trnRender()"></div>
        <div><label>Medalla neta c/u</label><input id="trnMedalla" type="number" step="100" value="1500" oninput="trnRender()"></div>
        <div><label>IVA %</label><input id="trnIva" type="number" step="1" value="19" oninput="trnRender()"></div>
        <div><label>Bloques de cancha (total)</label><input id="trnBlocks" type="number" step="1" value="6" oninput="trnRender()"></div>
      </div>
      <label class="trn-auto"><input type="checkbox" id="trnAuto" checked onchange="trnRender()"> Calcular bloques automático (según el formato)</label>
      <p class="trn-fine">Galvano: Trofeos Premium, modelo CR&nbsp;005 · 2 unidades. Medallas: 6 (1º a 3º puesto). Destildá "automático" para poner vos cuántos bloques de cancha se reservan.</p>
    </div>

  </main>
</div>

<script>
const TRN = {
  2: { pairs:8,  gm:12, gb:4, fm:4, fb:2, q:4, days:2, rule:'Pasan los 2 mejores de cada grupo a semifinales.', groups:['A','B'],
       qf:[], sf:[['1º A','2º B'],['1º B','2º A']] },
  3: { pairs:12, gm:18, gb:6, fm:4, fb:2, q:4, days:2, rule:'Clasifican los 3 ganadores de grupo + el mejor 2º.', groups:['A','B','C'],
       qf:[], sf:[['1º A','Mejor 2º'],['1º B','1º C']] },
  4: { pairs:16, gm:24, gb:8, fm:8, fb:4, q:8, days:3, rule:'Pasan los 2 mejores de cada grupo a cuartos de final.', groups:['A','B','C','D'],
       qf:[['1º A','2º B'],['1º C','2º D'],['1º B','2º A'],['1º D','2º C']], sf:[['Ganador C1','Ganador C2'],['Ganador C3','Ganador C4']] }
};
let trnG = 2;
function trnV(id){ return parseFloat(document.getElementById(id).value) || 0; }
function trnMoney(n){ return '$' + Math.round(n).toLocaleString('es-CL'); }
function trnSet(n){ trnG = n; document.querySelectorAll('[data-g]').forEach(b => b.classList.toggle('active', +b.dataset.g === n)); trnRender(); }

function trnMatch(m){
  return '<div class="trn-match"><div class="trn-slot">'+m[0]+'</div><div class="trn-vs">vs</div><div class="trn-slot">'+m[1]+'</div></div>';
}
function trnCol(title, matches){
  return '<div class="trn-col"><div class="trn-col-h">'+title+'</div>'+matches.map(trnMatch).join('')+'</div>';
}
function trnScheme(f){
  document.getElementById('trnGroups').innerHTML = f.groups.map(L =>
    '<div class="trn-group"><div class="trn-group-h">Grupo '+L+'</div>'+[1,2,3,4].map(i=>'<div class="trn-slot">Pareja '+i+'</div>').join('')+'</div>'
  ).join('');
  let cols = '';
  if (f.qf.length) cols += trnCol('Cuartos de final', f.qf);
  cols += trnCol('Semifinales', f.sf);
  cols += trnCol('Final', [['Ganador SF1','Ganador SF2']]);
  document.getElementById('trnBracket').innerHTML = cols;
  document.getElementById('trnThird').innerHTML = trnCol('3º y 4º puesto', [['Perdedor SF1','Perdedor SF2']]);
  document.getElementById('trnRule').innerHTML = '<b>Regla:</b> ' + f.rule + ' Se juega en ' + f.days + ' días.';
}

function trnRender(){
  const f = TRN[trnG], iva = 1 + trnV('trnIva')/100;
  const galv = trnV('trnGalvano')*iva*2, med = trnV('trnMedalla')*iva*6, prizes = galv + med;
  const auto = document.getElementById('trnAuto').checked;
  const blocksInput = document.getElementById('trnBlocks');
  let blocks;
  if (auto) { blocks = f.gb + f.fb; blocksInput.value = blocks; blocksInput.disabled = true; }
  else { blocksInput.disabled = false; blocks = trnV('trnBlocks'); }
  const court = blocks * trnV('trnBloque'), total = court + prizes, per = total / f.pairs;
  const precio = trnV('trnPrecio'), ingresos = precio * f.pairs, ganancia = ingresos - total;

  document.getElementById('trnPerPair').textContent = trnMoney(per);
  document.getElementById('trnIngresos').textContent = trnMoney(ingresos);
  document.getElementById('trnCostoTot').textContent = trnMoney(total);
  const gEl = document.getElementById('trnGanancia');
  gEl.textContent = trnMoney(ganancia);
  gEl.style.color = ganancia >= 0 ? '#7ee2b8' : '#fca5a5';

  document.getElementById('trnMetrics').innerHTML = [['Parejas',f.pairs],['Partidos',f.gm+f.fm],['Bloques cancha',blocks],['Días',f.days]]
    .map(m => '<div class="trn-metric"><small>'+m[0]+'</small><b>'+m[1]+'</b></div>').join('');
  document.getElementById('trnBreakdown').innerHTML = [['Canchas · '+blocks+' bloques',court],['Galvanos · 2',galv],['Medallas · 6',med],['Costo total',total]]
    .map((r,i) => '<div class="trn-row'+(i===3?' total':'')+'"><span>'+r[0]+'</span><span>'+trnMoney(r[1])+'</span></div>').join('');

  trnScheme(f);
}

function trnPDF(interna){
  const f = TRN[trnG], iva = 1 + trnV('trnIva')/100, ivaPct = trnV('trnIva');
  const galv = trnV('trnGalvano')*iva*2, med = trnV('trnMedalla')*iva*6, prizes = galv + med;
  const auto = document.getElementById('trnAuto').checked;
  const blocks = auto ? (f.gb + f.fb) : trnV('trnBlocks');
  const court = blocks*trnV('trnBloque'), total = court + prizes, per = total / f.pairs;
  const precio = trnV('trnPrecio'), ingresos = precio*f.pairs, ganancia = ingresos - total;
  const navy = '#0a1f38', gold = '#c9a762';

  const groupsHtml = f.groups.map(L => '<div class="g"><div class="gh">Grupo '+L+'</div>'
    + [1,2,3,4].map(i => '<div class="gs">Pareja '+i+'</div>').join('') + '</div>').join('');
  function pm(m){ return '<div class="m"><div class="ms">'+m[0]+'</div><div class="mv">vs</div><div class="ms">'+m[1]+'</div></div>'; }
  function pcol(t, ms){ return '<div class="c"><div class="ch">'+t+'</div>'+ms.map(pm).join('')+'</div>'; }
  let bracket = '';
  if (f.qf.length) bracket += pcol('Cuartos de final', f.qf);
  bracket += pcol('Semifinales', f.sf);
  bracket += pcol('Final', [['Ganador SF1','Ganador SF2']]);
  bracket += pcol('3º y 4º puesto', [['Perdedor SF1','Perdedor SF2']]);
  const money = [['Cuota mínima por pareja',per],['Precio de cobranza por pareja',precio],['Ingresos totales',ingresos],['Costo total del torneo',total]];
  const rows = money.map(r => '<div class="r"><span>'+r[0]+'</span><b>'+trnMoney(r[1])+'</b></div>').join('');

  let detalle = '';
  if (interna){
    const galvNetoT = trnV('trnGalvano')*2, medNetoT = trnV('trnMedalla')*6;
    const galvIva = galvNetoT*(iva-1), medIva = medNetoT*(iva-1), ivaTot = galvIva + medIva;
    const netoTot = court + galvNetoT + medNetoT;
    function drow(l, neto, ivaAmt, tot){ return '<tr><td>'+l+'</td><td>'+trnMoney(neto)+'</td><td>'+(ivaAmt>0?trnMoney(ivaAmt):'—')+'</td><td>'+trnMoney(tot)+'</td></tr>'; }
    detalle = '<div class="sec">Detalle de gastos · uso interno</div>'
      + '<table class="tbl"><thead><tr><th>Ítem</th><th>Neto</th><th>IVA '+ivaPct+'%</th><th>Total</th></tr></thead><tbody>'
      + drow('Canchas · '+blocks+' bloques de 1h30 (× '+trnMoney(trnV('trnBloque'))+')', court, 0, court)
      + drow('Galvanos CR 005 · 2 unidades', galvNetoT, galvIva, galv)
      + drow('Medallas · 6 unidades (1º a 3º)', medNetoT, medIva, med)
      + '</tbody><tfoot><tr><td>Costo total</td><td>'+trnMoney(netoTot)+'</td><td>'+trnMoney(ivaTot)+'</td><td>'+trnMoney(total)+'</td></tr></tfoot></table>'
      + '<div class="mini">Ingresos '+trnMoney(ingresos)+' · Ganancia '+trnMoney(ganancia)+' · Margen por pareja '+trnMoney(ganancia/f.pairs)+' · IVA que no se recupera (en negro): '+trnMoney(ivaTot)+'</div>';
  }

  const titulo = interna ? 'Torneo Copa INTERNO · '+f.pairs+' parejas' : 'Torneo Copa · '+f.pairs+' parejas';
  const marca = interna ? ' · <b style="color:#fca5a5">USO INTERNO</b>' : '';

  const doc = '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>'+titulo+'</title>'
    + '<link href="https://fonts.googleapis.com/css2?family=Anton&family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">'
    + '<style>'
    + '@page{size:A4;margin:12mm}*{margin:0;box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}'
    + 'body{font-family:Montserrat,Arial,sans-serif;color:#0f172a}'
    + '.hd{background:'+navy+';color:#fff;padding:18px 22px;border-radius:12px}'
    + '.eb{color:'+gold+';font-size:11px;letter-spacing:.16em;font-weight:800}'
    + '.hd h1{font-family:Anton,sans-serif;font-size:30px;font-weight:400;letter-spacing:.02em;margin:2px 0 4px}'
    + '.sub{color:#9fb0c3;font-size:11px}'
    + '.sec{font-size:11px;font-weight:800;color:#64748b;letter-spacing:.06em;text-transform:uppercase;margin:18px 0 8px}'
    + '.groups{display:flex;gap:8px}.g{flex:1;border:1.5px solid #e2e8f0;border-radius:8px;overflow:hidden;break-inside:avoid}'
    + '.gh{background:'+navy+';color:'+gold+';font-family:Anton,sans-serif;font-size:14px;padding:5px 8px}'
    + '.gs{font-size:11px;padding:5px 8px;border-top:1px solid #f1f5f9;font-weight:600}'
    + '.br{display:flex;gap:12px;align-items:flex-start}.c{flex:1}'
    + '.ch{font-size:9px;font-weight:800;color:#3730a3;text-transform:uppercase;text-align:center;margin-bottom:6px}'
    + '.m{border:1.5px solid #e2e8f0;border-radius:6px;overflow:hidden;margin-bottom:7px;break-inside:avoid}'
    + '.ms{font-size:11px;padding:4px 7px;font-weight:600}.ms+.ms{border-top:1px solid #eef2f6}'
    + '.mv{text-align:center;font-size:8px;color:#94a3b8;background:#f1f5f9}'
    + '.r{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:12px}.r span{color:#64748b}'
    + '.gan{display:flex;justify-content:space-between;align-items:center;padding:11px 16px;margin-top:12px;background:'+navy+';border-radius:10px;color:#fff}'
    + '.gan b{font-family:Anton,sans-serif;font-size:22px;color:'+(ganancia>=0?'#0fae74':'#dc2626')+'}'
    + '.tbl{width:100%;border-collapse:collapse;font-size:11px}'
    + '.tbl th{text-align:right;padding:6px 8px;background:#f1f5f9;color:#475569;font-weight:800;font-size:10px}'
    + '.tbl th:first-child{text-align:left}'
    + '.tbl td{padding:6px 8px;border-bottom:1px solid #f1f5f9;text-align:right}'
    + '.tbl td:first-child{text-align:left;color:#334155}'
    + '.tbl tfoot td{font-weight:800;border-top:1.5px solid #e2e8f0;border-bottom:none;color:'+navy+'}'
    + '.mini{font-size:11px;color:#475569;margin-top:8px;font-weight:600;line-height:1.5}'
    + '.ft{margin-top:14px;font-size:10px;color:#94a3b8;line-height:1.5}'
    + '</style></head><body>'
    + '<div class="hd"><div class="eb">ELITE PADEL LEAGUE</div><h1>TORNEO COPA</h1><div class="sub">'+f.pairs+' parejas · '+f.groups.length+' grupos · '+(f.gm+f.fm)+' partidos · '+f.days+' días · '+new Date().toLocaleDateString('es-CL')+marca+'</div></div>'
    + '<div class="sec">Fase de grupos</div><div class="groups">'+groupsHtml+'</div>'
    + '<div class="sec">Llave final</div><div class="br">'+bracket+'</div>'
    + '<div class="sec">Números</div>'+rows
    + '<div class="gan"><span>Tu ganancia estimada</span><b>'+trnMoney(ganancia)+'</b></div>'
    + detalle
    + '<div class="ft">Galvanos: Trofeos Premium, modelo CR 005. · epleague.cl</div>'
    + '<scr'+'ipt>window.onload=function(){setTimeout(function(){window.print();},450);};<\/scr'+'ipt>'
    + '</body></html>';

  const w = window.open('', '_blank');
  if (!w){ alert('Permití las ventanas emergentes para generar el PDF.'); return; }
  w.document.open(); w.document.write(doc); w.document.close();
}
trnSet(2);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
