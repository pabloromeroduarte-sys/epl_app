<?php
declare(strict_types=1);

if (($_GET['token'] ?? '') !== 'epl_backup_2026_9z') {
    die('Forbidden');
}

$files = [
    __DIR__ . '/_epl_backup_db.sql',
    __DIR__ . '/_epl_uploads.zip'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            echo "Deleted: " . basename($file) . "\n";
        } else {
            echo "Failed to delete: " . basename($file) . "\n";
        }
    } else {
        echo "Not found: " . basename($file) . "\n";
    }
}
unlink(__FILE__); // self-destruct on execution
echo "Cleanup script deleted itself.\n";
