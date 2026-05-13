<?php
/**
 * Swap venues from La Dehesa to Santa Blanca for matchday 8+
 */
error_reporting(E_ALL); ini_set('display_errors', 1);

$db = new PDO('mysql:host=localhost;dbname=epleague;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$idSantaBlanca = 2;
$idLaDehesa    = 17;

// 1. Get courts for both
$sbCourts = $db->query("SELECT id, nombre FROM recintos WHERE superior_id = $idSantaBlanca")->fetchAll(PDO::FETCH_KEY_PAIR);
$ldCourts = $db->query("SELECT id, nombre FROM recintos WHERE superior_id = $idLaDehesa")->fetchAll(PDO::FETCH_KEY_PAIR);

// Build map: La Dehesa ID -> Santa Blanca ID
$swapMap = [];
foreach ($ldCourts as $ldId => $ldName) {
    // Find matching name in Santa Blanca
    $sbId = array_search($ldName, $sbCourts);
    if ($sbId !== false) {
        $swapMap[$ldId] = $sbId;
    }
}

echo "Mapping build: " . count($swapMap) . " courts identified for swapping.\n";

// 2. Update partidos
$stUpd = $db->prepare("UPDATE partidos SET recinto_id = ? WHERE id = ?");

$partidos = $db->query("SELECT id, recinto_id, jornada FROM partidos WHERE jornada >= 8 AND recinto_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

$count = 0;
foreach ($partidos as $p) {
    if (isset($swapMap[$p['recinto_id']])) {
        $stUpd->execute([$swapMap[$p['recinto_id']], $p['id']]);
        $count++;
    }
}

echo "Partidos actualizados de La Dehesa a Santa Blanca: $count\n";
