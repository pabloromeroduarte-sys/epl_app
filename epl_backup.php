<?php
// epl_backup.php
// Script para respaldar base de datos y archivos críticos sin afectar el sistema original.
// Protegido por token para seguridad.

$secret_token = 'epl_backup_2026_secreto';

if (!isset($_GET['token']) || $_GET['token'] !== $secret_token) {
    http_response_code(403);
    die("Acceso denegado.");
}

// 1. Obtener credenciales de la base de datos
$envFile = __DIR__ . '/.env';
$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
}

$db_host = $env['DB_HOST'] ?? 'localhost';
$db_name = $env['DB_NAME'] ?? 'epldb';
$db_user = $env['DB_USER'] ?? 'root';
$db_pass = $env['DB_PASS'] ?? '';

// 2. Hacer el mysqldump
$sql_file = __DIR__ . '/backup_db_temp.sql';
$command = sprintf(
    'mysqldump -h %s -u %s %s %s > %s',
    escapeshellarg($db_host),
    escapeshellarg($db_user),
    $db_pass ? "-p" . escapeshellarg($db_pass) : "",
    escapeshellarg($db_name),
    escapeshellarg($sql_file)
);

exec($command, $output, $result);
if ($result !== 0) {
    die("Error al hacer mysqldump. Código: $result");
}

// 3. Crear archivo Zip con el .sql, el .env y la carpeta uploads
$zip_file = __DIR__ . '/respaldo_completo.zip';
$zip = new ZipArchive();

if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Error al crear el archivo ZIP.");
}

// Agregar la base de datos
if (file_exists($sql_file)) {
    $zip->addFile($sql_file, 'backup_db_temp.sql');
}

// Agregar el .env
if (file_exists($envFile)) {
    $zip->addFile($envFile, '.env');
}

// Agregar carpeta uploads
$uploads_dir = __DIR__ . '/uploads';
if (is_dir($uploads_dir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploads_dir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = 'uploads/' . substr($filePath, strlen($uploads_dir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
}

$zip->close();

// Eliminar el SQL temporal por seguridad
if (file_exists($sql_file)) {
    unlink($sql_file);
}

// 4. Forzar descarga del ZIP
if (file_exists($zip_file)) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="respaldo_elitepadel_' . date('Y-m-d_H-i') . '.zip"');
    header('Content-Length: ' . filesize($zip_file));
    readfile($zip_file);
    
    // Eliminar el zip después de descargar para no dejar basura
    unlink($zip_file);
    exit;
} else {
    die("Error: No se encontró el archivo ZIP final.");
}
