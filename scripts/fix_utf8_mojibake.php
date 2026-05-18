<?php
/** Repara texto UTF-8 leído como Latin-1 (capitÃ¡n → capitán). */
declare(strict_types=1);

$root = dirname(__DIR__);
$files = array_merge(
    glob($root . '/*.php') ?: [],
    glob($root . '/includes/**/*.php') ?: []
);

foreach ($files as $path) {
    $raw = file_get_contents($path);
    if ($raw === false || !preg_match('/Ã.|â€|â†|âœ|â"/u', $raw)) {
        continue;
    }
    $fixed = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
    if ($fixed === $raw) {
        continue;
    }
    file_put_contents($path, $fixed);
    echo "Fixed: " . str_replace($root . DIRECTORY_SEPARATOR, '', $path) . PHP_EOL;
}
