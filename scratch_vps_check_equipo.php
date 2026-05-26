<?php
require_once __DIR__ . '/includes/functions.php';
$db = epl_db();
$liga = epl_liga_activa();

$st = $db->prepare("
    SELECT e.* FROM equipos e
    WHERE e.liga_id = ? AND (e.jugador1_id = 40 OR e.jugador2_id = 40)
");
$st->execute([$liga['id']]);
print_r($st->fetchAll(PDO::FETCH_ASSOC));
?>
