<?php
/**
 * DEBUG — Examina estructura real de WP para entender cómo mapear jugadores a equipos
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
epl_require_admin();

$WP_PRE = 'wpqu_';
$wp = new PDO("mysql:host=localhost;dbname=wp_import;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Debug WP</title>
<style>body{font-family:monospace;background:#0a1421;color:#fff;padding:2rem;font-size:.82rem}
h2{color:#C9A762;margin-top:2rem}table{border-collapse:collapse;width:100%;max-width:900px}
td,th{border:1px solid #1C2F48;padding:.3rem .6rem;text-align:left}th{background:#1C2F48;color:#7dd3fc}
.ok{color:#86efac}.warn{color:#fbbf24}.dim{color:#475569}</style>
</head><body>

<h2>1. Primer sp_team y su postmeta</h2>
<?php
$team = $wp->query("SELECT ID, post_title FROM {$WP_PRE}posts WHERE post_type='sp_team' AND post_status!='trash' LIMIT 1")->fetch();
echo "<p class='ok'>Equipo: [{$team['ID']}] {$team['post_title']}</p>";
$meta = $wp->prepare("SELECT meta_key, meta_value FROM {$WP_PRE}postmeta WHERE post_id=? ORDER BY meta_key");
$meta->execute([$team['ID']]);
echo "<table><tr><th>meta_key</th><th>meta_value</th></tr>";
foreach ($meta->fetchAll() as $r) {
    $val = strlen($r['meta_value']) > 200 ? substr($r['meta_value'],0,200).'...' : $r['meta_value'];
    echo "<tr><td>{$r['meta_key']}</td><td>" . htmlspecialchars($val) . "</td></tr>";
}
echo "</table>";
?>

<h2>2. Primer sp_player y su postmeta</h2>
<?php
$player = $wp->query("SELECT ID, post_title, post_author FROM {$WP_PRE}posts WHERE post_type='sp_player' AND post_status!='trash' LIMIT 1")->fetch();
echo "<p class='ok'>Jugador post: [{$player['ID']}] {$player['post_title']} | post_author={$player['post_author']}</p>";
$meta2 = $wp->prepare("SELECT meta_key, meta_value FROM {$WP_PRE}postmeta WHERE post_id=? ORDER BY meta_key");
$meta2->execute([$player['ID']]);
echo "<table><tr><th>meta_key</th><th>meta_value</th></tr>";
foreach ($meta2->fetchAll() as $r) {
    $val = strlen($r['meta_value']) > 200 ? substr($r['meta_value'],0,200).'...' : $r['meta_value'];
    echo "<tr><td>{$r['meta_key']}</td><td>" . htmlspecialchars($val) . "</td></tr>";
}
echo "</table>";
?>

<h2>3. Usermeta con claves relacionadas a sp_player</h2>
<?php
$umeta = $wp->query("SELECT user_id, meta_key, meta_value FROM {$WP_PRE}usermeta
    WHERE meta_key LIKE '%player%' OR meta_key LIKE '%sp_%'
    LIMIT 20")->fetchAll();
echo "<table><tr><th>user_id</th><th>meta_key</th><th>meta_value</th></tr>";
foreach ($umeta as $r) {
    echo "<tr><td>{$r['user_id']}</td><td>{$r['meta_key']}</td><td>" . htmlspecialchars(substr($r['meta_value'],0,150)) . "</td></tr>";
}
echo "</table>";
?>

<h2>4. Todos los meta_keys distintos en postmeta de sp_player</h2>
<?php
$keys = $wp->query("
    SELECT DISTINCT pm.meta_key
    FROM {$WP_PRE}postmeta pm
    JOIN {$WP_PRE}posts p ON p.ID = pm.post_id
    WHERE p.post_type = 'sp_player'
    ORDER BY pm.meta_key
")->fetchAll(PDO::FETCH_COLUMN);
echo "<ul>";
foreach ($keys as $k) echo "<li>" . htmlspecialchars($k) . "</li>";
echo "</ul>";
?>

<h2>5. Todos los meta_keys distintos en postmeta de sp_team</h2>
<?php
$keys2 = $wp->query("
    SELECT DISTINCT pm.meta_key
    FROM {$WP_PRE}postmeta pm
    JOIN {$WP_PRE}posts p ON p.ID = pm.post_id
    WHERE p.post_type = 'sp_team'
    ORDER BY pm.meta_key
")->fetchAll(PDO::FETCH_COLUMN);
echo "<ul>";
foreach ($keys2 as $k) echo "<li>" . htmlspecialchars($k) . "</li>";
echo "</ul>";
?>

<h2>6. Muestra de wp_users (primeros 5)</h2>
<?php
$users = $wp->query("SELECT ID, user_login, user_email, display_name FROM {$WP_PRE}users LIMIT 5")->fetchAll();
echo "<table><tr><th>ID</th><th>login</th><th>email</th><th>display_name</th></tr>";
foreach ($users as $u) {
    echo "<tr><td>{$u['ID']}</td><td>{$u['user_login']}</td><td>{$u['user_email']}</td><td>{$u['display_name']}</td></tr>";
}
echo "</table>";
?>

</body></html>
