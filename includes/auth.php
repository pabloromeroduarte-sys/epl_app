<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function epl_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
        ]);
    }
}

function epl_jugador_actual(): ?array {
    epl_session_start();
    return $_SESSION['jugador'] ?? null;
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
    $st = $db->prepare('SELECT * FROM jugadores WHERE ' . $where . ' ORDER BY ' . epl_jugador_order_sql() . ' LIMIT 1');
    $st->execute([$email]);
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
        'id'       => (int)$row['id'],
        'email'    => $row['email'],
        'nombre'   => $row['nombre'],
        'apellido' => $row['apellido'],
        'foto'     => $row['foto'] ?? null,
        'rol'      => $row['rol'],
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
        header("Location: /elitepadelleague/login.php?back=$back");
        exit;
    }
    epl_sync_jugador_sesion();
}

function epl_require_admin(): void {
    $j = epl_jugador_actual();
    if (!$j || $j['rol'] !== 'admin') {
        http_response_code(403);
        header("Location: /elitepadelleague/dashboard.php");
        exit;
    }
}

function epl_login(string $email, string $password): bool {
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
    return true;
}

function epl_logout(): void {
    epl_session_start();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

function epl_hash_password(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
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
