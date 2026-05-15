<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();

// Columnas a agregar: [nombre => SQL sin IF NOT EXISTS]
$columns = [
    "alias"               => "ALTER TABLE jugadores ADD COLUMN alias               VARCHAR(100) DEFAULT NULL AFTER apellido",
    "rut"                 => "ALTER TABLE jugadores ADD COLUMN rut                 VARCHAR(15)  DEFAULT NULL AFTER alias",
    "fecha_nacimiento"    => "ALTER TABLE jugadores ADD COLUMN fecha_nacimiento    DATE         DEFAULT NULL AFTER rut",
    "sexo"                => "ALTER TABLE jugadores ADD COLUMN sexo                ENUM('M','F','otro') DEFAULT NULL AFTER fecha_nacimiento",
    "foto"                => "ALTER TABLE jugadores ADD COLUMN foto                VARCHAR(500) DEFAULT NULL AFTER sexo",
    "telefono"            => "ALTER TABLE jugadores ADD COLUMN telefono            VARCHAR(30)  DEFAULT NULL AFTER foto",
    "whatsapp"            => "ALTER TABLE jugadores ADD COLUMN whatsapp            VARCHAR(30)  DEFAULT NULL AFTER telefono",
    "comuna"              => "ALTER TABLE jugadores ADD COLUMN comuna              VARCHAR(100) DEFAULT NULL AFTER whatsapp",
    "profesion"           => "ALTER TABLE jugadores ADD COLUMN profesion           VARCHAR(150) DEFAULT NULL AFTER comuna",
    "nivel"               => "ALTER TABLE jugadores ADD COLUMN nivel               TINYINT UNSIGNED DEFAULT 5 AFTER profesion",
    "lado"                => "ALTER TABLE jugadores ADD COLUMN lado                ENUM('derecha','reves','ambos') DEFAULT NULL AFTER nivel",
    "pala"                => "ALTER TABLE jugadores ADD COLUMN pala                VARCHAR(100) DEFAULT NULL AFTER lado",
    "talla"               => "ALTER TABLE jugadores ADD COLUMN talla               VARCHAR(10)  DEFAULT NULL AFTER pala",
    "frecuencia_juego"    => "ALTER TABLE jugadores ADD COLUMN frecuencia_juego   ENUM('1_semana','2_semana','3_o_mas','ocasional') DEFAULT NULL AFTER talla",
    "reset_token"         => "ALTER TABLE jugadores ADD COLUMN reset_token         VARCHAR(100) DEFAULT NULL",
    "reset_token_expires" => "ALTER TABLE jugadores ADD COLUMN reset_token_expires DATETIME     DEFAULT NULL",
];

// Obtener columnas que ya existen
$existing = [];
$st = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jugadores'");
foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $col) {
    $existing[] = strtolower($col);
}

$results = [];
foreach ($columns as $col => $sql) {
    if (in_array(strtolower($col), $existing)) {
        $results[$col] = 'ya_existe';
    } else {
        try {
            $db->exec($sql);
            $results[$col] = 'agregada';
        } catch (PDOException $e) {
            $results[$col] = 'error: ' . $e->getMessage();
        }
    }
}

// Leer columnas finales para verificación
$cols = $db->query("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jugadores'
    ORDER BY ORDINAL_POSITION")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Migración — Jugadores</title>
  <style>
    body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem}
    h1{color:#f0b429;margin-bottom:1.5rem}
    table{border-collapse:collapse;width:100%;margin-top:1.5rem}
    th,td{padding:.4rem .8rem;text-align:left;border-bottom:1px solid #1e293b;font-size:.85rem}
    th{color:#94a3b8;font-size:.75rem;text-transform:uppercase}
    .agregada{color:#22c55e;font-weight:700}
    .ya_existe{color:#64748b}
    .err{color:#ef4444}
    .back{display:inline-block;margin-top:1.5rem;color:#f0b429;text-decoration:none;font-size:.85rem}
  </style>
</head>
<body>
<h1>Migración — tabla jugadores</h1>

<table>
  <tr><th>Columna</th><th>Resultado</th></tr>
  <?php foreach ($results as $col => $res):
    $cls = $res === 'agregada' ? 'agregada' : (str_starts_with($res,'error') ? 'err' : 'ya_existe');
    $label = $res === 'agregada' ? '✅ Agregada' : ($res === 'ya_existe' ? '— Ya existía' : '❌ '.$res);
  ?>
  <tr>
    <td><?= htmlspecialchars($col) ?></td>
    <td class="<?= $cls ?>"><?= htmlspecialchars($label) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<h2 style="margin-top:2rem;color:#94a3b8;font-size:.9rem;text-transform:uppercase">Columnas actuales en jugadores</h2>
<table>
  <tr><th>Columna</th><th>Tipo</th><th>Nullable</th><th>Default</th></tr>
  <?php foreach ($cols as $c): ?>
  <tr>
    <td><?= htmlspecialchars($c['COLUMN_NAME']) ?></td>
    <td><?= htmlspecialchars($c['DATA_TYPE']) ?></td>
    <td><?= $c['IS_NULLABLE'] ?></td>
    <td><?= htmlspecialchars($c['COLUMN_DEFAULT'] ?? 'NULL') ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<a href="jugadores.php" class="back">← Volver a Jugadores</a>
</body>
</html>
