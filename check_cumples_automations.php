<?php
// Test script to check current email automations in the database
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$db = epl_db();
echo "<h1>Automatizaciones de Email en la BD</h1>";

try {
    $st = $db->query("SELECT * FROM email_automatizaciones");
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rows)) {
        echo "<p>No hay registros en email_automatizaciones.</p>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse; font-family:sans-serif;'>";
        echo "<tr style='background:#eee;'>";
        echo "<th>ID</th><th>Nombre</th><th>Trigger</th><th>Destinatario</th><th>Activo</th><th>Asunto</th><th>Cuerpo</th>";
        echo "</tr>";
        foreach ($rows as $r) {
            echo "<tr>";
            echo "<td>" . $r['id'] . "</td>";
            echo "<td>" . htmlspecialchars($r['nombre']) . "</td>";
            echo "<td>" . htmlspecialchars($r['trigger_tipo']) . "</td>";
            echo "<td>" . htmlspecialchars($r['destinatario']) . "</td>";
            echo "<td>" . ($r['activo'] ? 'SÍ' : 'NO') . "</td>";
            echo "<td>" . htmlspecialchars($r['asunto']) . "</td>";
            echo "<td><pre style='margin:0; font-family:sans-serif; max-width:400px; white-space:pre-wrap;'>" . htmlspecialchars($r['cuerpo']) . "</pre></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'>Error al consultar: " . htmlspecialchars($e->getMessage()) . "</p>";
}
