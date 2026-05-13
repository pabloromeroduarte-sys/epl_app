<?php
/**
 * setup_local.php — Setup completo para XAMPP local
 *
 * Abre en el navegador: http://localhost/elitepadelleague/migration/setup_local.php
 *
 * Este script:
 *   1. Crea la base de datos epleague
 *   2. Crea todas las tablas (schema.sql)
 *   3. Crea el admin inicial (admin@epleague.cl / Admin2026!)
 *   4. Verifica que todo quedó bien
 */

set_time_limit(60);
header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$user = 'root';
$pass = '';     // XAMPP por defecto no tiene contraseña

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Setup EPL Local</title>
<style>
  body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem;max-width:700px;margin:0 auto}
  h1{color:#C9A762;font-size:1.5rem;margin-bottom:.5rem}
  .ok{color:#22c55e}.err{color:#ef4444}.warn{color:#fbbf24}.info{color:#94a3b8}
  .step{padding:.25rem 0;font-size:.9rem}
  .box{background:#1e293b;border-radius:8px;padding:1.25rem;margin-top:1rem;font-size:.88rem}
  .big{font-size:1.1rem;font-weight:bold;margin-top:1.5rem}
  a{color:#C9A762}
  code{background:#0f172a;padding:.1rem .4rem;border-radius:4px;font-size:.85rem}
</style></head><body>';

echo '<h1>🏓 Setup Local — Elite Padel League</h1>';

function step(string $msg, string $cls = 'info'): void {
    echo "<div class='step $cls'>$msg</div>";
    flush();
}

// ── 1. Conectar a MySQL (sin BD) ────────────────────────
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    step('✓ Conexión a MySQL OK', 'ok');
} catch (PDOException $e) {
    step('✗ No se pudo conectar a MySQL: ' . $e->getMessage(), 'err');
    step('⟶ Asegúrate de que MySQL está corriendo en XAMPP', 'warn');
    die('</body></html>');
}

// ── 2. Crear base de datos ──────────────────────────────
$pdo->exec("CREATE DATABASE IF NOT EXISTS `epleague` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
step('✓ Base de datos <code>epleague</code> creada (o ya existía)', 'ok');

$pdo->exec("USE `epleague`");

// ── 3. Crear tablas ─────────────────────────────────────
$schemaFile = __DIR__ . '/schema.sql';
if (!file_exists($schemaFile)) {
    step('✗ No se encontró schema.sql', 'err');
    die('</body></html>');
}

// Parsear y ejecutar cada statement del schema
$sql = file_get_contents($schemaFile);
// Quitar líneas que empiezan con -- o CREATE DATABASE / USE
$lines = explode("\n", $sql);
$filtered = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    if (str_starts_with($trimmed, '--')) continue;
    if (str_starts_with($trimmed, 'CREATE DATABASE')) continue;
    if (str_starts_with($trimmed, 'USE ')) continue;
    $filtered[] = $line;
}
$sql = implode("\n", $filtered);

// Ejecutar por statements (split por ;)
$statements = array_filter(array_map('trim', explode(';', $sql)));
$tablesCreated = 0;
foreach ($statements as $st) {
    if (!$st) continue;
    try {
        $pdo->exec($st);
        if (stripos($st, 'CREATE TABLE') !== false) {
            preg_match('/CREATE TABLE IF NOT EXISTS `([^`]+)`/', $st, $m);
            step("  ✓ Tabla <code>{$m[1]}</code>", 'ok');
            $tablesCreated++;
        }
    } catch (PDOException $e) {
        step("  ⚠ " . $e->getMessage(), 'warn');
    }
}
step("✓ $tablesCreated tablas creadas", 'ok');

// ── 4. Admin inicial ────────────────────────────────────
$adminEmail = 'admin@epleague.cl';
$adminPass  = 'Admin2026!';

$exists = $pdo->prepare("SELECT id FROM jugadores WHERE email=?");
$exists->execute([$adminEmail]);
if (!$exists->fetch()) {
    $pdo->prepare("INSERT INTO jugadores (email,password,nombre,apellido,rol) VALUES (?,?,?,?,?)")
        ->execute([$adminEmail, password_hash($adminPass, PASSWORD_DEFAULT), 'Admin', 'EPL', 'admin']);
    step("✓ Admin creado: <code>$adminEmail</code> / <code>$adminPass</code>", 'ok');
} else {
    step("✓ Admin ya existe: <code>$adminEmail</code>", 'info');
}

// ── 5. Liga de prueba ───────────────────────────────────
$pdo->exec("INSERT IGNORE INTO ligas (id,nombre,temporada,categoria,estado) VALUES (1,'Liga EPL 5ta Apertura 2026','Apertura 2026',5,'activa')");
step("✓ Liga activa de prueba insertada", 'ok');

// ── 6. Verificar .env ───────────────────────────────────
$envFile = dirname(__DIR__) . '/.env';
$envContent = file_exists($envFile) ? file_get_contents($envFile) : '';
$envOk = str_contains($envContent, 'DB_NAME=epleague');
if (!$envOk) {
    // Actualizar .env
    $newEnv = preg_replace('/^DB_NAME=.*/m', 'DB_NAME=epleague', $envContent);
    $newEnv = preg_replace('/^DB_USER=.*/m', 'DB_USER=root', $newEnv);
    $newEnv = preg_replace('/^DB_PASS=.*/m', 'DB_PASS=', $newEnv);
    file_put_contents($envFile, $newEnv);
    step("✓ .env actualizado (DB_NAME=epleague, DB_USER=root, DB_PASS=)", 'ok');
} else {
    step("✓ .env ya está configurado", 'ok');
}

// ── 7. Verificación final ───────────────────────────────
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
step("✓ Tablas en epleague: " . implode(', ', $tables), 'info');

echo '<div class="box">';
echo '<p class="big ok">✓ Setup completado</p>';
echo '<p style="margin-top:1rem;color:#94a3b8">Credenciales de administrador:</p>';
echo "<p><strong style='color:#fff'>Email:</strong> <code>$adminEmail</code></p>";
echo "<p><strong style='color:#fff'>Contraseña:</strong> <code>$adminPass</code></p>";
echo '<hr style="border-color:#334155;margin:1rem 0">';
echo '<p style="color:#94a3b8;margin-bottom:.75rem">Próximos pasos:</p>';
echo '<p>1. <a href="../index.php">Ver el sitio →</a></p>';
echo '<p>2. <a href="../login.php">Iniciar sesión →</a></p>';
echo '<p>3. Para migrar datos reales: <a href="migrate.php">Ejecutar migración WP →</a></p>';
echo '</div>';

echo '</body></html>';
