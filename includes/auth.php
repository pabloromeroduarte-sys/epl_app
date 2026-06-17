<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

function epl_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = 10 * 365 * 24 * 60 * 60; // ~10 años (sesión permanente)
        ini_set('session.gc_maxlifetime', (string)$lifetime);
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
            'cookie_lifetime' => $lifetime,   // cookie persiste 30 días en el navegador
            'gc_maxlifetime'  => $lifetime,   // servidor no borra la sesión por inactividad
        ]);
    }
}

// ── Remember-me helpers ──────────────────────────────────────────────────────

function epl_remember_ensure_table(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        epl_db()->exec("CREATE TABLE IF NOT EXISTS `epl_remember_tokens` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `jugador_id` INT UNSIGNED NOT NULL,
            `token_hash` VARCHAR(64)  NOT NULL,
            `expires_at` DATETIME     NOT NULL,
            `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_token` (`token_hash`),
            KEY `idx_jugador` (`jugador_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('epl_remember_ensure_table: ' . $e->getMessage());
    }
}

function epl_remember_set(int $jugador_id): void {
    epl_remember_ensure_table();
    $token   = bin2hex(random_bytes(32));
    $hash    = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 30 * 24 * 3600); // 30 días
    $db      = epl_db();
    // Limpiar tokens expirados de este jugador
    $db->prepare("DELETE FROM epl_remember_tokens WHERE jugador_id = ? AND expires_at < NOW()")->execute([$jugador_id]);
    $db->prepare("INSERT INTO epl_remember_tokens (jugador_id, token_hash, expires_at) VALUES (?,?,?)")->execute([$jugador_id, $hash, $expires]);
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    setcookie('epl_remember', $token, [
        'expires'  => time() + 30 * 24 * 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);
}

function epl_remember_check(): ?array {
    $cookie = $_COOKIE['epl_remember'] ?? '';
    if (!$cookie) return null;
    epl_remember_ensure_table();
    $hash = hash('sha256', $cookie);
    $db   = epl_db();
    $st   = $db->prepare("SELECT rt.jugador_id FROM epl_remember_tokens rt WHERE rt.token_hash = ? AND rt.expires_at > NOW() LIMIT 1");
    $st->execute([$hash]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $jst = $db->prepare("SELECT * FROM jugadores WHERE id = ? AND estado = 'activo' LIMIT 1");
    $jst->execute([$row['jugador_id']]);
    $jrow = $jst->fetch(PDO::FETCH_ASSOC);
    if (!$jrow) return null;
    // Renovar token (rolling window)
    $db->prepare("DELETE FROM epl_remember_tokens WHERE token_hash = ?")->execute([$hash]);
    epl_session_start();
    session_regenerate_id(true);
    $_SESSION['jugador'] = epl_jugador_sesion_desde_fila($jrow);
    epl_remember_set((int)$jrow['id']); // nuevo token
    return $_SESSION['jugador'];
}

function epl_remember_delete(): void {
    $cookie = $_COOKIE['epl_remember'] ?? '';
    if (!$cookie) return;
    try {
        epl_remember_ensure_table();
        $hash = hash('sha256', $cookie);
        epl_db()->prepare("DELETE FROM epl_remember_tokens WHERE token_hash = ?")->execute([$hash]);
    } catch (Throwable $e) {}
    setcookie('epl_remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
}

// ─────────────────────────────────────────────────────────────────────────────

function epl_jugador_actual(): ?array {
    epl_session_start();
    if (!empty($_SESSION['jugador'])) {
        return $_SESSION['jugador'];
    }
    // Sin sesión activa → intentar auto-login desde cookie "Recordarme"
    return epl_remember_check();
}

/** Orden para elegir la fila "principal" cuando hay duplicados por email. */
function epl_jugador_order_sql(): string {
    return "(CASE WHEN rut IS NOT NULL AND rut != '' THEN 1 ELSE 0 END) DESC,
            (CASE WHEN telefono IS NOT NULL AND telefono != '' THEN 1 ELSE 0 END) DESC,
            (CASE WHEN fecha_nacimiento IS NOT NULL AND fecha_nacimiento != '' AND fecha_nacimiento != '0000-00-00' THEN 1 ELSE 0 END) DESC,
            id DESC";
}

/** Fila canónica en `jugadores` para un email (la más completa). */
function epl_jugador_por_email(string $email, bool $solo_activo = false): ?array {
    $db = epl_db();
    $email = strtolower(trim($email));
    $where = 'email = ?' . ($solo_activo ? " AND estado = 'activo'" : '');
    try {
        // Orden preferido: fila con más datos de perfil (puede fallar si las columnas
        // nuevas aún no existen en la BD — el catch usa fallback simple).
        $st = $db->prepare('SELECT * FROM jugadores WHERE ' . $where . ' ORDER BY ' . epl_jugador_order_sql() . ' LIMIT 1');
        $st->execute([$email]);
    } catch (PDOException $e) {
        // Columnas nuevas aún no migradas → fallback a id DESC
        $st = $db->prepare('SELECT * FROM jugadores WHERE ' . $where . ' ORDER BY id DESC LIMIT 1');
        $st->execute([$email]);
    }
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    if (($row['fecha_nacimiento'] ?? '') === '0000-00-00') {
        $row['fecha_nacimiento'] = '';
    }
    return $row;
}

function epl_jugador_sesion_desde_fila(array $row): array {
    return [
        'id'                   => (int)$row['id'],
        'email'                => $row['email'],
        'nombre'               => $row['nombre'],
        'apellido'             => $row['apellido'],
        'foto'                 => $row['foto'] ?? null,
        'rol'                  => $row['rol'],
        'must_change_password' => (int)($row['must_change_password'] ?? 0),
    ];
}

/** Alinea la sesión con la fila canónica del email (misma que usa el admin). */
function epl_sync_jugador_sesion(): void {
    $sess = epl_jugador_actual();
    if (!$sess || empty($sess['email'])) {
        return;
    }
    $canon = epl_jugador_por_email($sess['email'], false);
    if (!$canon) {
        return;
    }
    $nueva = epl_jugador_sesion_desde_fila($canon);
    if ($nueva['id'] !== (int)$sess['id']
        || $nueva['nombre'] !== ($sess['nombre'] ?? '')
        || $nueva['apellido'] !== ($sess['apellido'] ?? '')
        || ($nueva['foto'] ?? null) !== ($sess['foto'] ?? null)
        || $nueva['rol'] !== ($sess['rol'] ?? '')) {
        epl_session_start();
        $_SESSION['jugador'] = $nueva;
    }
}

/** Perfil completo del jugador logueado (siempre la fila canónica de `jugadores`). */
function epl_jugador_db(): ?array {
    $sess = epl_jugador_actual();
    if (!$sess || empty($sess['email'])) {
        return null;
    }
    return epl_jugador_por_email($sess['email'], false);
}

function epl_require_login(): void {
    if (!epl_jugador_actual()) {
        $back = urlencode($_SERVER['REQUEST_URI']);
        header('Location: ' . epl_url("login.php?back=$back"));
        exit;
    }
    epl_sync_jugador_sesion();
    // Contraseña temporal — forzar cambio
    $sess = epl_jugador_actual();
    if (!empty($sess['must_change_password'])) {
        $cur = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        if (!in_array($cur, ['cambiar_password.php', 'logout.php'], true)) {
            header('Location: ' . epl_url('cambiar_password.php'));
            exit;
        }
    }
    // Un club no usa el área de jugador → llevarlo a su panel
    if (($sess['rol'] ?? '') === 'club') {
        $cur = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        if (!in_array($cur, ['cambiar_password.php', 'logout.php'], true)) {
            header('Location: ' . epl_url('club/resultados.php'));
            exit;
        }
    }
}

function epl_require_admin(): void {
    $j = epl_jugador_actual();
    if (!$j || $j['rol'] !== 'admin') {
        http_response_code(403);
        header('Location: ' . epl_url('dashboard.php'));
        exit;
    }
}

/** Solo deja pasar al rol 'club'. Resto → su área correspondiente o login. */
function epl_require_club(): void {
    $j = epl_jugador_actual();
    if (!$j) {
        $back = urlencode($_SERVER['REQUEST_URI']);
        header('Location: ' . epl_url("login.php?back=$back"));
        exit;
    }
    if (($j['rol'] ?? '') !== 'club') {
        header('Location: ' . epl_url(($j['rol'] ?? '') === 'admin' ? 'admin/index.php' : 'dashboard.php'));
        exit;
    }
    epl_sync_jugador_sesion();
    $sess = epl_jugador_actual();
    if (!empty($sess['must_change_password'])) {
        $cur = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        if (!in_array($cur, ['cambiar_password.php', 'logout.php'], true)) {
            header('Location: ' . epl_url('cambiar_password.php'));
            exit;
        }
    }
}

function epl_login(string $email, string $password, bool $remember = false): bool {
    $db = epl_db();
    $email = strtolower(trim($email));
    $st = $db->prepare("SELECT * FROM jugadores WHERE email = ? AND estado = 'activo'");
    $st->execute([$email]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $match = null;
    foreach ($rows as $row) {
        if (!empty($row['password']) && password_verify($password, $row['password'])) {
            $match = $row;
            break;
        }
    }
    if (!$match) {
        return false;
    }

    $canon = epl_jugador_por_email($email, true) ?? $match;
    if ((int)$canon['id'] !== (int)$match['id']) {
        $db->prepare('UPDATE jugadores SET password = ? WHERE id = ?')
           ->execute([$match['password'], $canon['id']]);
    }

    epl_session_start();
    session_regenerate_id(true);
    $_SESSION['jugador'] = epl_jugador_sesion_desde_fila($canon);
    if ($remember) {
        epl_remember_set((int)$canon['id']);
    }
    return true;
}

function epl_logout(): void {
    epl_session_start();
    epl_remember_delete(); // borrar token "Recordarme"
    session_destroy();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function epl_hash_password(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Genera una contraseña temporal aleatoria fácil de leer.
 * Excluye caracteres confusos (0, O, 1, I, l) para evitar errores al transcribir.
 */
function epl_generar_password_temporal(int $largo = 10): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $pwd = '';
    $n = strlen($chars);
    for ($i = 0; $i < $largo; $i++) {
        $pwd .= $chars[random_int(0, $n - 1)];
    }
    return $pwd;
}

/**
 * Asigna una contraseña temporal a un jugador y lo marca para forzar el cambio.
 * Retorna la contraseña en texto plano (para enviarla por email).
 */
function epl_asignar_password_temporal(int $jugador_id): string {
    $db = epl_db();
    $pwd = epl_generar_password_temporal();
    $db->prepare("UPDATE jugadores SET password=?, must_change_password=1 WHERE id=?")
       ->execute([epl_hash_password($pwd), $jugador_id]);
    return $pwd;
}

// Genera token para reset de contraseña
function epl_generar_reset_token(string $email): ?string {
    $db = epl_db();
    $st = $db->prepare("SELECT id FROM jugadores WHERE email = ? AND estado = 'activo'");
    $st->execute([strtolower(trim($email))]);
    if (!$st->fetch()) return null;

    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);
    $db->prepare("UPDATE jugadores SET reset_token = ?, reset_token_expires = ? WHERE email = ?")
       ->execute([$token, $expires, strtolower(trim($email))]);
    return $token;
}
