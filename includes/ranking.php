<?php
declare(strict_types=1);

/**
 * Ranking individual EPL — ventana móvil de 365 días.
 *
 * Este módulo mantiene un libro de movimientos independiente de la tabla de
 * posiciones por pareja. No borra puntos vencidos: simplemente dejan de sumar.
 */

function epl_ranking_ensure_schema(): void {
    static $done = false;
    if ($done) return;

    $db = epl_db();

    // Camino rápido una vez instalada la versión actual del esquema.
    $stReady = $db->query("SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema=DATABASE() AND table_name IN
        ('ranking_reglas','partido_jugadores','ranking_movimientos','ranking_incidencias')");
    if ((int)$stReady->fetchColumn() === 4) {
        $colReemplazo = $db->query("SHOW COLUMNS FROM suplente_partidos LIKE 'reemplaza_jugador_id'")->fetch();
        $reglasReady = $db->query("SELECT COUNT(*) total,COALESCE(SUM(puntos),0) suma FROM ranking_reglas WHERE activo=1")->fetch();
        if ($colReemplazo && (int)$reglasReady['total'] === 12 && (int)$reglasReady['suma'] === 225) {
            $done = true;
            return;
        }
    }

    $db->exec("CREATE TABLE IF NOT EXISTS `ranking_reglas` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `categoria` TINYINT UNSIGNED NOT NULL,
        `tipo` ENUM('victoria_liga','premio_final') NOT NULL,
        `posicion` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `puntos` SMALLINT UNSIGNED NOT NULL,
        `activo` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_ranking_regla` (`categoria`,`tipo`,`posicion`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `partido_jugadores` (
        `partido_id` INT UNSIGNED NOT NULL,
        `equipo_id` INT UNSIGNED NOT NULL,
        `jugador_id` INT UNSIGNED NOT NULL,
        `rol_partido` ENUM('titular','suplente') NOT NULL DEFAULT 'titular',
        `fuente` VARCHAR(40) NOT NULL DEFAULT 'automatico',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`partido_id`,`jugador_id`),
        KEY `idx_partido_jugadores_equipo` (`partido_id`,`equipo_id`),
        KEY `idx_partido_jugadores_jugador` (`jugador_id`,`partido_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `ranking_movimientos` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `jugador_id` INT UNSIGNED NOT NULL,
        `liga_id` INT UNSIGNED DEFAULT NULL,
        `partido_id` INT UNSIGNED DEFAULT NULL,
        `tipo` ENUM('victoria_liga','premio_final','ajuste') NOT NULL,
        `categoria` TINYINT UNSIGNED DEFAULT NULL,
        `posicion_final` TINYINT UNSIGNED DEFAULT NULL,
        `puntos` SMALLINT NOT NULL,
        `fecha_obtencion` DATE NOT NULL,
        `fecha_vencimiento` DATE NOT NULL,
        `fecha_fuente` VARCHAR(40) NOT NULL DEFAULT 'fecha_jugado',
        `clave_origen` VARCHAR(160) NOT NULL,
        `detalle` VARCHAR(255) DEFAULT NULL,
        `anulado_at` DATETIME DEFAULT NULL,
        `motivo_anulacion` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_ranking_movimiento_origen` (`clave_origen`),
        KEY `idx_ranking_vigente` (`anulado_at`,`fecha_vencimiento`,`jugador_id`),
        KEY `idx_ranking_jugador` (`jugador_id`,`fecha_vencimiento`),
        KEY `idx_ranking_liga_tipo` (`liga_id`,`tipo`),
        KEY `idx_ranking_partido` (`partido_id`,`jugador_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `ranking_incidencias` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `partido_id` INT UNSIGNED DEFAULT NULL,
        `tipo` VARCHAR(60) NOT NULL,
        `detalle` VARCHAR(500) NOT NULL,
        `resuelta_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_ranking_incidencia` (`partido_id`,`tipo`),
        KEY `idx_ranking_incidencia_activa` (`resuelta_at`,`tipo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Para saber a quién reemplazó realmente una galleta en cada encuentro.
    $colsSp = array_column($db->query("SHOW COLUMNS FROM suplente_partidos")->fetchAll(), 'Field');
    if (!in_array('reemplaza_jugador_id', $colsSp, true)) {
        $db->exec("ALTER TABLE suplente_partidos ADD COLUMN reemplaza_jugador_id INT UNSIGNED NULL AFTER partido_id");
    }

    $seed = $db->prepare("INSERT INTO ranking_reglas (categoria,tipo,posicion,puntos)
        VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE puntos=VALUES(puntos),activo=1");

    foreach ([4, 5] as $categoria) {
        $seed->execute([$categoria, 'victoria_liga', 0, 3]);
    }
    foreach ([1 => 50, 2 => 40, 3 => 25, 4 => 14, 5 => 10] as $pos => $pts) {
        $seed->execute([4, 'premio_final', $pos, $pts]);
    }
    foreach ([1 => 30, 2 => 20, 3 => 15, 4 => 10, 5 => 5] as $pos => $pts) {
        $seed->execute([5, 'premio_final', $pos, $pts]);
    }

    // Mantener compatibles las pantallas administrativas heredadas.
    $db->exec("UPDATE ligas SET puntos_1=50,puntos_2=40,puntos_3=25,puntos_4=14,puntos_grupos=10 WHERE categoria=4");
    $db->exec("UPDATE ligas SET puntos_1=30,puntos_2=20,puntos_3=15,puntos_4=10,puntos_grupos=5 WHERE categoria=5");
    $done = true;
}

function epl_ranking_escala_categoria(int $categoria): array {
    return $categoria === 4
        ? [1 => 50, 2 => 40, 3 => 25, 4 => 14, 5 => 10]
        : [1 => 30, 2 => 20, 3 => 15, 4 => 10, 5 => 5];
}

function epl_ranking_fecha_vencimiento(string $fecha): string {
    return (new DateTimeImmutable($fecha))->modify('+365 days')->format('Y-m-d');
}

/** @return array{fecha:?string,fuente:string} */
function epl_ranking_fecha_partido(array $partido): array {
    if (!empty($partido['fecha_jugado'])) {
        return ['fecha' => date('Y-m-d', strtotime((string)$partido['fecha_jugado'])), 'fuente' => 'fecha_jugado'];
    }

    // Rescate histórico: EPL tiene resultados antiguos sin fecha_jugado.
    if (!empty($partido['fecha_programada'])) {
        $fechaProgramada = date('Y-m-d', strtotime((string)$partido['fecha_programada']));
        $esMarcadorSinFecha = substr((string)$partido['fecha_programada'], 5, 5) === '12-31'
            && substr((string)$partido['fecha_programada'], 11, 5) === '00:00';
        if (!$esMarcadorSinFecha) {
            return ['fecha' => $fechaProgramada, 'fuente' => 'fecha_programada_historica'];
        }
    }

    if (!empty($partido['resultado_ingresado_at'])) {
        return ['fecha' => date('Y-m-d', strtotime((string)$partido['resultado_ingresado_at'])), 'fuente' => 'fecha_ingreso_historica'];
    }

    return ['fecha' => null, 'fuente' => 'sin_fecha'];
}

function epl_ranking_incidencia(int $partidoId, string $tipo, string $detalle): void {
    $db = epl_db();
    $st = $db->prepare("INSERT INTO ranking_incidencias (partido_id,tipo,detalle)
        VALUES (?,?,?) ON DUPLICATE KEY UPDATE detalle=VALUES(detalle),resuelta_at=NULL");
    $st->execute([$partidoId, $tipo, $detalle]);
}

/**
 * Congela los cuatro participantes reales. Si una suplencia histórica no
 * permite saber a quién reemplazó, no inventa el dato y deja una incidencia.
 */
function epl_ranking_snapshot_participantes(array $partido): bool {
    epl_ranking_ensure_schema();
    $db = epl_db();
    $partidoId = (int)$partido['id'];

    $stExist = $db->prepare("SELECT COUNT(*) FROM partido_jugadores WHERE partido_id=?");
    $stExist->execute([$partidoId]);
    if ((int)$stExist->fetchColumn() === 4) return true;

    $stTeams = $db->prepare("SELECT id,jugador1_id,jugador2_id FROM equipos WHERE id IN (?,?)");
    $stTeams->execute([(int)$partido['equipo_local_id'], (int)$partido['equipo_visitante_id']]);
    $teams = [];
    foreach ($stTeams->fetchAll() as $row) $teams[(int)$row['id']] = $row;

    $participantes = [];
    foreach ([(int)$partido['equipo_local_id'], (int)$partido['equipo_visitante_id']] as $equipoId) {
        if (empty($teams[$equipoId])) {
            epl_ranking_incidencia($partidoId, 'equipo_incompleto', "No se encontró el equipo {$equipoId}.");
            return false;
        }
        $team = $teams[$equipoId];
        $actuales = [(int)$team['jugador1_id'], (int)$team['jugador2_id']];
        $roles = [$actuales[0] => 'titular', $actuales[1] => 'titular'];

        $stSup = $db->prepare("SELECT s.jugador_id AS suplente_jugador_id,
                   sp.reemplaza_jugador_id,sp.registrado_por
            FROM suplente_partidos sp
            JOIN suplentes s ON s.id=sp.suplente_id
            WHERE sp.partido_id=? AND s.equipo_id=?");
        $stSup->execute([$partidoId, $equipoId]);
        foreach ($stSup->fetchAll() as $sup) {
            $suplenteJid = (int)$sup['suplente_jugador_id'];
            $reemplaza = (int)($sup['reemplaza_jugador_id'] ?? 0);

            // En registros antiguos, quien informó la galleta normalmente fue
            // el titular que sí jugó; se infiere que reemplazó al compañero.
            if (!$reemplaza && in_array((int)$sup['registrado_por'], $actuales, true)) {
                $reemplaza = $actuales[0] === (int)$sup['registrado_por'] ? $actuales[1] : $actuales[0];
            }
            if (!$reemplaza || !isset($roles[$reemplaza])) {
                epl_ranking_incidencia(
                    $partidoId,
                    'suplente_sin_reemplazo',
                    'Existe un suplente registrado, pero falta indicar a cuál titular reemplazó.'
                );
                return false;
            }
            unset($roles[$reemplaza]);
            $roles[$suplenteJid] = 'suplente';
        }

        if (count($roles) !== 2) {
            epl_ranking_incidencia($partidoId, 'participantes_invalidos', 'El equipo no quedó con exactamente dos jugadores.');
            return false;
        }
        foreach ($roles as $jugadorId => $rol) {
            $participantes[] = [$equipoId, (int)$jugadorId, $rol];
        }
    }

    if (count($participantes) !== 4) return false;
    $db->prepare("DELETE FROM partido_jugadores WHERE partido_id=?")->execute([$partidoId]);
    $ins = $db->prepare("INSERT INTO partido_jugadores
        (partido_id,equipo_id,jugador_id,rol_partido,fuente) VALUES (?,?,?,?,?)");
    foreach ($participantes as [$equipoId, $jugadorId, $rol]) {
        $ins->execute([$partidoId, $equipoId, $jugadorId, $rol, 'automatico']);
    }
    $db->prepare("UPDATE ranking_incidencias SET resuelta_at=NOW()
        WHERE partido_id=? AND resuelta_at IS NULL")->execute([$partidoId]);
    return true;
}

/** Sincroniza todas las victorias de una liga de manera idempotente. */
function epl_ranking_sincronizar_liga_partidos(int $ligaId): array {
    epl_ranking_ensure_schema();
    $db = epl_db();

    $stLiga = $db->prepare("SELECT id,nombre,tipo,categoria FROM ligas WHERE id=?");
    $stLiga->execute([$ligaId]);
    $liga = $stLiga->fetch();
    if (!$liga) return ['asignados' => 0, 'incidencias' => 0];

    // Los americanos/torneos entregan premio por posición, no +3 por partido.
    if ($liga['tipo'] !== 'liga') {
        $db->prepare("UPDATE ranking_movimientos SET anulado_at=COALESCE(anulado_at,NOW()),
            motivo_anulacion='La competencia no entrega puntos por partido'
            WHERE liga_id=? AND tipo='victoria_liga' AND anulado_at IS NULL")->execute([$ligaId]);
        return ['asignados' => 0, 'incidencias' => 0];
    }

    $categoria = (int)($liga['categoria'] ?? 0);
    $stRegla = $db->prepare("SELECT puntos FROM ranking_reglas
        WHERE categoria=? AND tipo='victoria_liga' AND posicion=0 AND activo=1 LIMIT 1");
    $stRegla->execute([$categoria]);
    $puntosVictoria = (int)($stRegla->fetchColumn() ?: 3);

    $db->prepare("UPDATE ranking_movimientos
        SET anulado_at=COALESCE(anulado_at,NOW()),motivo_anulacion='Resultado corregido o pendiente de resincronización'
        WHERE liga_id=? AND tipo='victoria_liga' AND anulado_at IS NULL")->execute([$ligaId]);

    $stPartidos = $db->prepare("SELECT * FROM partidos
        WHERE liga_id=? AND estado IN ('jugado','walkover') AND ganador_id IS NOT NULL");
    $stPartidos->execute([$ligaId]);
    $upsert = $db->prepare("INSERT INTO ranking_movimientos
        (jugador_id,liga_id,partido_id,tipo,categoria,puntos,fecha_obtencion,fecha_vencimiento,fecha_fuente,clave_origen,detalle)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE puntos=VALUES(puntos),fecha_obtencion=VALUES(fecha_obtencion),
          fecha_vencimiento=VALUES(fecha_vencimiento),fecha_fuente=VALUES(fecha_fuente),
          categoria=VALUES(categoria),detalle=VALUES(detalle),anulado_at=NULL,motivo_anulacion=NULL");

    $asignados = 0;
    $incidencias = 0;
    foreach ($stPartidos->fetchAll() as $partido) {
        $fechaInfo = epl_ranking_fecha_partido($partido);
        if (!$fechaInfo['fecha']) {
            epl_ranking_incidencia((int)$partido['id'], 'partido_sin_fecha', 'El partido tiene resultado, pero no una fecha utilizable.');
            $incidencias++;
            continue;
        }
        if (!epl_ranking_snapshot_participantes($partido)) {
            $incidencias++;
            continue;
        }
        $stGanadores = $db->prepare("SELECT jugador_id FROM partido_jugadores WHERE partido_id=? AND equipo_id=?");
        $stGanadores->execute([(int)$partido['id'], (int)$partido['ganador_id']]);
        $ganadores = array_map('intval', $stGanadores->fetchAll(PDO::FETCH_COLUMN));
        if (count($ganadores) !== 2) {
            epl_ranking_incidencia((int)$partido['id'], 'ganadores_invalidos', 'No se pudieron determinar los dos jugadores ganadores.');
            $incidencias++;
            continue;
        }
        foreach ($ganadores as $jugadorId) {
            $clave = "partido:{$partido['id']}:jugador:{$jugadorId}:victoria";
            $detalle = ($partido['estado'] === 'walkover' ? 'Victoria por WO' : 'Victoria de liga') . ' · ' . $liga['nombre'];
            $upsert->execute([
                $jugadorId,$ligaId,(int)$partido['id'],'victoria_liga',$categoria,$puntosVictoria,
                $fechaInfo['fecha'],epl_ranking_fecha_vencimiento($fechaInfo['fecha']),$fechaInfo['fuente'],$clave,$detalle,
            ]);
            $asignados++;
        }
    }
    return ['asignados' => $asignados, 'incidencias' => $incidencias];
}

/** Devuelve la pareja que recibe el premio final, aplicando la regla >50%. */
function epl_ranking_titulares_finales(int $ligaId, int $equipoId): array {
    $db = epl_db();
    $stEq = $db->prepare("SELECT jugador1_id,jugador2_id FROM equipos WHERE id=?");
    $stEq->execute([$equipoId]);
    $equipo = $stEq->fetch();
    if (!$equipo) return [];
    $titulares = [(int)$equipo['jugador1_id'], (int)$equipo['jugador2_id']];

    $stTotal = $db->prepare("SELECT COUNT(*) FROM partidos WHERE liga_id=? AND estado<>'cancelado' AND (equipo_local_id=? OR equipo_visitante_id=?)");
    $stTotal->execute([$ligaId, $equipoId, $equipoId]);
    $totalPartidos = (int)$stTotal->fetchColumn();
    if ($totalPartidos === 0) return $titulares;

    $stSup = $db->prepare("SELECT s.id AS suplente_id,s.jugador_id AS suplente_jugador_id,
               COUNT(DISTINCT sp.partido_id) AS jugados
        FROM suplentes s
        JOIN suplente_partidos sp ON sp.suplente_id=s.id
        JOIN partidos p ON p.id=sp.partido_id AND p.estado IN ('jugado','walkover')
        WHERE s.liga_id=? AND s.equipo_id=? AND sp.partido_id IS NOT NULL
        GROUP BY s.id,s.jugador_id
        HAVING COUNT(DISTINCT sp.partido_id) * 2 > ?
        ORDER BY jugados DESC LIMIT 1");
    $stSup->execute([$ligaId, $equipoId, $totalPartidos]);
    $sup = $stSup->fetch();
    if (!$sup) return $titulares;

    $suplenteJid = (int)$sup['suplente_jugador_id'];
    if (in_array($suplenteJid, $titulares, true)) return $titulares;
    $stReemplazo = $db->prepare("SELECT reemplaza_jugador_id,COUNT(*) total
        FROM suplente_partidos sp
        JOIN partidos p ON p.id=sp.partido_id AND p.estado IN ('jugado','walkover')
        WHERE sp.suplente_id=? AND sp.reemplaza_jugador_id IS NOT NULL
        GROUP BY reemplaza_jugador_id ORDER BY total DESC,reemplaza_jugador_id ASC LIMIT 1");
    $stReemplazo->execute([(int)$sup['suplente_id']]);
    $reemplaza = (int)($stReemplazo->fetchColumn() ?: 0);
    if ($reemplaza && in_array($reemplaza, $titulares, true)) {
        $titulares[array_search($reemplaza, $titulares, true)] = $suplenteJid;
    }
    return array_values(array_unique($titulares));
}

/** Asigna el Top 5 al cerrar una liga o torneo. */
function epl_ranking_asignar_premios(int $ligaId): array {
    epl_ranking_ensure_schema();
    $db = epl_db();
    $stLiga = $db->prepare("SELECT * FROM ligas WHERE id=?");
    $stLiga->execute([$ligaId]);
    $liga = $stLiga->fetch();
    if (!$liga) throw new RuntimeException('Competencia no encontrada.');
    if ($liga['estado'] !== 'finalizada') throw new RuntimeException('La competencia debe estar finalizada.');

    $categoria = (int)($liga['categoria'] ?? 0);
    if (!in_array($categoria, [4, 5], true)) {
        throw new RuntimeException('El ranking individual solo admite competencias de 4ta o 5ta.');
    }
    $fecha = $liga['fecha_fin'] ?: date('Y-m-d');
    $escala = epl_ranking_escala_categoria($categoria);

    $db->prepare("UPDATE ranking_movimientos SET anulado_at=COALESCE(anulado_at,NOW()),
        motivo_anulacion='Premio final recalculado'
        WHERE liga_id=? AND tipo='premio_final' AND anulado_at IS NULL")->execute([$ligaId]);

    $stTop = $db->prepare("SELECT posicion,equipo_id FROM clasificacion
        WHERE liga_id=? AND posicion BETWEEN 1 AND 5 ORDER BY posicion ASC");
    $stTop->execute([$ligaId]);
    $upsert = $db->prepare("INSERT INTO ranking_movimientos
        (jugador_id,liga_id,tipo,categoria,posicion_final,puntos,fecha_obtencion,fecha_vencimiento,fecha_fuente,clave_origen,detalle)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE posicion_final=VALUES(posicion_final),puntos=VALUES(puntos),
          fecha_obtencion=VALUES(fecha_obtencion),fecha_vencimiento=VALUES(fecha_vencimiento),
          categoria=VALUES(categoria),detalle=VALUES(detalle),anulado_at=NULL,motivo_anulacion=NULL");

    $asignados = 0;
    foreach ($stTop->fetchAll() as $row) {
        $posicion = (int)$row['posicion'];
        $puntos = (int)$escala[$posicion];
        $titulares = epl_ranking_titulares_finales($ligaId, (int)$row['equipo_id']);
        if (count($titulares) !== 2) continue;
        foreach ($titulares as $jugadorId) {
            $clave = "liga:{$ligaId}:jugador:{$jugadorId}:premio-final";
            $upsert->execute([
                $jugadorId,$ligaId,'premio_final',$categoria,$posicion,$puntos,$fecha,
                epl_ranking_fecha_vencimiento($fecha),'fecha_fin',$clave,
                "{$posicion}° lugar · {$liga['nombre']}",
            ]);
            $asignados++;
        }
    }

    // Mantener la tabla heredada disponible para pantallas antiguas durante la transición.
    $db->prepare("DELETE FROM ranking_puntos WHERE liga_id=?")->execute([$ligaId]);
    $legacy = $db->prepare("INSERT INTO ranking_puntos
        (jugador_id,liga_id,posicion_final,puntos,fecha_competicion) VALUES (?,?,?,?,?)");
    $stPremios = $db->prepare("SELECT jugador_id,posicion_final,puntos,fecha_obtencion
        FROM ranking_movimientos WHERE liga_id=? AND tipo='premio_final' AND anulado_at IS NULL");
    $stPremios->execute([$ligaId]);
    foreach ($stPremios->fetchAll() as $mov) {
        $legacy->execute([(int)$mov['jugador_id'],$ligaId,(int)$mov['posicion_final'],(int)$mov['puntos'],$mov['fecha_obtencion']]);
    }

    return ['asignados' => $asignados, 'fecha' => $fecha];
}

function epl_ranking_top(int $limite = 100): array {
    epl_ranking_ensure_schema();
    $db = epl_db();
    $limite = max(1, min(500, $limite));
    $sql = "SELECT * FROM (SELECT j.id,j.nombre,j.apellido,j.alias,j.foto,
            SUM(rm.puntos) AS puntos,
            SUM(rm.tipo='victoria_liga') AS victorias,
            SUM(rm.tipo='premio_final') AS premios,
            MIN(CASE WHEN rm.tipo='premio_final' AND rm.categoria=4 THEN rm.posicion_final END) AS mejor_4ta,
            MIN(CASE WHEN rm.tipo='premio_final' AND rm.categoria=5 THEN rm.posicion_final END) AS mejor_5ta,
            SUM(CASE WHEN rm.fecha_vencimiento <= DATE_ADD(CURDATE(),INTERVAL 30 DAY) THEN rm.puntos ELSE 0 END) AS vence_30_dias,
            MAX(rm.fecha_obtencion) AS ultima_fecha
        FROM ranking_movimientos rm
        JOIN jugadores j ON j.id=rm.jugador_id
        WHERE rm.anulado_at IS NULL AND rm.fecha_vencimiento>CURDATE()
        GROUP BY j.id,j.nombre,j.apellido,j.alias,j.foto
        HAVING SUM(rm.puntos)>0) ranking_actual
        ORDER BY puntos DESC,victorias DESC,
          COALESCE(mejor_4ta,999) ASC,COALESCE(mejor_5ta,999) ASC,
          ultima_fecha DESC,apellido ASC,nombre ASC
        LIMIT {$limite}";
    return $db->query($sql)->fetchAll();
}

function epl_ranking_resumen_jugador(int $jugadorId): array {
    epl_ranking_ensure_schema();
    $db = epl_db();
    $st = $db->prepare("SELECT COALESCE(SUM(puntos),0) AS puntos,
            SUM(tipo='victoria_liga') AS victorias,
            SUM(tipo='premio_final') AS premios,
            COALESCE(SUM(CASE WHEN fecha_vencimiento<=DATE_ADD(CURDATE(),INTERVAL 30 DAY) THEN puntos ELSE 0 END),0) AS vence_30_dias
        FROM ranking_movimientos
        WHERE jugador_id=? AND anulado_at IS NULL AND fecha_vencimiento>CURDATE()");
    $st->execute([$jugadorId]);
    return $st->fetch() ?: ['puntos'=>0,'victorias'=>0,'premios'=>0,'vence_30_dias'=>0];
}

/** Backfill idempotente para resultados y premios ya existentes. */
function epl_ranking_migrar_historico(): array {
    epl_ranking_ensure_schema();
    $db = epl_db();
    $resumen = ['ligas' => 0, 'movimientos' => 0, 'incidencias' => 0, 'premios_legacy' => 0];
    foreach ($db->query("SELECT id FROM ligas ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $ligaId) {
        $sync = epl_ranking_sincronizar_liga_partidos((int)$ligaId);
        $resumen['ligas']++;
        $resumen['movimientos'] += $sync['asignados'];
        $resumen['incidencias'] += $sync['incidencias'];
    }

    // Importar premios antiguos sin duplicarlos.
    $legacy = $db->query("SELECT rp.*,l.categoria,l.nombre AS liga_nombre
        FROM ranking_puntos rp JOIN ligas l ON l.id=rp.liga_id")->fetchAll();
    $upsert = $db->prepare("INSERT INTO ranking_movimientos
        (jugador_id,liga_id,tipo,categoria,posicion_final,puntos,fecha_obtencion,fecha_vencimiento,fecha_fuente,clave_origen,detalle)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE puntos=VALUES(puntos),posicion_final=VALUES(posicion_final),
          fecha_obtencion=VALUES(fecha_obtencion),fecha_vencimiento=VALUES(fecha_vencimiento),
          anulado_at=NULL,motivo_anulacion=NULL");
    foreach ($legacy as $row) {
        $fecha = (string)$row['fecha_competicion'];
        $clave = "liga:{$row['liga_id']}:jugador:{$row['jugador_id']}:premio-final";
        $upsert->execute([
            (int)$row['jugador_id'],(int)$row['liga_id'],'premio_final',(int)$row['categoria'],
            (int)$row['posicion_final'],(int)$row['puntos'],$fecha,epl_ranking_fecha_vencimiento($fecha),
            'ranking_puntos_legacy',$clave,"Premio histórico · {$row['liga_nombre']}",
        ]);
        $resumen['premios_legacy']++;
    }
    return $resumen;
}
