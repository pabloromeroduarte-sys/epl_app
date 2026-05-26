<?php
$page_title = 'Admin — Calendario de Cumpleaños';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();

// 1. Obtener mes seleccionado (por defecto el actual)
$mes_actual = (int)date('m');
$mes_sel    = isset($_GET['mes']) ? max(1, min(12, (int)$_GET['mes'])) : $mes_actual;
$anio_actual = (int)date('Y');

// Nombres de meses en español
$meses_es = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// 2. Query de cumpleaños del mes seleccionado
$st = $db->prepare("
    SELECT id, nombre, apellido, email, telefono, fecha_nacimiento,
           DAY(fecha_nacimiento) as dia,
           MONTH(fecha_nacimiento) as mes,
           (YEAR(CURDATE()) - YEAR(fecha_nacimiento)) as edad_cumple
    FROM jugadores
    WHERE fecha_nacimiento IS NOT NULL 
      AND fecha_nacimiento != '0000-00-00'
      AND MONTH(fecha_nacimiento) = ?
      AND estado = 'activo'
    ORDER BY dia ASC, nombre ASC
");
$st->execute([$mes_sel]);
$cumples_mes = $st->fetchAll(PDO::FETCH_ASSOC);

// Agrupar cumpleaños por día del mes
$cumples_por_dia = [];
foreach ($cumples_mes as $c) {
    $cumples_por_dia[(int)$c['dia']][] = $c;
}

// 3. Query de cumpleaños de HOY
$hoy_md = date('m-d');
$st_hoy = $db->prepare("
    SELECT id, nombre, apellido, email, telefono, fecha_nacimiento,
           (YEAR(CURDATE()) - YEAR(fecha_nacimiento)) as edad_cumple
    FROM jugadores
    WHERE fecha_nacimiento IS NOT NULL
      AND fecha_nacimiento != '0000-00-00'
      AND DATE_FORMAT(fecha_nacimiento, '%m-%d') = ?
      AND estado = 'activo'
    ORDER BY nombre ASC
");
$st_hoy->execute([$hoy_md]);
$cumples_hoy = $st_hoy->fetchAll(PDO::FETCH_ASSOC);

// 4. Calcular estructura del calendario para el mes seleccionado
$primer_dia_timestamp = mktime(0, 0, 0, $mes_sel, 1, $anio_actual);
$cant_dias_mes = (int)date('t', $primer_dia_timestamp);
// N de día de la semana (1 para Lunes, 7 para Domingo)
$dia_semana_1er_dia = (int)date('N', $primer_dia_timestamp);

require_once '../includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <!-- Cabecera Premium -->
    <div class="dash-header" style="background:linear-gradient(135deg,#1c2f48 0%, #0f1e30 100%);border-radius:18px;padding:1.5rem 1.75rem;color:#fff;margin-bottom:1.5rem;position:relative;overflow:hidden">
      <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:radial-gradient(circle,rgba(201,167,98,.2),transparent 70%)"></div>
      <div style="position:relative;z-index:1">
        <span style="font-size:.65rem;font-weight:900;letter-spacing:.25em;color:#C9A762;text-transform:uppercase">Panel admin</span>
        <h1 style="color:#fff;margin:.2rem 0 .15rem;font-size:clamp(1.5rem,3.5vw,2rem);font-family:'Anton',sans-serif;text-transform:uppercase;line-height:1">Calendario de <span style="color:#C9A762">Cumpleaños</span></h1>
        <p style="color:rgba(255,255,255,.7);margin-top:.2rem;font-size:.82rem">Revisa quiénes cumplen años en la liga, mándales saludos por WhatsApp y celebra su día.</p>
      </div>
    </div>

    <!-- CUMPLEAÑOS DE HOY (Destacado Golden) -->
    <?php if (!empty($cumples_hoy)): ?>
    <div class="card" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 2px solid #f59e0b; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 15px rgba(245,158,11,.15)">
      <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:1rem">
        <span style="font-size:2rem; animation: pulse 1.5s infinite">🎂</span>
        <div>
          <h2 style="font-size:1.15rem; font-weight:800; color:#92400e; margin:0">¡Cumpleaños de Hoy!</h2>
          <p style="font-size:.8rem; color:#b45309; margin:.15rem 0 0">Hoy es un día especial para los siguientes jugadores de la liga:</p>
        </div>
      </div>
      
      <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:1rem">
        <?php foreach ($cumples_hoy as $ch): ?>
        <?php 
          $tel_clean = preg_replace('/\D+/', '', $ch['telefono'] ?? '');
          if ($tel_clean && !str_starts_with($tel_clean, '56')) {
              $tel_clean = '56' . $tel_clean;
          }
          $ws_link = $tel_clean 
            ? 'https://wa.me/' . $tel_clean . '?text=' . urlencode("¡Hola " . $ch['nombre'] . "! Te escribimos de Elite Padel League para desearte un muy feliz cumpleaños 🎂 que tengas un excelente día y mucho éxito en tus partidos. ¡Un abrazo!")
            : null;
        ?>
        <div style="background:#fff; border-radius:12px; padding:1rem; border:1px solid #fde68a; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 6px rgba(0,0,0,.03)">
          <div>
            <div style="font-weight:800; color:var(--navy); font-size:.9rem"><?= epl_h($ch['nombre'] . ' ' . $ch['apellido']) ?></div>
            <div style="font-size:.78rem; color:#64748b; margin-top:.2rem">
              Cumple: <strong style="color:#d97706"><?= $ch['edad_cumple'] ?> años</strong>
            </div>
            <?php if ($ch['telefono']): ?>
              <div style="font-size:.75rem; color:#94a3b8; margin-top:.1rem">📞 <?= epl_h($ch['telefono']) ?></div>
            <?php endif; ?>
          </div>
          <?php if ($ws_link): ?>
            <a href="<?= $ws_link ?>" target="_blank" rel="noopener" style="background:#25d366; color:#fff; font-size:.72rem; font-weight:800; text-decoration:none; padding:.45rem .75rem; border-radius:8px; display:inline-flex; align-items:center; gap:.35rem; transition:transform .12s">
              <span>💬 Felicitar</span>
            </a>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- SELECCIÓN DE MES (Sliding Menu) -->
    <div style="display:flex; gap:.4rem; overflow-x:auto; padding-bottom:.75rem; margin-bottom:1.5rem; -webkit-overflow-scrolling:touch" class="no-scrollbar">
      <?php foreach ($meses_es as $num => $nombre): ?>
        <?php $active = ($mes_sel === $num); ?>
        <a href="?mes=<?= $num ?>" style="padding:.55rem 1.1rem; border-radius:10px; font-size:.82rem; font-weight:800; text-decoration:none; white-space:nowrap;
           background:<?= $active ? 'var(--navy)' : '#fff' ?>;
           color:<?= $active ? '#fff' : 'var(--gray-600)' ?>;
           border:1px solid <?= $active ? 'var(--navy)' : 'var(--gray-200)' ?>;
           box-shadow: <?= $active ? '0 4px 10px rgba(28,47,72,0.25)' : '0 2px 4px rgba(0,0,0,0.03)' ?>;
           transition: all .12s">
          <?= $nombre ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- DISTRIBUCIÓN: Calendario y Listado -->
    <div style="display:grid; grid-template-columns:1fr; gap:1.5rem">
      
      <!-- GRID DEL CALENDARIO -->
      <div class="card" style="padding:1.5rem">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem">
          <h2 style="font-size:1.1rem; font-weight:800; color:var(--navy); margin:0">
            📅 Calendario de <?= $meses_es[$mes_sel] ?>
          </h2>
          <span style="font-size:.78rem; font-weight:800; color:#64748b; background:#f1f5f9; padding:.3rem .7rem; border-radius:6px">
            <?= count($cumples_mes) ?> cumpleaños en este mes
          </span>
        </div>

        <!-- Encabezados de días de la semana -->
        <div style="display:grid; grid-template-columns:repeat(7, 1fr); text-align:center; font-size:.7rem; font-weight:900; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.5rem">
          <div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div><div>Dom</div>
        </div>

        <!-- Días del mes en Grid -->
        <div style="display:grid; grid-template-columns:repeat(7, 1fr); gap:4px">
          <?php
          // 1. Espacios en blanco al inicio del mes
          for ($i = 1; $i < $dia_semana_1er_dia; $i++) {
              echo '<div style="background:#f8fafc; border-radius:8px; aspect-ratio:1.1"></div>';
          }

          // 2. Días del mes reales
          for ($dia = 1; $dia <= $cant_dias_mes; $dia++) {
              $has_cumple = isset($cumples_por_dia[$dia]);
              $es_hoy = ($mes_sel === (int)date('m') && $dia === (int)date('d'));
              
              $bg = '#f8fafc';
              $border = '1px solid #f1f5f9';
              if ($has_cumple) {
                  $bg = '#fffbeb';
                  $border = '1px solid #fde68a';
              }
              if ($es_hoy) {
                  $bg = '#fef3c7';
                  $border = '2px solid #d97706';
              }
              ?>
              <div style="background:<?= $bg ?>; border:<?= $border ?>; border-radius:8px; padding:.4rem; aspect-ratio:1.1; display:flex; flex-direction:column; justify-content:space-between; position:relative; cursor:<?= $has_cumple ? 'pointer' : 'default' ?>"
                   <?= $has_cumple ? 'onclick="scrollToDay('.$dia.')"' : '' ?>
                   class="dia-celda">
                <span style="font-weight:700; font-size:.85rem; color:<?= $es_hoy ? '#b45309' : 'var(--navy)' ?>"><?= $dia ?></span>
                <?php if ($has_cumple): ?>
                  <div style="display:flex; justify-content:space-between; align-items:center">
                    <span style="font-size:.9rem; filter:drop-shadow(0 1px 1px rgba(0,0,0,0.1))">🎂</span>
                    <span style="background:#d97706; color:#fff; font-size:.6rem; font-weight:900; border-radius:99px; padding:.05rem .25rem; line-height:1">
                      <?= count($cumples_por_dia[$dia]) ?>
                    </span>
                  </div>
                <?php endif; ?>
              </div>
              <?php
          }
          ?>
        </div>
      </div>

      <!-- LISTADO DETALLADO -->
      <div class="card" style="padding:1.5rem">
        <h2 style="font-size:1.1rem; font-weight:800; color:var(--navy); margin:0 0 1.25rem">
          📋 Lista de Cumpleaños
        </h2>

        <?php if (empty($cumples_mes)): ?>
          <p style="color:#94a3b8; text-align:center; padding:2rem; font-size:.85rem">No hay jugadores con fecha de nacimiento registrada en este mes.</p>
        <?php else: ?>
          <div style="display:grid; grid-template-columns:1fr; gap:.75rem">
            <?php foreach ($cumples_mes as $c): ?>
            <?php 
              $tel_clean = preg_replace('/\D+/', '', $c['telefono'] ?? '');
              if ($tel_clean && !str_starts_with($tel_clean, '56')) {
                  $tel_clean = '56' . $tel_clean;
              }
              $ws_link = $tel_clean 
                ? 'https://wa.me/' . $tel_clean . '?text=' . urlencode("¡Hola " . $c['nombre'] . "! De parte de la organización de Elite Padel League te deseamos un feliz cumpleaños 🎂 ¡Que pases un excelente día y nos vemos en las canchas!")
                : null;
            ?>
            <div id="dia-cumple-<?= $c['dia'] ?>" style="display:flex; justify-content:space-between; align-items:center; padding:.85rem 1rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; transition: background .2s, border-color .2s" class="cumple-fila">
              <div style="display:flex; align-items:center; gap:1rem">
                <!-- Círculo del día -->
                <div style="width:40px; height:40px; border-radius:50%; background:var(--navy); color:#fff; display:flex; align-items:center; justify-content:center; flex-direction:column; line-height:1.1">
                  <span style="font-size:.9rem; font-weight:800"><?= $c['dia'] ?></span>
                  <span style="font-size:.48rem; text-transform:uppercase; font-weight:700"><?= substr($meses_es[$mes_sel], 0, 3) ?></span>
                </div>
                <div>
                  <div style="font-weight:800; color:var(--navy); font-size:.88rem"><?= epl_h($c['nombre'] . ' ' . $c['apellido']) ?></div>
                  <div style="font-size:.78rem; color:#64748b; margin-top:.15rem">
                    Cumple: <strong style="color:var(--navy)"><?= $c['edad_cumple'] ?> años</strong> · Nacimiento: <?= date('d/m/Y', strtotime($c['fecha_nacimiento'])) ?>
                  </div>
                </div>
              </div>
              
              <div style="display:flex; gap:.5rem">
                <?php if ($ws_link): ?>
                  <a href="<?= $ws_link ?>" target="_blank" rel="noopener" class="btn btn-sm" style="background:#25d366; color:#fff; border:none; display:inline-flex; align-items:center; gap:.35rem; font-weight:700; font-size:.72rem">
                    <span>WhatsApp</span>
                  </a>
                <?php endif; ?>
                <a href="mailto:<?= epl_h($c['email']) ?>" class="btn btn-sm" style="background:#f1f5f9; color:var(--navy); border:none; display:inline-flex; align-items:center; font-weight:700; font-size:.72rem">
                  Email
                </a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>

  </main>
</div>

<!-- Animaciones y Scripts -->
<style>
@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); }
}
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.dia-celda:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.06);
  transition: all .15s ease-bezier(0,0,0.2,1);
}
.cumple-fila.highlight {
  background: #fffbeb !important;
  border-color: #fde68a !important;
}
</style>

<script>
function scrollToDay(dia) {
  const el = document.getElementById('dia-cumple-' + dia);
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    // Resaltar visualmente
    el.classList.add('highlight');
    setTimeout(() => {
      el.classList.remove('highlight');
    }, 2000);
  }
}
</script>

<?php require_once '../includes/footer.php'; ?>
