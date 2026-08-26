<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la consola local.');
}

require_once __DIR__ . '/../includes/functions.php';

try {
    $resultado = epl_ranking_migrar_historico();
    echo "Ranking individual migrado correctamente.\n";
    echo "Competiciones revisadas: {$resultado['ligas']}\n";
    echo "Movimientos de victoria sincronizados: {$resultado['movimientos']}\n";
    echo "Premios históricos importados: {$resultado['premios_legacy']}\n";
    echo "Incidencias pendientes de revisión: {$resultado['incidencias']}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
