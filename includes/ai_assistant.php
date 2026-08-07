<?php
declare(strict_types=1);

require_once __DIR__ . '/mcp.php';

const EPL_AI_MAX_TOOL_ROUNDS = 4;

function epl_ai_providers(): array {
    return [
        'epl' => ['label' => 'EPL Directo', 'ready' => true],
        'claude' => ['label' => 'Claude', 'ready' => epl_env('ANTHROPIC_API_KEY', '') !== ''],
        'gemini' => ['label' => 'Gemini', 'ready' => epl_env('GEMINI_API_KEY', '') !== ''],
    ];
}

function epl_ai_auth(array $jugador): array {
    return [
        'jugador_id' => (int)$jugador['id'],
        'client_id' => 'epl-web-assistant',
        'nombre' => (string)($jugador['nombre'] ?? ''),
        'apellido' => (string)($jugador['apellido'] ?? ''),
        'email' => (string)($jugador['email'] ?? ''),
        'rol' => (string)($jugador['rol'] ?? 'jugador'),
    ];
}

function epl_ai_system_prompt(array $auth): string {
    $role = (string)$auth['rol'];
    return "Eres el Asistente oficial de Elite Padel League (EPL). Responde en espanol claro y breve. "
        . "La cuenta conectada tiene rol {$role}. Usa herramientas para consultar datos reales; nunca inventes partidos, fechas, resultados o recintos. "
        . "Respeta estrictamente los permisos devueltos por EPL. Los jugadores solo pueden consultar y gestionar lo propio; los clubes solo sus ligas; los administradores todo lo autorizado. "
        . "Antes de cualquier cambio, explica exactamente que se modificara y solicita confirmacion. No afirmes que un cambio fue realizado hasta recibir el resultado exitoso de la herramienta. "
        . "Cuando muestres partidos incluye, si existe: liga, jornada, parejas, fecha, recinto, estado y resultado.";
}

function epl_ai_tools_for(array $auth): array {
    $tools = epl_mcp_tools();
    return array_values(array_filter($tools, static function (array $tool) use ($auth): bool {
        $name = (string)($tool['name'] ?? '');
        if ($auth['rol'] !== 'admin' && in_array($name, ['listar_recintos', 'administrar_partido'], true)) return false;
        if ($auth['rol'] === 'club' && $name === 'solicitar_reprogramacion') return false;
        return true;
    }));
}

function epl_ai_is_write_tool(string $name): bool {
    return in_array($name, ['solicitar_reprogramacion', 'administrar_partido'], true);
}

function epl_ai_is_confirmation(string $message): bool {
    $value = mb_strtolower(trim($message), 'UTF-8');
    return (bool)preg_match('/^(si|sí|confirmo|confirmar|confirmado|hazlo|adelante|de acuerdo)([.! ]|$)/u', $value);
}

function epl_ai_is_cancel(string $message): bool {
    $value = mb_strtolower(trim($message), 'UTF-8');
    return (bool)preg_match('/^(no|cancelar|cancela|olvidalo|olvídalo)([.! ]|$)/u', $value);
}

function epl_ai_tool_payload(array $result): mixed {
    $text = (string)($result['content'][0]['text'] ?? '');
    $decoded = json_decode($text, true);
    return $decoded ?? $text;
}

function epl_ai_pending_key(array $auth): string {
    return 'epl_ai_pending_' . (int)$auth['jugador_id'];
}

function epl_ai_execute_tool(array $auth, string $name, array $arguments): array {
    if (epl_ai_is_write_tool($name)) {
        unset($arguments['confirmar']);
        $_SESSION[epl_ai_pending_key($auth)] = [
            'tool' => $name,
            'arguments' => $arguments,
            'created_at' => time(),
        ];
        return [
            'ok' => false,
            'requiere_confirmacion' => true,
            'mensaje' => 'El cambio NO fue ejecutado. Resume estos datos y pide confirmacion explicita al usuario.',
            'herramienta' => $name,
            'datos' => $arguments,
        ];
    }
    return epl_ai_tool_payload(epl_mcp_call($auth, $name, $arguments));
}

function epl_ai_pending(array $auth): ?array {
    $key = epl_ai_pending_key($auth);
    $pending = $_SESSION[$key] ?? null;
    if (!is_array($pending) || (int)($pending['created_at'] ?? 0) < time() - 600) {
        unset($_SESSION[$key]);
        return null;
    }
    return $pending;
}

function epl_ai_confirm_pending(array $auth): array {
    $key = epl_ai_pending_key($auth);
    $pending = epl_ai_pending($auth);
    if (!$pending) return ['ok' => false, 'error' => 'No hay ningun cambio pendiente de confirmacion.'];
    unset($_SESSION[$key]);
    $arguments = is_array($pending['arguments'] ?? null) ? $pending['arguments'] : [];
    $arguments['confirmar'] = true;
    return epl_ai_tool_payload(epl_mcp_call($auth, (string)$pending['tool'], $arguments));
}

function epl_ai_cancel_pending(array $auth): void {
    unset($_SESSION[epl_ai_pending_key($auth)]);
}

function epl_ai_http_json(string $url, array $headers, array $payload): array {
    if (!function_exists('curl_init')) throw new RuntimeException('El servidor no tiene habilitado cURL.');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 75,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('No fue posible conectar con el proveedor de IA: ' . $error);
    $data = json_decode((string)$raw, true);
    if ($status < 200 || $status >= 300) {
        $message = (string)($data['error']['message'] ?? $data['message'] ?? "HTTP {$status}");
        throw new RuntimeException('El proveedor de IA respondio con error: ' . $message);
    }
    if (!is_array($data)) throw new RuntimeException('El proveedor devolvio una respuesta invalida.');
    return $data;
}

function epl_ai_claude(array $auth, array $history): string {
    $key = epl_env('ANTHROPIC_API_KEY', '');
    if ($key === '') throw new RuntimeException('Claude aun no esta configurado.');
    $messages = array_map(static fn(array $m): array => [
        'role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
        'content' => (string)$m['content'],
    ], $history);
    $tools = array_map(static fn(array $t): array => [
        'name' => $t['name'],
        'description' => $t['description'],
        'input_schema' => $t['inputSchema'],
    ], epl_ai_tools_for($auth));

    for ($round = 0; $round < EPL_AI_MAX_TOOL_ROUNDS; $round++) {
        $data = epl_ai_http_json('https://api.anthropic.com/v1/messages', [
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ], [
            'model' => epl_env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'max_tokens' => 1200,
            'system' => epl_ai_system_prompt($auth),
            'messages' => $messages,
            'tools' => $tools,
        ]);
        $content = is_array($data['content'] ?? null) ? $data['content'] : [];
        $toolResults = [];
        $text = '';
        foreach ($content as $part) {
            if (($part['type'] ?? '') === 'text') $text .= (string)($part['text'] ?? '');
            if (($part['type'] ?? '') === 'tool_use') {
                $result = epl_ai_execute_tool($auth, (string)$part['name'], is_array($part['input'] ?? null) ? $part['input'] : []);
                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => (string)$part['id'],
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
            }
        }
        if (!$toolResults) return trim($text) ?: 'No encontre informacion para responder.';
        $messages[] = ['role' => 'assistant', 'content' => $content];
        $messages[] = ['role' => 'user', 'content' => $toolResults];
    }
    return 'La consulta necesita demasiados pasos. Intenta pedir una cosa a la vez.';
}

function epl_ai_gemini(array $auth, array $history): string {
    $key = epl_env('GEMINI_API_KEY', '');
    if ($key === '') throw new RuntimeException('Gemini aun no esta configurado.');
    $contents = array_map(static fn(array $m): array => [
        'role' => $m['role'] === 'assistant' ? 'model' : 'user',
        'parts' => [['text' => (string)$m['content']]],
    ], $history);
    $declarations = array_map(static fn(array $t): array => [
        'name' => $t['name'],
        'description' => $t['description'],
        'parameters' => $t['inputSchema'],
    ], epl_ai_tools_for($auth));
    $model = rawurlencode(epl_env('GEMINI_MODEL', 'gemini-2.5-flash'));
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . rawurlencode($key);

    for ($round = 0; $round < EPL_AI_MAX_TOOL_ROUNDS; $round++) {
        $data = epl_ai_http_json($url, [], [
            'systemInstruction' => ['parts' => [['text' => epl_ai_system_prompt($auth)]]],
            'contents' => $contents,
            'tools' => [['functionDeclarations' => $declarations]],
            'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 1200],
        ]);
        $candidate = $data['candidates'][0]['content'] ?? [];
        $parts = is_array($candidate['parts'] ?? null) ? $candidate['parts'] : [];
        $responses = [];
        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text'])) $text .= (string)$part['text'];
            if (is_array($part['functionCall'] ?? null)) {
                $call = $part['functionCall'];
                $name = (string)($call['name'] ?? '');
                $result = epl_ai_execute_tool($auth, $name, is_array($call['args'] ?? null) ? $call['args'] : []);
                $responses[] = ['functionResponse' => ['name' => $name, 'response' => ['result' => $result]]];
            }
        }
        if (!$responses) return trim($text) ?: 'No encontre informacion para responder.';
        $contents[] = ['role' => 'model', 'parts' => $parts];
        $contents[] = ['role' => 'user', 'parts' => $responses];
    }
    return 'La consulta necesita demasiados pasos. Intenta pedir una cosa a la vez.';
}

function epl_ai_markdown_data(mixed $data): string {
    if (is_string($data)) return $data;
    if (!is_array($data)) return 'No encontre informacion.';
    if (!$data) return 'No encontre registros con esos filtros.';
    if (isset($data['ok']) && $data['ok'] === false) return 'No pude completar la consulta: ' . ($data['error'] ?? 'error desconocido');
    $isList = array_keys($data) === range(0, count($data) - 1);
    $rows = $isList ? $data : [$data];
    $lines = [];
    foreach (array_slice($rows, 0, 20) as $row) {
        if (!is_array($row)) { $lines[] = '- ' . (string)$row; continue; }
        if (isset($row['local'], $row['visitante'])) {
            $fecha = !empty($row['fecha_programada']) ? date('d/m/Y H:i', strtotime((string)$row['fecha_programada'])) : 'sin fecha';
            $lines[] = '- **' . $row['local'] . ' vs ' . $row['visitante'] . '** — ' . $fecha
                . ' · ' . ($row['recinto'] ?: 'recinto pendiente') . ' · ' . ($row['estado'] ?? '');
        } elseif (isset($row['nombre'])) {
            $lines[] = '- **' . $row['nombre'] . '**' . (isset($row['estado']) ? ' — ' . $row['estado'] : '');
        } else {
            $lines[] = '- ' . implode(' · ', array_map(static fn($k, $v) => $k . ': ' . (is_scalar($v) || $v === null ? (string)$v : json_encode($v)), array_keys($row), $row));
        }
    }
    return implode("\n", $lines);
}

function epl_ai_direct(array $auth, string $message): string {
    $q = mb_strtolower($message, 'UTF-8');
    $tool = 'buscar_partidos';
    $args = ['limite' => 20];
    $heading = 'Estos son los partidos que encontre:';
    if (preg_match('/\b(quien soy|quién soy|mi cuenta|mis permisos)\b/u', $q)) {
        $tool = 'quien_soy'; $args = []; $heading = 'Tu cuenta conectada:';
    } elseif (preg_match('/\b(ligas|torneos)\b/u', $q) && !str_contains($q, 'partido')) {
        $tool = 'listar_ligas'; $args = []; $heading = 'Estas son tus ligas autorizadas:';
    } elseif (preg_match('/\b(reprogram|suspend)\w*/u', $q)) {
        $tool = 'ver_reprogramaciones'; $args = ['limite' => 50]; $heading = 'Estas son las reprogramaciones autorizadas:';
    } elseif (preg_match('/\b(recintos?|canchas?|sedes?)\b/u', $q) && $auth['rol'] === 'admin') {
        $tool = 'listar_recintos'; $args = []; $heading = 'Estos son los recintos activos:';
    } elseif (preg_match('/\bpartido\s*(?:id\s*)?#?(\d+)\b/u', $q, $match)) {
        $tool = 'ver_partido'; $args = ['partido_id' => (int)$match[1]]; $heading = 'Detalle del partido:';
    } else {
        if (preg_match('/\bpendiente(s)?\b/u', $q)) $args['estado'] = 'pendiente';
        elseif (preg_match('/\b(reprogramado|reprogramados)\b/u', $q)) $args['estado'] = 'reprogramado';
        elseif (preg_match('/\b(jugado|jugados|resultado|resultados)\b/u', $q)) $args['estado'] = 'jugado';
        if (str_contains($q, 'hoy')) $args['desde'] = $args['hasta'] = date('Y-m-d');
        if (str_contains($q, 'manana') || str_contains($q, 'mañana')) $args['desde'] = $args['hasta'] = date('Y-m-d', strtotime('+1 day'));
    }
    $result = epl_ai_tool_payload(epl_mcp_call($auth, $tool, $args));
    return $heading . "\n\n" . epl_ai_markdown_data($result);
}

function epl_ai_reply(array $auth, string $provider, array $history): array {
    $providers = epl_ai_providers();
    if (!isset($providers[$provider]) || !$providers[$provider]['ready']) $provider = 'epl';
    $latest = (string)($history[array_key_last($history)]['content'] ?? '');
    if ($provider === 'claude') $text = epl_ai_claude($auth, $history);
    elseif ($provider === 'gemini') $text = epl_ai_gemini($auth, $history);
    else $text = epl_ai_direct($auth, $latest);
    return ['provider' => $provider, 'text' => $text, 'pending' => epl_ai_pending($auth)];
}
