<?php
require_once __DIR__ . '/includes/functions.php';
$db = epl_db();

$st = $db->query("SELECT * FROM equipos WHERE id = 12");
$equipo = $st->fetch(PDO::FETCH_ASSOC);
print_r($equipo);

if ($equipo) {
    $stJ = $db->prepare("SELECT id, nombre, apellido, email FROM jugadores WHERE id IN (?, ?)");
    $stJ->execute([$equipo['jugador1_id'], $equipo['jugador2_id']]);
    print_r($stJ->fetchAll(PDO::FETCH_ASSOC));
}
?>
