<?php
$epl = new PDO('mysql:host=localhost;dbname=epleague;charset=utf8mb4', 'root', '');
try {
    $epl->exec("ALTER TABLE ligas 
        ADD COLUMN inscripcion_inicio DATE NULL AFTER fecha_fin,
        ADD COLUMN inscripcion_fin DATE NULL AFTER inscripcion_inicio");
    echo "SUCCESS: Columns inscripcion_inicio and inscripcion_fin added to ligas table.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
