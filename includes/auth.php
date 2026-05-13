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

function epl_require_login(): void {
    if (!epl_jugador_actual()) {
        $back = urlencode($_SERVER['REQUEST_URI']);
        header("Location: /elitepadelleague/login.php?back=$back");
        exit;
    }
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
    $st = $db->prepare("SELECT * FROM jugadores WHERE email = ? AND estado = 'activo' LIMIT 1");
    $st->execute([strtolower(trim($email))]);
    $jugador = $st->fetch();

    if (!$jugador || !password_verify($password, $jugador['password'])) {
        return false;
    }

    epl_session_start();
    session_regenerate_id(true);
    $_SESSION['jugador'] = [
        'id'       => $jugador['id'],
        'email'    => $jugador['email'],
        'nombre'   => $jugador['nombre'],
        'apellido' => $jugador['apellido'],
        'foto'     => $jugador['foto'],
        'rol'      => $jugador['rol'],
    ];
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
