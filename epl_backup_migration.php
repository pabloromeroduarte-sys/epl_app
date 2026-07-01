<?php
/**
 * Script temporal de migración - Respaldar Base de Datos y Uploads
 * USO: epl_backup_migration.php?token=epl_backup_2026_9z
 */

declare(strict_types=1);

// Token de seguridad hardcodeado para evitar accesos no autorizados
$secret_token = "epl_backup_2026_9z";
if (($_GET['token'] ?? '') !== $secret_token) {
    header('HTTP/1.0 403 Forbidden');
    echo "Acceso denegado.";
    exit;
}

// Aumentar límites para procesos largos
ini_set('memory_limit', '512M');
set_time_limit(300);

require_once __DIR__ . '/includes/db.php';

$mode = $_GET['action'] ?? 'all';

if ($mode === 'db' || $mode === 'all') {
    backup_db();
}

if ($mode === 'uploads' || $mode === 'all') {
    backup_uploads();
}

echo "\nProceso finalizado con éxito.";

function backup_db() {
    echo "Iniciando respaldo de Base de Datos...\n";
    try {
        $pdo = epl_db();
        $sql = "-- Respaldo de Base de Datos EPL\n";
        $sql .= "-- Generado el " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        // Obtener tablas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $sql .= "-- Structure for table: $table\n";
            $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            $sql .= $createStmt['Create Table'] . ";\n\n";

            // Obtener registros
            $dataStmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($rows) > 0) {
                $sql .= "-- Data for table: $table\n";
                foreach ($rows as $row) {
                    $keys = array_map(fn($k) => "`$k`", array_keys($row));
                    $values = array_map(function($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        return $pdo->quote((string)$v);
                    }, array_values($row));

                    $sql .= "INSERT INTO `$table` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // Guardar a archivo
        $filename = __DIR__ . '/_epl_backup_db.sql';
        file_put_contents($filename, $sql);
        echo "Base de datos respaldada correctamente en: " . basename($filename) . " (" . number_format(strlen($sql) / 1024 / 1024, 2) . " MB)\n";

    } catch (Exception $e) {
        echo "ERROR al respaldar base de datos: " . $e->getMessage() . "\n";
    }
}

function backup_uploads() {
    echo "Iniciando respaldo de carpeta /uploads/...\n";
    $dirToZip = __DIR__ . '/uploads';
    $zipFile = __DIR__ . '/_epl_uploads.zip';

    if (!file_exists($dirToZip)) {
        echo "La carpeta /uploads/ no existe en este servidor.\n";
        return;
    }

    if (file_exists($zipFile)) {
        unlink($zipFile);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        echo "No se pudo crear el archivo ZIP.\n";
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirToZip),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $count = 0;
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($dirToZip) + 1);
            $zip->addFile($filePath, $relativePath);
            $count++;
        }
    }

    $zip->close();
    echo "Carpeta uploads comprimida correctamente en: " . basename($zipFile) . " ($count archivos comprimidos)\n";
}
