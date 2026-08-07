<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

const EPL_MCP_PROTOCOL_VERSION = '2025-06-18';

function epl_mcp_base_url(): string {
    $app = rtrim(epl_env('APP_URL', ''), '/');
    if ($app !== '') return $app . '/mcp';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/mcp';
}

function epl_mcp_url(string $path = ''): string {
    return epl_mcp_base_url() . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function epl_mcp_ensure_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = epl_db();
    $mcpCol = $db->query("SHOW COLUMNS FROM jugadores LIKE 'mcp_habilitado'")->fetch();
    if (!$mcpCol) {
        $db->exec("ALTER TABLE jugadores ADD COLUMN mcp_habilitado TINYINT(1) NOT NULL DEFAULT 0 AFTER rol");
        $db->exec("UPDATE jugadores SET mcp_habilitado=1 WHERE rol='admin' AND estado='activo'");
    }
    $db->exec("CREATE TABLE IF NOT EXISTS mcp_oauth_clients (
        client_id VARCHAR(96) NOT NULL,
        client_name VARCHAR(190) NOT NULL,
        redirect_uris TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS mcp_oauth_codes (
        code_hash CHAR(64) NOT NULL,
        client_id VARCHAR(96) NOT NULL,
        jugador_id INT UNSIGNED NOT NULL,
        redirect_uri VARCHAR(500) NOT NULL,
        scope VARCHAR(255) NOT NULL,
        code_challenge VARCHAR(128) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (code_hash), KEY idx_code_exp (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS mcp_oauth_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        token_hash CHAR(64) NOT NULL,
        refresh_hash CHAR(64) NULL,
        client_id VARCHAR(96) NOT NULL,
        jugador_id INT UNSIGNED NOT NULL,
        scope VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        refresh_expires_at DATETIME NULL,
        revoked_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME NULL,
        PRIMARY KEY (id), UNIQUE KEY uk_access (token_hash), UNIQUE KEY uk_refresh (refresh_hash),
        KEY idx_token_user (jugador_id), KEY idx_token_exp (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS mcp_audit_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        jugador_id INT UNSIGNED NOT NULL,
        client_id VARCHAR(96) NOT NULL,
        tool_name VARCHAR(100) NOT NULL,
        target_type VARCHAR(50) NULL,
        target_id INT UNSIGNED NULL,
        success TINYINT(1) NOT NULL DEFAULT 1,
        input_json JSON NULL,
        before_json JSON NULL,
        after_json JSON NULL,
        error_message VARCHAR(500) NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY idx_audit_user (jugador_id, created_at), KEY idx_audit_tool (tool_name, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function epl_mcp_json(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function epl_mcp_b64url(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function epl_mcp_redirect_uri_ok(string $uri): bool {
    $p = parse_url($uri);
    if (!$p || empty($p['scheme']) || empty($p['host'])) return false;
    if ($p['scheme'] === 'https') return true;
    return $p['scheme'] === 'http' && in_array(strtolower($p['host']), ['localhost', '127.0.0.1', '::1'], true);
}

function epl_mcp_client(string $clientId): ?array {
    epl_mcp_ensure_schema();
    $st = epl_db()->prepare('SELECT * FROM mcp_oauth_clients WHERE client_id=?');
    $st->execute([$clientId]);
    return $st->fetch() ?: null;
}

function epl_mcp_client_redirect_ok(array $client, string $redirectUri): bool {
    $uris = json_decode((string)$client['redirect_uris'], true);
    return is_array($uris) && in_array($redirectUri, $uris, true);
}

function epl_mcp_bearer(): string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    return preg_match('/^Bearer\s+(.+)$/i', trim($header), $m) ? trim($m[1]) : '';
}

function epl_mcp_require_token(): array {
    epl_mcp_ensure_schema();
    $token = epl_mcp_bearer();
    if ($token === '') epl_mcp_auth_challenge();
    $st = epl_db()->prepare("SELECT t.*, j.email, j.nombre, j.apellido, j.rol, j.estado, j.mcp_habilitado
        FROM mcp_oauth_tokens t JOIN jugadores j ON j.id=t.jugador_id
        WHERE t.token_hash=? AND t.revoked_at IS NULL AND t.expires_at > NOW() AND j.estado='activo' AND j.mcp_habilitado=1 LIMIT 1");
    $st->execute([hash('sha256', $token)]);
    $row = $st->fetch();
    if (!$row) epl_mcp_auth_challenge('invalid_token');
    epl_db()->prepare('UPDATE mcp_oauth_tokens SET last_used_at=NOW() WHERE id=?')->execute([$row['id']]);
    return $row;
}

function epl_mcp_auth_challenge(string $error = ''): never {
    $meta = epl_mcp_url('.well-known/oauth-protected-resource.php');
    $value = 'Bearer resource_metadata="' . $meta . '"';
    if ($error !== '') $value .= ', error="' . $error . '"';
    header('WWW-Authenticate: ' . $value);
    epl_mcp_json(['error' => $error ?: 'authorization_required'], 401);
}

/** @return int[] */
function epl_mcp_liga_ids(array $auth): array {
    $db = epl_db();
    if ($auth['rol'] === 'admin') {
        return array_map('intval', $db->query('SELECT id FROM ligas ORDER BY id DESC')->fetchAll(PDO::FETCH_COLUMN));
    }
    if ($auth['rol'] === 'club') return epl_club_ligas((int)$auth['jugador_id']);
    $st = $db->prepare("SELECT DISTINCT le.liga_id FROM liga_equipos le JOIN equipos e ON e.id=le.equipo_id
        WHERE e.jugador1_id=? OR e.jugador2_id=? ORDER BY le.liga_id DESC");
    $st->execute([$auth['jugador_id'], $auth['jugador_id']]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function epl_mcp_can_liga(array $auth, int $ligaId): bool {
    return $ligaId > 0 && in_array($ligaId, epl_mcp_liga_ids($auth), true);
}

function epl_mcp_can_partido(array $auth, array $partido): bool {
    if ($auth['rol'] === 'admin') return true;
    if (!epl_mcp_can_liga($auth, (int)$partido['liga_id'])) return false;
    if ($auth['rol'] === 'club') return true;
    $st = epl_db()->prepare('SELECT 1 FROM equipos WHERE id IN (?,?) AND (jugador1_id=? OR jugador2_id=?) LIMIT 1');
    $st->execute([$partido['equipo_local_id'], $partido['equipo_visitante_id'], $auth['jugador_id'], $auth['jugador_id']]);
    return (bool)$st->fetchColumn();
}

function epl_mcp_partido(int $id): ?array {
    $st = epl_db()->prepare("SELECT p.*, l.nombre liga_nombre, el.nombre local_nombre, ev.nombre visitante_nombre,
        r.nombre recinto_nombre, rs.nombre recinto_superior
        FROM partidos p JOIN ligas l ON l.id=p.liga_id JOIN equipos el ON el.id=p.equipo_local_id
        JOIN equipos ev ON ev.id=p.equipo_visitante_id LEFT JOIN recintos r ON r.id=p.recinto_id
        LEFT JOIN recintos rs ON rs.id=r.superior_id WHERE p.id=?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function epl_mcp_audit(array $auth, string $tool, array $input, bool $success, ?string $targetType = null,
    ?int $targetId = null, ?array $before = null, ?array $after = null, ?string $error = null): void {
    try {
        $st = epl_db()->prepare("INSERT INTO mcp_audit_log
            (jugador_id,client_id,tool_name,target_type,target_id,success,input_json,before_json,after_json,error_message,ip_address)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $enc = fn($v) => $v === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $st->execute([$auth['jugador_id'], $auth['client_id'], $tool, $targetType, $targetId, $success ? 1 : 0,
            $enc($input), $enc($before), $enc($after), $error, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) { error_log('MCP audit: ' . $e->getMessage()); }
}

function epl_mcp_text_result(mixed $data, bool $error = false): array {
    return ['content' => [['type' => 'text', 'text' => is_string($data) ? $data : json_encode($data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]], 'isError' => $error];
}

function epl_mcp_tools(): array {
    $obj = ['type' => 'object', 'additionalProperties' => false];
    return [
        ['name'=>'quien_soy','description'=>'Muestra la cuenta EPL conectada, su rol y capacidades.','inputSchema'=>$obj,
            'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]],
        ['name'=>'listar_ligas','description'=>'Lista únicamente las ligas que la cuenta conectada puede consultar.','inputSchema'=>$obj,
            'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]],
        ['name'=>'buscar_partidos','description'=>'Busca partidos autorizados por liga, estado, fechas o nombre de pareja/jugador.',
            'inputSchema'=>['type'=>'object','additionalProperties'=>false,'properties'=>[
                'liga_id'=>['type'=>'integer'],'estado'=>['type'=>'string','enum'=>['pendiente','reprogramado','jugado','walkover','no_presentado']],
                'desde'=>['type'=>'string','description'=>'Fecha YYYY-MM-DD'],'hasta'=>['type'=>'string','description'=>'Fecha YYYY-MM-DD'],
                'buscar'=>['type'=>'string'],'limite'=>['type'=>'integer','minimum'=>1,'maximum'=>100]
            ]],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]],
        ['name'=>'ver_partido','description'=>'Obtiene todos los detalles autorizados de un partido por su ID.',
            'inputSchema'=>['type'=>'object','additionalProperties'=>false,'required'=>['partido_id'],'properties'=>['partido_id'=>['type'=>'integer']]],
            'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]],
        ['name'=>'ver_reprogramaciones','description'=>'Lista reprogramaciones y solicitudes pendientes. Admin ve todas; jugador solo las propias; club solo sus ligas.',
            'inputSchema'=>['type'=>'object','additionalProperties'=>false,'properties'=>['liga_id'=>['type'=>'integer'],'limite'=>['type'=>'integer','minimum'=>1,'maximum'=>100]]],
            'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]],
        ['name'=>'listar_recintos','description'=>'Lista recintos disponibles. Solo administradores.', 'inputSchema'=>$obj,
            'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]],
        ['name'=>'solicitar_reprogramacion','description'=>'Crea una solicitud de reprogramación para un partido propio. Requiere confirmación explícita.',
            'inputSchema'=>['type'=>'object','additionalProperties'=>false,'required'=>['partido_id','motivo','confirmar'],'properties'=>[
                'partido_id'=>['type'=>'integer'],'motivo'=>['type'=>'string','minLength'=>3,'maxLength'=>500],
                'fecha_propuesta'=>['type'=>'string','description'=>'YYYY-MM-DD HH:MM; opcional si el rival no respondió'],
                'rival_no_responde'=>['type'=>'boolean'],'mutuo_acuerdo'=>['type'=>'boolean'],
                'confirmar'=>['type'=>'boolean','description'=>'Debe ser true después de que el usuario confirme el resumen']
            ]],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>false,'idempotentHint'=>false]],
        ['name'=>'administrar_partido','description'=>'Modifica fecha, recinto, estado o alerta de un partido. Exclusivo para administradores y requiere confirmación.',
            'inputSchema'=>['type'=>'object','additionalProperties'=>false,'required'=>['partido_id','confirmar'],'properties'=>[
                'partido_id'=>['type'=>'integer'],'fecha_programada'=>['type'=>['string','null'],'description'=>'YYYY-MM-DD HH:MM o null para dejar sin fecha'],
                'recinto_id'=>['type'=>['integer','null']],'estado'=>['type'=>'string','enum'=>['pendiente','reprogramado','jugado','walkover','no_presentado']],
                'alerta_admin'=>['type'=>['string','null'],'maxLength'=>500], 'confirmar'=>['type'=>'boolean']
            ]],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>false,'idempotentHint'=>true]],
    ];
}

function epl_mcp_call(array $auth, string $tool, array $a): array {
    $db = epl_db();
    try {
        if ($tool === 'quien_soy') {
            return epl_mcp_text_result(['id'=>(int)$auth['jugador_id'],'nombre'=>trim($auth['nombre'].' '.$auth['apellido']),
                'email'=>$auth['email'],'rol'=>$auth['rol'],'ligas_permitidas'=>epl_mcp_liga_ids($auth)]);
        }
        if ($tool === 'listar_ligas') {
            $ids = epl_mcp_liga_ids($auth);
            if (!$ids) return epl_mcp_text_result([]);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $st = $db->prepare("SELECT id,nombre,estado,tipo,sexo,fecha_inicio,fecha_fin FROM ligas WHERE id IN ($ph) ORDER BY id DESC");
            $st->execute($ids);
            return epl_mcp_text_result($st->fetchAll());
        }
        if ($tool === 'buscar_partidos') {
            $ids = epl_mcp_liga_ids($auth);
            if (isset($a['liga_id'])) {
                $lid=(int)$a['liga_id']; if (!in_array($lid,$ids,true)) throw new RuntimeException('No tienes acceso a esa liga.'); $ids=[$lid];
            }
            if (!$ids) return epl_mcp_text_result([]);
            $ph=implode(',',array_fill(0,count($ids),'?')); $params=$ids;
            $where="p.liga_id IN ($ph)";
            if ($auth['rol']==='jugador') { $where.=' AND (el.jugador1_id=? OR el.jugador2_id=? OR ev.jugador1_id=? OR ev.jugador2_id=?)'; array_push($params,...array_fill(0,4,$auth['jugador_id'])); }
            if (!empty($a['estado'])) { $where.=' AND p.estado=?'; $params[]=$a['estado']; }
            if (!empty($a['desde'])) { $where.=' AND p.fecha_programada>=?'; $params[]=$a['desde'].' 00:00:00'; }
            if (!empty($a['hasta'])) { $where.=' AND p.fecha_programada<=?'; $params[]=$a['hasta'].' 23:59:59'; }
            if (!empty($a['buscar'])) { $where.=' AND (el.nombre LIKE ? OR ev.nombre LIKE ? OR CONCAT(jl1.nombre," ",jl1.apellido) LIKE ? OR CONCAT(jl2.nombre," ",jl2.apellido) LIKE ? OR CONCAT(jv1.nombre," ",jv1.apellido) LIKE ? OR CONCAT(jv2.nombre," ",jv2.apellido) LIKE ?)'; $q='%'.trim($a['buscar']).'%'; array_push($params,$q,$q,$q,$q,$q,$q); }
            $limit=max(1,min(100,(int)($a['limite']??30)));
            $st=$db->prepare("SELECT p.id,p.liga_id,l.nombre liga,p.jornada,p.nombre_fecha,p.fecha_programada,p.estado,
                el.nombre local,ev.nombre visitante,r.nombre recinto,p.sets_local,p.sets_visitante
                FROM partidos p JOIN ligas l ON l.id=p.liga_id JOIN equipos el ON el.id=p.equipo_local_id JOIN equipos ev ON ev.id=p.equipo_visitante_id
                LEFT JOIN jugadores jl1 ON jl1.id=el.jugador1_id LEFT JOIN jugadores jl2 ON jl2.id=el.jugador2_id
                LEFT JOIN jugadores jv1 ON jv1.id=ev.jugador1_id LEFT JOIN jugadores jv2 ON jv2.id=ev.jugador2_id
                LEFT JOIN recintos r ON r.id=p.recinto_id WHERE $where ORDER BY p.fecha_programada IS NULL,p.fecha_programada,p.jornada LIMIT $limit");
            $st->execute($params); return epl_mcp_text_result($st->fetchAll());
        }
        if ($tool === 'ver_partido') {
            $p=epl_mcp_partido((int)($a['partido_id']??0));
            if (!$p || !epl_mcp_can_partido($auth,$p)) throw new RuntimeException('Partido no encontrado o no autorizado.');
            return epl_mcp_text_result($p);
        }
        if ($tool === 'ver_reprogramaciones') {
            $ids=epl_mcp_liga_ids($auth); if(isset($a['liga_id'])){$lid=(int)$a['liga_id'];if(!in_array($lid,$ids,true))throw new RuntimeException('No tienes acceso a esa liga.');$ids=[$lid];}
            if(!$ids)return epl_mcp_text_result([]);$ph=implode(',',array_fill(0,count($ids),'?'));$params=$ids;
            $where="p.liga_id IN ($ph) AND (p.estado='reprogramado' OR sr.estado='pendiente')";
            if($auth['rol']==='jugador'){$where.=' AND (sr.solicitante_id=? OR el.jugador1_id=? OR el.jugador2_id=? OR ev.jugador1_id=? OR ev.jugador2_id=?)';array_push($params,...array_fill(0,5,$auth['jugador_id']));}
            $limit=max(1,min(100,(int)($a['limite']??50)));
            $st=$db->prepare("SELECT p.id partido_id,l.nombre liga,el.nombre local,ev.nombre visitante,p.fecha_original,p.fecha_programada,p.estado partido_estado,
                sr.id solicitud_id,sr.motivo,sr.fecha_propuesta,sr.estado solicitud_estado,sr.rival_no_responde,sr.mutuo_acuerdo,sr.created_at
                FROM partidos p JOIN ligas l ON l.id=p.liga_id JOIN equipos el ON el.id=p.equipo_local_id JOIN equipos ev ON ev.id=p.equipo_visitante_id
                LEFT JOIN solicitudes_reprogramacion sr ON sr.id=(SELECT MAX(s2.id) FROM solicitudes_reprogramacion s2 WHERE s2.partido_id=p.id)
                WHERE $where ORDER BY COALESCE(sr.created_at,p.fecha_programada) DESC LIMIT $limit");
            $st->execute($params);return epl_mcp_text_result($st->fetchAll());
        }
        if ($tool === 'listar_recintos') {
            if($auth['rol']!=='admin')throw new RuntimeException('Esta herramienta es exclusiva para administradores.');
            return epl_mcp_text_result($db->query('SELECT id,nombre,direccion,superior_id,activo FROM recintos WHERE activo=1 ORDER BY nombre')->fetchAll());
        }
        if ($tool === 'solicitar_reprogramacion') {
            if(empty($a['confirmar']))throw new RuntimeException('Falta confirmación. Resume el cambio y pide al usuario que lo confirme.');
            $pid=(int)($a['partido_id']??0);$p=epl_mcp_partido($pid);
            if(!$p||!epl_mcp_can_partido($auth,$p)||$auth['rol']==='club')throw new RuntimeException('Partido no autorizado para solicitar reprogramación.');
            $motivo=trim((string)($a['motivo']??''));if(mb_strlen($motivo)<3)throw new RuntimeException('Debes indicar un motivo.');
            $sinResp=!empty($a['rival_no_responde']);$acuerdo=!empty($a['mutuo_acuerdo']);$fecha=trim((string)($a['fecha_propuesta']??''));
            if(!$sinResp&&$fecha==='')throw new RuntimeException('Debes proponer fecha o indicar que el rival no respondió.');
            if(!$sinResp&&!$acuerdo)throw new RuntimeException('Para proponer fecha debes confirmar que existe mutuo acuerdo.');
            if($fecha!==''&&strtotime($fecha)===false)throw new RuntimeException('La fecha propuesta no es válida.');
            $before=$p;$db->beginTransaction();
            $estadoSol=(!$sinResp&&$acuerdo&&$fecha!=='')?'aprobada':'pendiente';
            $db->prepare("INSERT INTO solicitudes_reprogramacion(partido_id,solicitante_id,motivo,fecha_propuesta,rival_no_responde,mutuo_acuerdo,estado,fecha_aprobada) VALUES(?,?,?,?,?,?,?,?)")
                ->execute([$pid,$auth['jugador_id'],$motivo,$fecha?:null,$sinResp?1:0,$acuerdo?1:0,$estadoSol,$estadoSol==='aprobada'?$fecha:null]);
            $db->prepare("UPDATE partidos SET estado='reprogramado',fecha_original=IFNULL(fecha_original,fecha_programada),recinto_original_id=IFNULL(recinto_original_id,recinto_id),fecha_programada=NULL,baja_token=NULL,baja_solicitada_at=NULL,baja_confirmada_at=NULL,baja_confirmada_por=NULL,cancha_token=NULL,cancha_solicitada_at=NULL,cancha_confirmada_at=NULL,cancha_confirmada_por=NULL WHERE id=?")->execute([$pid]);
            $db->commit();$after=epl_mcp_partido($pid);epl_mcp_audit($auth,$tool,$a,true,'partido',$pid,$before,$after);
            return epl_mcp_text_result(['ok'=>true,'mensaje'=>'Solicitud de reprogramación creada.','partido'=>$after]);
        }
        if ($tool === 'administrar_partido') {
            if($auth['rol']!=='admin')throw new RuntimeException('Esta herramienta es exclusiva para administradores.');
            if(empty($a['confirmar']))throw new RuntimeException('Falta confirmación. Resume exactamente los cambios y pide confirmación.');
            $pid=(int)($a['partido_id']??0);$before=epl_mcp_partido($pid);if(!$before)throw new RuntimeException('Partido no encontrado.');
            $allowed=['fecha_programada','recinto_id','estado','alerta_admin'];$sets=[];$params=[];
            foreach($allowed as $k){if(array_key_exists($k,$a)){$v=$a[$k];if($k==='fecha_programada'&&$v!==null){if(strtotime((string)$v)===false)throw new RuntimeException('Fecha no válida.');$v=date('Y-m-d H:i:s',strtotime((string)$v));}if($k==='recinto_id'&&$v!==null){$chk=$db->prepare('SELECT 1 FROM recintos WHERE id=? AND activo=1');$chk->execute([(int)$v]);if(!$chk->fetchColumn())throw new RuntimeException('Recinto no válido.');$v=(int)$v;}$sets[]="$k=?";$params[]=$v;}}
            if(!$sets)throw new RuntimeException('No indicaste ningún cambio.');
            epl_partido_snapshot_original($pid);$params[]=$pid;$db->prepare('UPDATE partidos SET '.implode(',',$sets).' WHERE id=?')->execute($params);
            $after=epl_mcp_partido($pid);epl_mcp_audit($auth,$tool,$a,true,'partido',$pid,$before,$after);
            $fechaCambio=($before['fecha_programada']??null)!==($after['fecha_programada']??null);
            $recintoCambio=(int)($before['recinto_id']??0)!==(int)($after['recinto_id']??0);
            if($fechaCambio||$recintoCambio){
                $cambios=[];
                if($fechaCambio)$cambios[]=$after['fecha_programada']?'nueva fecha: '.date('d/m/Y H:i',strtotime($after['fecha_programada'])):'partido dejado sin fecha';
                if($recintoCambio)$cambios[]=$after['recinto_nombre']?'nuevo recinto: '.$after['recinto_nombre']:'recinto pendiente';
                epl_notif_partido($pid,'reprogramacion','📅 Cambio en tu partido',ucfirst(implode(' · ',$cambios)).'. Revisa los detalles en Mis Partidos.',epl_url('mis_torneos.php'),false,[],true);
            }
            return epl_mcp_text_result(['ok'=>true,'mensaje'=>'Partido actualizado.','antes'=>$before,'despues'=>$after]);
        }
        throw new RuntimeException('Herramienta desconocida.');
    } catch (Throwable $e) {
        if($db->inTransaction())$db->rollBack();
        epl_mcp_audit($auth,$tool,$a,false,isset($a['partido_id'])?'partido':null,isset($a['partido_id'])?(int)$a['partido_id']:null,null,null,$e->getMessage());
        return epl_mcp_text_result(['ok'=>false,'error'=>$e->getMessage()],true);
    }
}
