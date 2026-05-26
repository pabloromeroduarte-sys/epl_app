<?php
// Script de prueba para diagnosticar el error 500 en cumpleanos.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
// epl_require_admin();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$db = epl_db();
echo "<h1>Prueba de Diagnóstico Cumpleaños</h1>";

try {
    echo "<h2>Probando consulta 1 (cumpleaños del mes)...</h2>";
    $mes_sel = (int)date('m');
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
    $res1 = $st->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Consulta 1 exitosa. Encontrados: " . count($res1) . " jugadores.</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>Error en consulta 1: " . htmlspecialchars($e->getMessage()) . "</p>";
}

try {
    echo "<h2>Probando consulta 2 (cumpleaños de hoy)...</h2>";
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
    $res2 = $st_hoy->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Consulta 2 exitosa. Encontrados: " . count($res2) . " jugadores.</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>Error en consulta 2: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Fin del test</h2>";
