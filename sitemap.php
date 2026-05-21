<?php
// sitemap.php — Sitemap XML dinámico para Google
// Accesible vía /sitemap.xml (con .htaccess) o /sitemap.php
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host  = $_SERVER['HTTP_HOST'] ?? 'epleague.cl';
$base  = $proto . '://' . $host;

$today = date('Y-m-d');

// Páginas estáticas con prioridad
$paginas = [
    ['loc' => $base . '/',                'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => $base . '/torneos.php',     'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => $base . '/reglamento.php',  'priority' => '0.7', 'changefreq' => 'monthly'],
    ['loc' => $base . '/clasificacion.php','priority' => '0.8', 'changefreq' => 'daily'],
    ['loc' => $base . '/resultados.php',  'priority' => '0.8', 'changefreq' => 'daily'],
    ['loc' => $base . '/jugadores.php',   'priority' => '0.6', 'changefreq' => 'weekly'],
    ['loc' => $base . '/registro.php',    'priority' => '0.9', 'changefreq' => 'monthly'],
];

// Ligas/torneos públicos
$ligas = [];
$jugadores = [];
try {
    $db = epl_db();
    $st = $db->query("SELECT id, nombre, estado, updated_at FROM ligas ORDER BY id DESC LIMIT 50");
    $ligas = $st->fetchAll(PDO::FETCH_ASSOC);

    // Jugadores activos (indexables)
    $stj = $db->query("SELECT id, updated_at FROM jugadores WHERE estado='activo' ORDER BY id ASC LIMIT 500");
    $jugadores = $stj->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // silencioso, no rompemos el sitemap
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($paginas as $p): ?>
  <url>
    <loc><?= htmlspecialchars($p['loc'], ENT_XML1) ?></loc>
    <lastmod><?= $today ?></lastmod>
    <changefreq><?= $p['changefreq'] ?></changefreq>
    <priority><?= $p['priority'] ?></priority>
  </url>
<?php endforeach; ?>
<?php foreach ($ligas as $l):
    $lastmod = !empty($l['updated_at']) ? date('Y-m-d', strtotime($l['updated_at'])) : $today;
?>
  <url>
    <loc><?= htmlspecialchars($base . '/torneo.php?id=' . (int)$l['id'], ENT_XML1) ?></loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
<?php endforeach; ?>
<?php foreach ($jugadores as $j):
    $lastmod = !empty($j['updated_at']) ? date('Y-m-d', strtotime($j['updated_at'])) : $today;
?>
  <url>
    <loc><?= htmlspecialchars($base . '/jugador.php?id=' . (int)$j['id'], ENT_XML1) ?></loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
<?php endforeach; ?>
</urlset>
