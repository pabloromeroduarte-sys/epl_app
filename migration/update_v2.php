<?php
/**
 * update_v2.php — Migra una BD epleague v1 → v2
 * Abre en el navegador: http://localhost/elitepadelleague/migration/update_v2.php
 * Es seguro correrlo varias veces (usa IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).
 */
set_time_limit(120);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>EPL Update v2</title>
<style>
  body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem;max-width:720px;margin:0 auto}
  h1{color:#C9A762;font-size:1.4rem;margin-bottom:.5rem}
  .ok{color:#22c55e}.err{color:#ef4444}.warn{color:#fbbf24}.info{color:#94a3b8}
  .step{padding:.2rem 0;font-size:.88rem}
  .box{background:#1e293b;border-radius:8px;padding:1.25rem;margin-top:1.5rem;font-size:.88rem}
  code{background:#0f172a;padding:.1rem .4rem;border-radius:4px}
</style></head><body><h1>🏓 EPL Update v2 — Elite Padel League</h1>';

function step(string $msg, string $cls = 'info'): void {
    echo "<div class='step $cls'>$msg</div>";
    flush();
}

require_once dirname(__DIR__) . '/includes/db.php';
try {
    $db = epl_db();
    step('✓ Conexión a BD OK', 'ok');
} catch (Exception $e) {
    step('✗ Error de conexión: ' . $e->getMessage(), 'err');
    die('</body></html>');
}

// Helper: add column if not exists
function addCol(PDO $db, string $table, string $col, string $def): void {
    $exists = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetch();
    if (!$exists) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
        step("  + <code>$table.$col</code> agregada", 'ok');
    } else {
        step("  · <code>$table.$col</code> ya existe", 'info');
    }
}

// Helper: add index if not exists
function addIndex(PDO $db, string $table, string $name, string $def): void {
    $exists = $db->query("SHOW INDEX FROM `$table` WHERE Key_name='$name'")->fetch();
    if (!$exists) {
        $db->exec("ALTER TABLE `$table` ADD $def");
        step("  + índice <code>$name</code> agregado", 'ok');
    }
}

// ── 1. jugadores — nuevos campos ──────────────────────────
step('── jugadores ──', 'warn');
addCol($db, 'jugadores', 'rut',            "VARCHAR(15) DEFAULT NULL AFTER `alias`");
addCol($db, 'jugadores', 'sexo',           "ENUM('M','F','otro') DEFAULT NULL AFTER `fecha_nacimiento`");
addCol($db, 'jugadores', 'whatsapp',       "VARCHAR(30) DEFAULT NULL AFTER `telefono`");
addCol($db, 'jugadores', 'comuna',         "VARCHAR(100) DEFAULT NULL AFTER `whatsapp`");
addCol($db, 'jugadores', 'profesion',      "VARCHAR(150) DEFAULT NULL AFTER `comuna`");
addCol($db, 'jugadores', 'lado',           "ENUM('derecha','reves','ambos') DEFAULT NULL AFTER `nivel`");
addCol($db, 'jugadores', 'pala',           "VARCHAR(100) DEFAULT NULL AFTER `lado`");
addCol($db, 'jugadores', 'talla',          "VARCHAR(10) DEFAULT NULL AFTER `pala`");
addCol($db, 'jugadores', 'frecuencia_juego', "ENUM('1_semana','2_semana','3_o_mas','ocasional') DEFAULT NULL AFTER `talla`");

// ── 2. ligas — nuevos campos ──────────────────────────────
step('── ligas ──', 'warn');
// Actualizar ENUM estado para incluir 'proximamente'
try {
    $db->exec("ALTER TABLE `ligas` MODIFY `estado` ENUM('proximamente','inscripcion','activa','finalizada') NOT NULL DEFAULT 'inscripcion'");
    step("  + estado ENUM actualizado en ligas", 'ok');
} catch (PDOException $e) {
    step("  · estado ENUM ligas: " . $e->getMessage(), 'warn');
}
addCol($db, 'ligas', 'precio',              "DECIMAL(10,2) DEFAULT NULL AFTER `estado`");
addCol($db, 'ligas', 'precio_neto_deseado', "DECIMAL(12,2) NULL DEFAULT NULL AFTER `precio`");
addCol($db, 'ligas', 'mp_comision_pct',     "DECIMAL(8,4) NULL DEFAULT NULL AFTER `precio_neto_deseado`");
addCol($db, 'ligas', 'sede',                "VARCHAR(200) DEFAULT NULL AFTER `fecha_fin`");
addCol($db, 'ligas', 'url_maps',            "VARCHAR(500) DEFAULT NULL AFTER `sede`");
addCol($db, 'ligas', 'foto_portada',        "VARCHAR(500) DEFAULT NULL AFTER `descripcion`");
addCol($db, 'ligas', 'mailerlite_group_id', "VARCHAR(100) DEFAULT NULL AFTER `foto_portada`");

// ── 3. partidos — campo cancha ───────────────────────────
step('── partidos ──', 'warn');
addCol($db, 'partidos', 'cancha', "VARCHAR(100) DEFAULT NULL AFTER `jornada`");

// ── 4. solicitudes_reprogramacion — campos nuevos ─────────
step('── solicitudes_reprogramacion ──', 'warn');
addCol($db, 'solicitudes_reprogramacion', 'rival_no_responde', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `fecha_propuesta`");
addCol($db, 'solicitudes_reprogramacion', 'mutuo_acuerdo',     "TINYINT(1) NOT NULL DEFAULT 0 AFTER `rival_no_responde`");
addCol($db, 'solicitudes_reprogramacion', 'fecha_aprobada',    "DATETIME DEFAULT NULL AFTER `estado`");
addCol($db, 'solicitudes_reprogramacion', 'cancha_aprobada',   "VARCHAR(100) DEFAULT NULL AFTER `fecha_aprobada`");
addCol($db, 'solicitudes_reprogramacion', 'aprobado_por',      "INT UNSIGNED DEFAULT NULL AFTER `cancha_aprobada`");

// ── 5. inscripciones — campos nuevos ─────────────────────
step('── inscripciones ──', 'warn');
addCol($db, 'inscripciones', 'rol_equipo', "ENUM('capitan','partner') NOT NULL DEFAULT 'capitan' AFTER `equipo_id`");
addCol($db, 'inscripciones', 'token',      "VARCHAR(64) DEFAULT NULL AFTER `rol_equipo`");
addCol($db, 'inscripciones', 'pago_monto', "DECIMAL(10,2) DEFAULT NULL AFTER `pago_estado`");
addCol($db, 'inscripciones', 'pago_ref',   "VARCHAR(200) DEFAULT NULL AFTER `pago_monto`");

// ── 5b. pagos y notificaciones (inscripciones jugador) ───
step('── pagos / notificaciones ──', 'warn');
$db->exec("CREATE TABLE IF NOT EXISTS `pagos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id` INT UNSIGNED DEFAULT NULL,
  `jugador_id` INT UNSIGNED DEFAULT NULL,
  `inscripcion_id` INT UNSIGNED DEFAULT NULL,
  `concepto` VARCHAR(300) NOT NULL DEFAULT '',
  `rol` VARCHAR(30) NOT NULL DEFAULT 'manual',
  `monto` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `estado` ENUM('pendiente','completado','rechazado') NOT NULL DEFAULT 'pendiente',
  `metodo` VARCHAR(80) DEFAULT NULL,
  `mp_preference_id` VARCHAR(120) DEFAULT NULL,
  `mp_payment_id` VARCHAR(120) DEFAULT NULL,
  `token_ref` VARCHAR(64) DEFAULT NULL,
  `equipo_token` VARCHAR(64) DEFAULT NULL,
  `notas` TEXT DEFAULT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token_ref` (`token_ref`),
  KEY `idx_inscripcion` (`inscripcion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
step('  ✓ tabla pagos OK', 'ok');

$db->exec("CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `jugador_id` INT UNSIGNED NOT NULL,
  `tipo` VARCHAR(50) NOT NULL,
  `titulo` VARCHAR(150) NOT NULL,
  `mensaje` TEXT NOT NULL,
  `url` VARCHAR(255) DEFAULT NULL,
  `leida` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jugador_leida` (`jugador_id`, `leida`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
step('  ✓ tabla notificaciones OK', 'ok');

// ── 6. Tabla suplentes ───────────────────────────────────
step('── suplentes ──', 'warn');
$db->exec("CREATE TABLE IF NOT EXISTS `suplentes` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id`          INT UNSIGNED NOT NULL,
  `equipo_id`        INT UNSIGNED NOT NULL,
  `jugador_id`       INT UNSIGNED NOT NULL,
  `partidos_jugados` TINYINT UNSIGNED DEFAULT 0,
  `estado`           ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `registrado_por`   INT UNSIGNED NOT NULL,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_jugador_liga` (`jugador_id`, `liga_id`),
  FOREIGN KEY (`liga_id`)    REFERENCES `ligas`(`id`),
  FOREIGN KEY (`equipo_id`)  REFERENCES `equipos`(`id`),
  FOREIGN KEY (`jugador_id`) REFERENCES `jugadores`(`id`),
  FOREIGN KEY (`registrado_por`) REFERENCES `jugadores`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
step("  ✓ Tabla suplentes OK", 'ok');

$db->exec("CREATE TABLE IF NOT EXISTS `suplente_partidos` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `suplente_id`    INT UNSIGNED NOT NULL,
  `partido_id`     INT UNSIGNED NOT NULL,
  `registrado_por` INT UNSIGNED NOT NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_suplente_partido` (`suplente_id`, `partido_id`),
  FOREIGN KEY (`suplente_id`)    REFERENCES `suplentes`(`id`),
  FOREIGN KEY (`partido_id`)     REFERENCES `partidos`(`id`),
  FOREIGN KEY (`registrado_por`) REFERENCES `jugadores`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
step("  ✓ Tabla suplente_partidos OK", 'ok');

// ── 7. Tablas ERP Finanzas ───────────────────────────────
step('── finanzas ──', 'warn');
$db->exec("CREATE TABLE IF NOT EXISTS `finanzas_ingresos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id`     INT UNSIGNED DEFAULT NULL,
  `jugador_id`  INT UNSIGNED DEFAULT NULL,
  `tipo`        ENUM('mercadopago','manual','global') NOT NULL DEFAULT 'manual',
  `concepto`    VARCHAR(300) NOT NULL,
  `monto`       DECIMAL(10,2) NOT NULL,
  `referencia`  VARCHAR(200) DEFAULT NULL,
  `estado`      ENUM('confirmado','pendiente','rechazado') NOT NULL DEFAULT 'confirmado',
  `registrado_por` INT UNSIGNED DEFAULT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`liga_id`)    REFERENCES `ligas`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`jugador_id`) REFERENCES `jugadores`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
step("  ✓ finanzas_ingresos OK", 'ok');

$db->exec("CREATE TABLE IF NOT EXISTS `finanzas_egresos` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id`      INT UNSIGNED DEFAULT NULL,
  `categoria`    ENUM('canchas','pelotas','trofeos','marketing','otros') NOT NULL DEFAULT 'otros',
  `concepto`     VARCHAR(300) NOT NULL,
  `monto`        DECIMAL(10,2) NOT NULL,
  `comprobante`  VARCHAR(500) DEFAULT NULL,
  `registrado_por` INT UNSIGNED DEFAULT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`liga_id`) REFERENCES `ligas`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
step("  ✓ finanzas_egresos OK", 'ok');

$db->exec("CREATE TABLE IF NOT EXISTS `saldos_apps` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app`         VARCHAR(100) NOT NULL,
  `tipo`        ENUM('recarga','consumo') NOT NULL,
  `monto`       DECIMAL(10,2) NOT NULL,
  `descripcion` VARCHAR(300) DEFAULT NULL,
  `registrado_por` INT UNSIGNED DEFAULT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
step("  ✓ saldos_apps OK", 'ok');

// ── Resumen final ─────────────────────────────────────────
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

echo '<div class="box">';
echo '<p class="ok" style="font-size:1.1rem;font-weight:bold">✓ Update v2 completado</p>';
echo '<p style="color:#94a3b8;margin-top:.75rem">Tablas en epleague: <code>' . implode(', ', $tables) . '</code></p>';
echo '<hr style="border-color:#334155;margin:1rem 0">';
echo '<p><a href="../admin/" style="color:#C9A762">Ir al panel admin →</a></p>';
echo '</div>';
echo '</body></html>';
