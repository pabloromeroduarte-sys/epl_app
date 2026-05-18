<?php
/**
 * SCRIPT DE LIMPIEZA TOTAL
 * Borra todos los datos de EPL excepto la configuración (SMTP, MP).
 * Usar SOLO antes de importar datos desde WordPress.
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
epl_require_admin();

$db  = epl_db();
$ok  = false;
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirmar'] ?? '') === 'BORRAR TODO') {
    try {
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");

        $tablas = [
            'suplente_partidos',
            'suplentes',
            'solicitudes_reprogramacion',
            'ranking_puntos',
            'clasificacion',
            'liga_equipos',
            'inscripciones',
            'pagos',
            'notificaciones',
            'partidos',
            'equipos',
            'jugadores',
            'ligas',
            'recintos',
            'finanzas_ingresos',
            'finanzas_egresos',
            'gastos',
            'saldos_apps',
            'saldos_clubes',
        ];

        foreach ($tablas as $t) {
            $db->exec("TRUNCATE TABLE `$t`");
        }

        $db->exec("SET FOREIGN_KEY_CHECKS = 1");

        $ok  = true;
        $msg = 'Base de datos limpiada. Todas las tablas vaciadas excepto configuración.';
    } catch (Throwable $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Limpiar BD — EPL</title>
<style>
  body { font-family: monospace; background: #0a1421; color: #fff; padding: 2rem; }
  h1 { color: #C9A762; }
  .card { background: #1C2F48; border-radius: 12px; padding: 2rem; max-width: 500px; margin: 2rem auto; }
  .alert-ok  { background: #14532d; color: #86efac; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
  .alert-err { background: #7f1d1d; color: #fca5a5; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
  .warn { background: #7c2d12; color: #fdba74; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: bold; }
  input[type=text] { width: 100%; padding: .6rem; border-radius: 6px; border: 1px solid #C9A762; background: #0a1421; color: #fff; font-family: monospace; font-size: 1rem; margin-bottom: 1rem; }
  button { background: #dc2626; color: #fff; border: none; padding: .75rem 1.5rem; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; }
  button:hover { background: #b91c1c; }
  .tablas { font-size: .8rem; color: #94a3b8; margin-top: 1rem; }
</style>
</head>
<body>
<div class="card">
  <h1>⚠️ Limpiar Base de Datos</h1>

  <?php if ($ok): ?>
    <div class="alert-ok">✅ <?= htmlspecialchars($msg) ?></div>
    <p>Ahora podés correr el script de importación de WordPress.</p>
    <a href="importar_wp.php" style="color:#C9A762">→ Ir a importar_wp.php</a>
  <?php elseif ($msg): ?>
    <div class="alert-err">❌ <?= htmlspecialchars($msg) ?></div>
  <?php else: ?>
    <div class="warn">
      ⛔ ATENCIÓN: Esto borrará TODOS los datos de la aplicación.<br>
      Solo se conserva la tabla <strong>configuracion</strong> (SMTP, MercadoPago).
    </div>

    <p>Tablas que se vaciarán:</p>
    <div class="tablas">
      jugadores, equipos, ligas, recintos, partidos, clasificacion, liga_equipos,
      inscripciones, pagos, notificaciones, suplentes, suplente_partidos,
      solicitudes_reprogramacion, ranking_puntos, finanzas_ingresos,
      finanzas_egresos, gastos, saldos_apps, saldos_clubes
    </div>

    <form method="POST" style="margin-top:1.5rem">
      <label style="display:block;margin-bottom:.5rem;color:#fbbf24">
        Escribí exactamente: <strong>BORRAR TODO</strong>
      </label>
      <input type="text" name="confirmar" placeholder="BORRAR TODO" required>
      <button type="submit">💥 Borrar todo y empezar de cero</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
