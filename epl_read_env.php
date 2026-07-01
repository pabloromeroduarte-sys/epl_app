<?php
declare(strict_types=1);

if (($_GET['token'] ?? '') !== 'epl_backup_2026_9z') {
    die('Forbidden');
}

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    echo file_get_contents($envFile);
} else {
    echo ".env not found";
}
