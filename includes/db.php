<?php
declare(strict_types=1);
date_default_timezone_set('America/Santiago');

function epl_env(string $key, string $default = ''): string {
    static $cache = [];
    if (empty($cache)) {
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if ($line[0] === '#' || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $cache[trim($k)] = trim($v);
            }
        }
    }
    return $cache[$key] ?? $_ENV[$key] ?? $default;
}

function epl_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = epl_env('DB_HOST', 'localhost');
    $name = epl_env('DB_NAME', 'epleague');
    $user = epl_env('DB_USER', 'root');
    $pass = epl_env('DB_PASS', '');

    $pdo = new PDO(
        "mysql:host=$host;dbname=$name;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $pdo->exec("SET time_zone = '-04:00'");
    return $pdo;
}
