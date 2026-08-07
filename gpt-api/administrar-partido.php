<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gpt_actions.php';
epl_gpt_require_method('POST');
$in = epl_gpt_input();
$args = ['partido_id' => (int)($in['partido_id'] ?? 0), 'confirmar' => !empty($in['confirmar'])];
foreach (['fecha_programada','recinto_id','estado','alerta_admin'] as $key) {
    if (array_key_exists($key, $in)) $args[$key] = $in[$key];
}
epl_gpt_run('administrar_partido', $args);

