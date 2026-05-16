<?php
// Token secreto — debe coincidir con el secret DEPLOY_TOKEN en GitHub Actions
$secret = getenv('DEPLOY_TOKEN') ?: 'epl_deploy_2026_x9k';

$token = $_GET['token'] ?? $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';
if ($token !== $secret) {
    http_response_code(403);
    die('Forbidden');
}

$dir = __DIR__;
$output = [];
$code   = 0;

// Arreglar ownership de git si es necesario
exec("git config --global --add safe.directory $dir 2>&1", $output, $code);

// Pull
exec("GIT_TERMINAL_PROMPT=0 git -C $dir pull origin main 2>&1", $output, $code);

header('Content-Type: text/plain');
echo implode("\n", $output) . "\n";
echo "Exit code: $code\n";
echo "Done: " . date('Y-m-d H:i:s') . "\n";
