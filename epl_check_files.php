<?php
declare(strict_types=1);

if (($_GET['token'] ?? '') !== 'epl_backup_2026_9z') {
    die('Forbidden');
}

$dir = __DIR__ . '/uploads';
if (!file_exists($dir)) {
    die('/uploads does not exist');
}

function list_files($dir) {
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            echo "DIR: $file\n";
            list_files($path);
        } else {
            echo "FILE: $file (" . filesize($path) . " bytes)\n";
        }
    }
}

list_files($dir);
