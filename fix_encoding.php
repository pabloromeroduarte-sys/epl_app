<?php
// Protección: solo admin logueado puede ejecutar este script de diagnóstico.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
epl_require_admin();
// ─────────────────────────────────────────────────────────────────────────────/**
 * Script para corregir problemas de codificaciÃ³n (doble encode UTF-8)
 * en la base de datos epleague.
 */
error_reporting(E_ALL); ini_set('display_errors', 1);

$db = new PDO('mysql:host=localhost;dbname=epleague;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

function fix_str(?string $s): ?string {
    if (!$s) return $s;
    // Si detectamos el patrÃ³n de doble encoding Ã
    if (str_contains($s, 'Ã')) {
        // Intentamos revertir el doble encoding
        // Esto asume que el string es UTF-8 pero los caracteres originales 
        // fueron interpretados como Latin1 antes de volver a guardarse en UTF-8.
        $fixed = @mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
        return $fixed ?: $s;
    }
    return $s;
}

$tables = [
    'jugadores' => ['nombre', 'apellido', 'comuna', 'profesion', 'pala'],
    'equipos'   => ['nombre'],
    'partidos'  => ['nombre_fecha', 'cancha'],
    'recintos'  => ['nombre', 'direccion'],
    'ligas'     => ['nombre', 'temporada', 'sede']
];

foreach ($tables as $table => $cols) {
    echo "Procesando tabla: $table...\n";
    $rows = $db->query("SELECT id, " . implode(',', $cols) . " FROM $table")->fetchAll(PDO::FETCH_ASSOC);
    
    $stUpd = $db->prepare("UPDATE $table SET " . implode('=?,', $cols) . "=? WHERE id=?");
    
    $count = 0;
    foreach ($rows as $row) {
        $id = $row['id'];
        unset($row['id']);
        
        $newValues = [];
        $changed = false;
        foreach ($row as $val) {
            $fixed = fix_str($val);
            if ($fixed !== $val) $changed = true;
            $newValues[] = $fixed;
        }
        
        if ($changed) {
            $newValues[] = $id;
            $stUpd->execute($newValues);
            $count++;
        }
    }
    echo "  -> $count registros corregidos.\n";
}

echo "\nÂ¡CodificaciÃ³n corregida!\n";

