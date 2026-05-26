<?php
require_once __DIR__ . '/includes/functions.php';

$db = epl_db();
$liga = epl_liga_activa();

if (!$liga) {
    echo "No hay liga activa.\n";
    exit;
}

echo "Liga Activa: " . $liga['nombre'] . " (ID: " . $liga['id'] . ")\n";
echo "Fecha actual (PHP): " . date('Y-m-d H:i:s') . "\n";

// Definir lunes y domingo de esta semana
$lunes = date('Y-m-d 00:00:00', strtotime('monday this week'));
$domingo = date('Y-m-d 23:59:59', strtotime('sunday this week'));
echo "Semana actual: Lunes {$lunes} hasta Domingo {$domingo}\n";

// Lógica 1: Buscar la jornada con más partidos en la semana actual
$stSemana = $db->prepare("
    SELECT p.jornada, COUNT(*) as cnt
    FROM partidos p
    WHERE p.liga_id = ?
      AND p.fecha_programada >= ?
      AND p.fecha_programada <= ?
      AND p.jornada IS NOT NULL AND p.jornada > 0
    GROUP BY p.jornada
    ORDER BY cnt DESC
    LIMIT 1
");
$stSemana->execute([$liga['id'], $lunes, $domingo]);
$resSemana = $stSemana->fetch();

$jornada_reciente = null;
if ($resSemana) {
    $jornada_reciente = (int)$resSemana['jornada'];
    echo "Lógica 1 (Semana Actual): Jornada {$jornada_reciente} con {$resSemana['cnt']} partidos.\n";
} else {
    // Lógica 2: Fallback a la primera jornada en el futuro
    $stFuturo = $db->prepare("
        SELECT p.jornada, p.fecha_programada
        FROM partidos p
        WHERE p.liga_id = ?
          AND p.fecha_programada >= NOW()
          AND p.jornada IS NOT NULL AND p.jornada > 0
        ORDER BY p.fecha_programada ASC
        LIMIT 1
    ");
    $stFuturo->execute([$liga['id']]);
    $resFuturo = $stFuturo->fetch();
    if ($resFuturo) {
        $jornada_reciente = (int)$resFuturo['jornada'];
        echo "Lógica 2 (Futuro cercano): Jornada {$jornada_reciente} en fecha {$resFuturo['fecha_programada']}.\n";
    } else {
        // Lógica 3: Fallback al partido más cercano absoluto
        $stAbs = $db->prepare("
            SELECT p.jornada, p.fecha_programada, ABS(TIMESTAMPDIFF(SECOND, p.fecha_programada, NOW())) as diff
            FROM partidos p
            WHERE p.liga_id = ?
              AND p.fecha_programada IS NOT NULL
              AND p.fecha_programada > '1900-01-01'
              AND p.jornada IS NOT NULL AND p.jornada > 0
            ORDER BY diff ASC
            LIMIT 1
        ");
        $stAbs->execute([$liga['id']]);
        $resAbs = $stAbs->fetch();
        if ($resAbs) {
            $jornada_reciente = (int)$resAbs['jornada'];
            echo "Lógica 3 (Absoluto cercano): Jornada {$jornada_reciente} en fecha {$resAbs['fecha_programada']}.\n";
        }
    }
}

echo "\nResultado final: Jornada detectada como reciente = " . ($jornada_reciente ?? 'Ninguna') . "\n";
?>
