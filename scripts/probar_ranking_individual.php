<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la consola local.');
}

require_once __DIR__ . '/../includes/functions.php';

function ranking_assert(bool $condicion, string $mensaje): void {
    if (!$condicion) throw new RuntimeException($mensaje);
    echo "OK · {$mensaje}\n";
}

$db = epl_db();
epl_ranking_ensure_schema();

ranking_assert(epl_ranking_fecha_vencimiento('2026-08-26') === '2027-08-26', 'Cada movimiento vence exactamente a los 365 días.');
ranking_assert(epl_ranking_escala_categoria(4) === [1=>50,2=>40,3=>25,4=>14,5=>10], 'La escala de 4ta es correcta.');
ranking_assert(epl_ranking_escala_categoria(5) === [1=>30,2=>20,3=>15,4=>10,5=>5], 'La escala de 5ta es correcta.');

$top = epl_ranking_top(100);
ranking_assert(is_array($top), 'El Top 100 se puede calcular.');

$jugadorId = (int)($db->query("SELECT id FROM jugadores ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
if ($jugadorId) {
    $antes = epl_ranking_resumen_jugador($jugadorId);
    $db->beginTransaction();
    try {
        $ins = $db->prepare("INSERT INTO ranking_movimientos
            (jugador_id,tipo,puntos,fecha_obtencion,fecha_vencimiento,fecha_fuente,clave_origen,detalle)
            VALUES (?,'ajuste',7,CURDATE(),CURDATE(),'prueba',?,'Debe estar vencido'),
                   (?,'ajuste',11,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 1 DAY),'prueba',?,'Debe estar vigente')");
        $ins->execute([$jugadorId, 'prueba:vencido:'.uniqid(), $jugadorId, 'prueba:vigente:'.uniqid()]);
        $durante = epl_ranking_resumen_jugador($jugadorId);
        ranking_assert((int)$durante['puntos'] === (int)$antes['puntos'] + 11, 'El ranking ignora vencimiento hoy y suma solo vencimientos futuros.');
    } finally {
        $db->rollBack();
    }
}

$duplicados = (int)$db->query("SELECT COUNT(*) FROM (
    SELECT clave_origen FROM ranking_movimientos GROUP BY clave_origen HAVING COUNT(*)>1
) d")->fetchColumn();
ranking_assert($duplicados === 0, 'No existen movimientos duplicados.');

$participantesInvalidos = (int)$db->query("SELECT COUNT(*) FROM (
    SELECT partido_id,COUNT(*) total FROM partido_jugadores GROUP BY partido_id HAVING total<>4
) x")->fetchColumn();
ranking_assert($participantesInvalidos === 0, 'Cada partido congelado tiene cuatro participantes.');

$partidoWo = $db->query("SELECT * FROM partidos WHERE estado='jugado' AND ganador_id IS NOT NULL LIMIT 1")->fetch();
if ($partidoWo) {
    $ligaId = (int)$partidoWo['liga_id'];
    $localId = (int)$partidoWo['equipo_local_id'];
    $visitaId = (int)$partidoWo['equipo_visitante_id'];
    $ganaLocal = (int)$partidoWo['ganador_id'] === $localId;
    $gamesLocal = (int)$partidoWo['games_s1_local'] + (int)$partidoWo['games_s2_local'] + (int)$partidoWo['games_s3_local'];
    $gamesVisita = (int)$partidoWo['games_s1_visitante'] + (int)$partidoWo['games_s2_visitante'] + (int)$partidoWo['games_s3_visitante'];
    $stAntes = $db->prepare("SELECT equipo_id,games_favor,games_contra FROM clasificacion WHERE liga_id=? AND equipo_id IN (?,?)");
    $stAntes->execute([$ligaId,$localId,$visitaId]);
    $clasifAntes = [];
    foreach ($stAntes->fetchAll() as $fila) $clasifAntes[(int)$fila['equipo_id']] = $fila;

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE partidos SET estado='walkover',sets_local=NULL,sets_visitante=NULL,
            games_s1_local=NULL,games_s1_visitante=NULL,games_s2_local=NULL,games_s2_visitante=NULL,
            games_s3_local=NULL,games_s3_visitante=NULL WHERE id=?")->execute([(int)$partidoWo['id']]);
        epl_recalcular_clasificacion($ligaId);
        $stDespues = $db->prepare("SELECT equipo_id,games_favor,games_contra FROM clasificacion WHERE liga_id=? AND equipo_id IN (?,?)");
        $stDespues->execute([$ligaId,$localId,$visitaId]);
        $clasifDespues = [];
        foreach ($stDespues->fetchAll() as $fila) $clasifDespues[(int)$fila['equipo_id']] = $fila;
        $esperadoLocalFavor = (int)$clasifAntes[$localId]['games_favor'] - $gamesLocal + ($ganaLocal ? 12 : 0);
        $esperadoVisitaFavor = (int)$clasifAntes[$visitaId]['games_favor'] - $gamesVisita + ($ganaLocal ? 0 : 12);
        ranking_assert(
            (int)$clasifDespues[$localId]['games_favor'] === $esperadoLocalFavor
            && (int)$clasifDespues[$visitaId]['games_favor'] === $esperadoVisitaFavor,
            'Un WO se calcula como 6-0, 6-0.'
        );
    } finally {
        $db->rollBack();
    }
}

$ligaPremio = $db->query("SELECT id,categoria FROM ligas WHERE categoria IN (4,5) AND (SELECT COUNT(*) FROM clasificacion c WHERE c.liga_id=ligas.id AND c.posicion BETWEEN 1 AND 5)=5 LIMIT 1")->fetch();
if ($ligaPremio) {
    $ligaPremioId = (int)$ligaPremio['id'];
    $escalaPremio = epl_ranking_escala_categoria((int)$ligaPremio['categoria']);
    $db->beginTransaction();
    try {
        $db->prepare("UPDATE ligas SET estado='finalizada',fecha_fin=COALESCE(fecha_fin,CURDATE()) WHERE id=?")->execute([$ligaPremioId]);
        $premio = epl_ranking_asignar_premios($ligaPremioId);
        ranking_assert((int)$premio['asignados'] === 10, 'El cierre premia a los dos jugadores de cada equipo del Top 5.');
        $stPremios = $db->prepare("SELECT posicion_final,puntos,COUNT(*) total FROM ranking_movimientos
            WHERE liga_id=? AND tipo='premio_final' AND anulado_at IS NULL GROUP BY posicion_final,puntos ORDER BY posicion_final");
        $stPremios->execute([$ligaPremioId]);
        $filasPremio = $stPremios->fetchAll();
        $premiosCorrectos = count($filasPremio) === 5;
        foreach ($filasPremio as $fila) {
            $pos = (int)$fila['posicion_final'];
            $premiosCorrectos = $premiosCorrectos && (int)$fila['total'] === 2 && (int)$fila['puntos'] === $escalaPremio[$pos];
        }
        ranking_assert($premiosCorrectos, 'Los premios Top 5 respetan la escala de la categoría.');
    } finally {
        $db->rollBack();
    }
}

echo "Pruebas del ranking individual completadas.\n";
