<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Presupuesto — <?= htmlspecialchars($pres['nombre']) ?></title>
<style>
  * { box-sizing:border-box; margin:0; padding:0 }
  body { font-family: Arial, sans-serif; font-size: 12px; color: #1a202c; background:#fff; padding:30px 40px }
  .header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #1C2F48; padding-bottom:14px; margin-bottom:18px }
  .logo-text { font-size:20px; font-weight:900; text-transform:uppercase; letter-spacing:.06em; color:#1C2F48 }
  .logo-sub { font-size:10px; color:#64748b; font-weight:600; margin-top:2px }
  .pres-title { font-size:17px; font-weight:900; color:#1C2F48; text-align:right }
  .pres-meta { font-size:10px; color:#64748b; text-align:right; margin-top:3px }
  .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:9px; font-weight:700; text-transform:uppercase }
  .badge-borrador { background:#fef3c7; color:#92400e }
  .badge-cerrado  { background:#d1fae5; color:#065f46 }

  /* Resumen */
  .summary { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:18px }
  .sum-box { border-radius:10px; padding:12px 14px; text-align:center }
  .sum-box.ing { background:#f0fdf4; border:1.5px solid #bbf7d0 }
  .sum-box.egr { background:#fef2f2; border:1.5px solid #fecaca }
  .sum-box.gan { background:#eff6ff; border:1.5px solid #bfdbfe }
  .sum-box.neg { background:#fef2f2; border:1.5px solid #fecaca }
  .sum-num { font-size:18px; font-weight:900; line-height:1.1 }
  .sum-lbl { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; margin-top:3px; opacity:.75 }
  .sum-box.ing .sum-num { color:#16a34a }
  .sum-box.egr .sum-num { color:#dc2626 }
  .sum-box.gan .sum-num { color:#1d4ed8 }
  .sum-box.neg .sum-num { color:#dc2626 }

  /* Tablas */
  .section-title { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; margin-bottom:6px; padding:5px 8px; border-radius:6px }
  .ing-title { background:#dcfce7; color:#15803d }
  .egr-title { background:#fee2e2; color:#b91c1c }
  table { width:100%; border-collapse:collapse; margin-bottom:16px }
  th { background:#f8fafc; padding:6px 8px; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#64748b; border-bottom:2px solid #e2e8f0; text-align:left }
  td { padding:5px 8px; border-bottom:1px solid #f1f5f9; font-size:11px }
  tr:last-child td { border-bottom:none }
  .num { text-align:right }
  .bold { font-weight:700 }
  .total-row td { background:#f8fafc; font-weight:800; font-size:12px }
  .footer { margin-top:24px; padding-top:12px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:flex-end }
  .footer-note { font-size:9px; color:#94a3b8 }
  .notas-box { background:#f8fafc; border-left:3px solid #cbd5e1; padding:8px 12px; border-radius:0 6px 6px 0; margin-bottom:16px; font-size:11px; color:#475569 }

  @media print {
    body { padding:15px 20px }
    @page { margin:15mm 12mm }
  }
</style>
</head>
<body>

<!-- Encabezado -->
<div class="header">
  <div>
    <div class="logo-text">Elite Padel League</div>
    <div class="logo-sub">Presupuesto interno de torneo</div>
  </div>
  <div style="text-align:right">
    <div class="pres-title"><?= htmlspecialchars($pres['nombre']) ?></div>
    <div class="pres-meta">
      <?php if ($pres['referencia']): ?><?= htmlspecialchars($pres['referencia']) ?> &nbsp;·&nbsp; <?php endif; ?>
      <?= ucfirst($pres['tipo']) ?> &nbsp;·&nbsp;
      <?= date('d/m/Y', strtotime($pres['updated_at'])) ?>
      &nbsp;&nbsp;
      <span class="badge badge-<?= $pres['estado'] ?>"><?= ucfirst($pres['estado']) ?></span>
    </div>
  </div>
</div>

<!-- Resumen -->
<div class="summary">
  <div class="sum-box ing">
    <div class="sum-num"><?= $fmtCLP($tot_ing) ?></div>
    <div class="sum-lbl" style="color:#15803d">Total ingresos</div>
  </div>
  <div class="sum-box egr">
    <div class="sum-num"><?= $fmtCLP($tot_egr) ?></div>
    <div class="sum-lbl" style="color:#b91c1c">Total costos</div>
  </div>
  <div class="sum-box <?= $ganancia>=0?'gan':'neg' ?>">
    <div class="sum-num"><?= $ganancia<0?'-':'' ?><?= $fmtCLP(abs($ganancia)) ?></div>
    <div class="sum-lbl"><?= $ganancia>=0?'Ganancia neta':'⚠ Pérdida' ?></div>
  </div>
</div>

<!-- Notas -->
<?php if ($pres['notas']): ?>
<div class="notas-box"><strong>Notas:</strong> <?= nl2br(htmlspecialchars($pres['notas'])) ?></div>
<?php endif; ?>

<!-- Ingresos -->
<?php if (!empty($fases)):
  $tot_partidos_pdf = array_sum(array_column($fases, 'cantidad'));
  $tot_horas_pdf = array_sum(array_map(fn($f)=>($f['cantidad']*$f['valor_unitario'])/60, $fases));
?>
<div class="section-title" style="background:#e0e7ff;color:#3730a3">🎾 Planificación de partidos</div>
<table>
  <thead><tr>
    <th style="width:32px;text-align:center">#</th>
    <th>Fase</th>
    <th class="num">Partidos</th>
    <th class="num">Canchas simult.</th>
    <th class="num">Duración (min)</th>
    <th class="num">Horas cancha</th>
  </tr></thead>
  <tbody>
    <?php foreach ($fases as $idx => $f):
      $hf = ($f['cantidad'] * $f['valor_unitario']) / 60;
      // extraer canchas del campo descripcion "N cancha(s) simultánea(s)"
      preg_match('/^(\d+)/', $f['descripcion'], $m);
      $nc = $m[1] ?? 1;
    ?>
    <tr>
      <td style="text-align:center;color:#94a3b8"><?= $idx+1 ?></td>
      <td><?= htmlspecialchars($f['categoria']) ?></td>
      <td class="num bold"><?= (int)$f['cantidad'] ?></td>
      <td class="num"><?= $nc ?></td>
      <td class="num"><?= (int)$f['valor_unitario'] ?> min</td>
      <td class="num bold" style="color:#3730a3"><?= $hf == floor($hf) ? (int)$hf : number_format($hf,1,',','.') ?> h</td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot><tr class="total-row">
    <td colspan="2" class="num">Totales</td>
    <td class="num" style="color:#3730a3"><?= $tot_partidos_pdf ?> partidos</td>
    <td></td><td></td>
    <td class="num" style="color:#3730a3"><?= $tot_horas_pdf == floor($tot_horas_pdf) ? (int)$tot_horas_pdf : number_format($tot_horas_pdf,1,',','.') ?> h</td>
  </tr></tfoot>
</table>
<?php endif; ?>

<div class="section-title ing-title">💚 Ingresos</div>
<table>
  <thead><tr>
    <th>Categoría</th><th>Descripción</th>
    <th class="num">Cantidad</th><th class="num">Valor unit.</th><th class="num">Total</th>
  </tr></thead>
  <tbody>
    <?php foreach ($ingresos as $it):
      $tot = $it['cantidad'] * $it['valor_unitario'];
    ?>
    <tr>
      <td><?= htmlspecialchars($it['categoria']) ?></td>
      <td><?= htmlspecialchars($it['descripcion']) ?></td>
      <td class="num"><?= rtrim(rtrim(number_format($it['cantidad'],2,',','.'),'0'),',') ?></td>
      <td class="num"><?= $fmtCLP($it['valor_unitario']) ?></td>
      <td class="num bold"><?= $fmtCLP($tot) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot><tr class="total-row">
    <td colspan="4" class="num">Total ingresos</td>
    <td class="num" style="color:#16a34a"><?= $fmtCLP($tot_ing) ?></td>
  </tr></tfoot>
</table>

<!-- Costos -->
<div class="section-title egr-title">❤️ Costos</div>
<table>
  <thead><tr>
    <th>Categoría</th><th>Descripción</th>
    <th class="num">Cantidad</th><th class="num">Valor unit.</th><th class="num">Total</th>
  </tr></thead>
  <tbody>
    <?php foreach ($egresos as $it):
      $tot = $it['cantidad'] * $it['valor_unitario'];
    ?>
    <tr>
      <td><?= htmlspecialchars($it['categoria']) ?></td>
      <td><?= htmlspecialchars($it['descripcion']) ?></td>
      <td class="num"><?= rtrim(rtrim(number_format($it['cantidad'],2,',','.'),'0'),',') ?></td>
      <td class="num"><?= $fmtCLP($it['valor_unitario']) ?></td>
      <td class="num bold"><?= $fmtCLP($tot) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot><tr class="total-row">
    <td colspan="4" class="num">Total costos</td>
    <td class="num" style="color:#dc2626"><?= $fmtCLP($tot_egr) ?></td>
  </tr></tfoot>
</table>

<!-- Resumen final -->
<table style="max-width:340px;margin-left:auto">
  <tr style="background:#f8fafc"><td class="bold" style="padding:7px 12px">Total ingresos</td><td class="num bold" style="color:#16a34a;padding:7px 12px"><?= $fmtCLP($tot_ing) ?></td></tr>
  <tr><td style="padding:6px 12px">Total costos</td><td class="num" style="color:#dc2626;padding:6px 12px"><?= $fmtCLP($tot_egr) ?></td></tr>
  <tr style="background:<?= $ganancia>=0?'#f0fdf4':'#fef2f2' ?>;border-top:2px solid <?= $ganancia>=0?'#16a34a':'#dc2626' ?>">
    <td class="bold" style="padding:9px 12px;font-size:13px"><?= $ganancia>=0?'Ganancia neta':'Pérdida' ?></td>
    <td class="num bold" style="color:<?= $ganancia>=0?'#16a34a':'#dc2626' ?>;padding:9px 12px;font-size:15px">
      <?= $ganancia<0?'-':'' ?><?= $fmtCLP(abs($ganancia)) ?>
    </td>
  </tr>
</table>

<div class="footer">
  <div class="footer-note">Documento interno — Elite Padel League</div>
  <div class="footer-note">Generado el <?= date('d/m/Y H:i') ?></div>
</div>

<script>window.onload = function(){ window.print(); }</script>
</body>
</html>
