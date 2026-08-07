<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gpt_actions.php';
epl_gpt_require_method('GET');
$in = epl_gpt_input();
epl_gpt_run('buscar_partidos', array_filter([
    'liga_id' => isset($in['liga_id']) ? (int)$in['liga_id'] : null,
    'estado' => $in['estado'] ?? null,
    'desde' => $in['desde'] ?? null,
    'hasta' => $in['hasta'] ?? null,
    'buscar' => $in['buscar'] ?? null,
    'limite' => isset($in['limite']) ? (int)$in['limite'] : null,
], static fn($v) => $v !== null && $v !== ''));

