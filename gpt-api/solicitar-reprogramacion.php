<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gpt_actions.php';
epl_gpt_require_method('POST');
$in = epl_gpt_input();
epl_gpt_run('solicitar_reprogramacion', [
    'partido_id' => (int)($in['partido_id'] ?? 0),
    'motivo' => trim((string)($in['motivo'] ?? '')),
    'fecha_propuesta' => isset($in['fecha_propuesta']) ? trim((string)$in['fecha_propuesta']) : '',
    'rival_no_responde' => !empty($in['rival_no_responde']),
    'mutuo_acuerdo' => !empty($in['mutuo_acuerdo']),
    'confirmar' => !empty($in['confirmar']),
]);

