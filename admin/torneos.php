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

      <div style="display:flex;justify-content:flex-end;margin-top:.75rem">
        <button type="button" class="trn-pdf-btn" id="trnPdfBtn" onclick="trnPDF()">📄 Descargar PDF</button>
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

function trnEnsureLib(cb){
  if (window.html2pdf) return cb();
  const btn = document.getElementById('trnPdfBtn');
  const s = document.createElement('script');
  s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
  s.onload = cb;
  s.onerror = function(){ if(btn){btn.disabled=false;btn.textContent='📄 Descargar PDF';} alert('No se pudo cargar el generador de PDF. Revisá tu conexión.'); };
  document.head.appendChild(s);
}

function trnPDF(){
  const btn = document.getElementById('trnPdfBtn');
  if (btn){ btn.disabled = true; btn.textContent = '⏳ Generando…'; }
  trnEnsureLib(function(){
    const f = TRN[trnG], iva = 1 + trnV('trnIva')/100;
    const galv = trnV('trnGalvano')*iva*2, med = trnV('trnMedalla')*iva*6, prizes = galv + med;
    const auto = document.getElementById('trnAuto').checked;
    const blocks = auto ? (f.gb + f.fb) : trnV('trnBlocks');
    const court = blocks*trnV('trnBloque'), total = court + prizes, per = total / f.pairs;
    const precio = trnV('trnPrecio'), ingresos = precio*f.pairs, ganancia = ingresos - total;
    const navy = '#0a1f38', gold = '#c9a762';

    const groupsHtml = f.groups.map(L => '<div style="border:1.5px solid #e2e8f0;border-radius:10px;overflow:hidden">'
      + '<div style="background:'+navy+';color:'+gold+';font-family:Anton,sans-serif;font-size:15px;padding:6px 10px">Grupo '+L+'</div>'
      + [1,2,3,4].map(i => '<div style="font-size:12px;color:#334155;padding:6px 10px;border-top:1px solid #f1f5f9;font-weight:600">Pareja '+i+'</div>').join('')
      + '</div>').join('');

    function pm(m){ return '<div style="border:1.5px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:8px;background:#fafbfc">'
      + '<div style="font-size:12px;padding:5px 8px;font-weight:600;color:#334155">'+m[0]+'</div>'
      + '<div style="text-align:center;font-size:9px;color:#94a3b8;background:#f1f5f9;padding:1px">vs</div>'
      + '<div style="font-size:12px;padding:5px 8px;font-weight:600;color:#334155">'+m[1]+'</div></div>'; }
    function pcol(t, ms){ return '<div style="min-width:148px;margin-right:14px">'
      + '<div style="font-size:10px;font-weight:800;color:#3730a3;text-transform:uppercase;letter-spacing:.05em;text-align:center;margin-bottom:6px">'+t+'</div>'
      + ms.map(pm).join('') + '</div>'; }
    let bracket = '';
    if (f.qf.length) bracket += pcol('Cuartos de final', f.qf);
    bracket += pcol('Semifinales', f.sf);
    bracket += pcol('Final', [['Ganador SF1','Ganador SF2']]);
    bracket += pcol('3º y 4º puesto', [['Perdedor SF1','Perdedor SF2']]);

    const money = [['Cuota mínima por pareja',per],['Precio de cobranza por pareja',precio],['Ingresos totales',ingresos],['Costo total del torneo',total]];

    const wrap = document.createElement('div');
    wrap.style.cssText = 'position:absolute;left:-9999px;top:0;width:794px;background:#fff;font-family:Montserrat,Arial,sans-serif;color:#0f172a';
    wrap.innerHTML =
      '<div style="background:'+navy+';padding:22px 28px">'
        + '<div style="color:'+gold+';font-size:12px;letter-spacing:.16em;font-weight:800">ELITE PADEL LEAGUE</div>'
        + '<div style="font-family:Anton,sans-serif;color:#fff;font-size:32px;letter-spacing:.02em;margin-top:2px">TORNEO COPA</div>'
        + '<div style="color:#9fb0c3;font-size:12px;margin-top:6px">'+f.pairs+' parejas · '+f.groups.length+' grupos · '+(f.gm+f.fm)+' partidos · '+f.days+' días · '+new Date().toLocaleDateString('es-CL')+'</div>'
      + '</div>'
      + '<div style="padding:22px 28px">'
        + '<div style="font-size:12px;font-weight:800;color:#64748b;letter-spacing:.06em;text-transform:uppercase;margin-bottom:10px">Fase de grupos</div>'
        + '<div style="display:grid;grid-template-columns:repeat('+f.groups.length+',1fr);gap:10px;margin-bottom:20px">'+groupsHtml+'</div>'
        + '<div style="font-size:12px;font-weight:800;color:#64748b;letter-spacing:.06em;text-transform:uppercase;margin-bottom:10px">Llave final</div>'
        + '<div style="display:flex;align-items:flex-start;margin-bottom:22px">'+bracket+'</div>'
        + '<div style="font-size:12px;font-weight:800;color:#64748b;letter-spacing:.06em;text-transform:uppercase;margin-bottom:8px">Números</div>'
        + money.map(r => '<div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:13px"><span style="color:#64748b">'+r[0]+'</span><span style="font-weight:700">'+trnMoney(r[1])+'</span></div>').join('')
        + '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;margin-top:14px;background:'+navy+';border-radius:12px;color:#fff"><span>Tu ganancia estimada</span><span style="font-family:Anton,sans-serif;font-size:24px;color:'+(ganancia>=0?'#7ee2b8':'#fca5a5')+'">'+trnMoney(ganancia)+'</span></div>'
        + '<div style="font-size:11px;color:#94a3b8;margin-top:14px;line-height:1.5">Costos: canchas '+trnMoney(court)+' ('+blocks+' bloques de 1h30) · galvanos '+trnMoney(galv)+' (Trofeos Premium CR 005) · medallas '+trnMoney(med)+'. Valores con IVA incluido.</div>'
      + '</div>'
      + '<div style="padding:12px 28px;border-top:1px solid #eef2f6;color:#94a3b8;font-size:11px">Elite Padel League · epleague.cl</div>';
    document.body.appendChild(wrap);

    const done = function(){ wrap.remove(); if(btn){btn.disabled=false;btn.textContent='📄 Descargar PDF';} };
    html2pdf().set({
      margin: 0,
      filename: 'torneo-copa-'+f.pairs+'-parejas.pdf',
      image: { type:'jpeg', quality:.95 },
      html2canvas: { scale:2, backgroundColor:'#ffffff' },
      jsPDF: { unit:'mm', format:'a4', orientation:'portrait' }
    }).from(wrap).save().then(done).catch(done);
  });
}
trnSet(2);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
