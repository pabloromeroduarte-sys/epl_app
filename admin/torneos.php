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
      <p>Fase de grupos + eliminación. Elegí cuántos grupos y ajustá los costos: el sistema calcula canchas, premios y la cuota mínima por pareja para financiar el torneo.</p>
    </section>

    <div class="trn-wrap">
      <div class="trn-label">Cantidad de grupos (parejas)</div>
      <div class="trn-fmt">
        <button type="button" class="trn-fmt-btn" data-g="2" onclick="trnSet(2)">2 grupos · 8 parejas</button>
        <button type="button" class="trn-fmt-btn" data-g="3" onclick="trnSet(3)">3 grupos · 12</button>
        <button type="button" class="trn-fmt-btn" data-g="4" onclick="trnSet(4)">4 grupos · 16</button>
      </div>

      <div class="trn-result">
        <span>Cuota mínima por pareja</span>
        <b id="trnPerPair">—</b>
      </div>

      <div class="trn-metrics" id="trnMetrics"></div>

      <div class="trn-card"><div id="trnBreakdown"></div></div>

      <div class="trn-card">
        <div class="trn-rule" id="trnRule"></div>
        <div class="trn-rounds" id="trnRounds"></div>
      </div>

      <div class="trn-label">Ajustar costos · todo en negro, el IVA no se recupera</div>
      <div class="trn-costs">
        <div><label>Bloque cancha (1h30 = 3 partidos)</label><input id="trnBloque" type="number" step="500" value="26000" oninput="trnRender()"></div>
        <div><label>Galvano neto c/u</label><input id="trnGalvano" type="number" step="100" value="13900" oninput="trnRender()"></div>
        <div><label>Medalla neta c/u</label><input id="trnMedalla" type="number" step="100" value="1500" oninput="trnRender()"></div>
        <div><label>IVA %</label><input id="trnIva" type="number" step="1" value="19" oninput="trnRender()"></div>
      </div>
      <p class="trn-fine">Galvano: Trofeos Premium, modelo CR&nbsp;005 · 2 unidades. Medallas: 6 (1º a 3º puesto). Próximamente: inscribir las parejas reales y el avance del cuadro.</p>
    </div>

  </main>
</div>

<script>
const TRN = {
  2: { pairs:8,  gm:12, gb:4, fm:4, fb:2, q:4, days:2, rule:'Pasan los 2 mejores de cada grupo', rounds:['2 semifinales','Final','3º y 4º puesto'] },
  3: { pairs:12, gm:18, gb:6, fm:4, fb:2, q:4, days:2, rule:'3 ganadores de grupo + el mejor 2º', rounds:['2 semifinales','Final','3º y 4º puesto'] },
  4: { pairs:16, gm:24, gb:8, fm:8, fb:4, q:8, days:3, rule:'Pasan los 2 mejores de cada grupo', rounds:['4 cuartos de final','2 semifinales','Final','3º y 4º puesto'] }
};
let trnG = 2;
function trnV(id){ return parseFloat(document.getElementById(id).value) || 0; }
function trnMoney(n){ return '$' + Math.round(n).toLocaleString('es-CL'); }
function trnSet(n){ trnG = n; document.querySelectorAll('[data-g]').forEach(b => b.classList.toggle('active', +b.dataset.g === n)); trnRender(); }
function trnRender(){
  const f = TRN[trnG], iva = 1 + trnV('trnIva')/100;
  const galv = trnV('trnGalvano')*iva*2, med = trnV('trnMedalla')*iva*6, prizes = galv + med;
  const blocks = f.gb + f.fb, court = blocks*trnV('trnBloque'), total = court + prizes, per = total / f.pairs;
  document.getElementById('trnPerPair').textContent = trnMoney(per);
  document.getElementById('trnMetrics').innerHTML = [['Parejas',f.pairs],['Partidos',f.gm+f.fm],['Bloques cancha',blocks],['Días',f.days]]
    .map(m => '<div class="trn-metric"><small>'+m[0]+'</small><b>'+m[1]+'</b></div>').join('');
  document.getElementById('trnBreakdown').innerHTML = [['Canchas · '+blocks+' bloques',court],['Galvanos · 2',galv],['Medallas · 6',med],['Costo total',total]]
    .map((r,i) => '<div class="trn-row'+(i===3?' total':'')+'"><span>'+r[0]+'</span><span>'+trnMoney(r[1])+'</span></div>').join('');
  document.getElementById('trnRule').textContent = f.rule + ' → ' + f.q + ' clasificados';
  document.getElementById('trnRounds').innerHTML = f.rounds.map(r => '<span class="trn-round">'+r+'</span>').join('');
}
trnSet(2);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
