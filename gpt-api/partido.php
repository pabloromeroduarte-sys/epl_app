<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gpt_actions.php';
epl_gpt_require_method('GET');
$in = epl_gpt_input();
epl_gpt_run('ver_partido', ['partido_id' => (int)($in['partido_id'] ?? 0)]);

