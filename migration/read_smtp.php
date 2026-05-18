<?php
require_once dirname(__DIR__) . '/includes/db.php';
$st = epl_db()->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'smtp%'");
foreach ($st->fetchAll() as $r) {
    echo $r['clave'] . '=' . $r['valor'] . "\n";
}
