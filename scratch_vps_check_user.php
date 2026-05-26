<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$db = epl_db();
if (session_status() === PHP_SESSION_NONE) session_start();
echo "Session Jugador ID: " . ($_SESSION['jugador_id'] ?? 'Ninguno') . "\n";
if (isset($_SESSION['jugador_id'])) {
    $st = $db->prepare("SELECT * FROM jugadores WHERE id = ?");
    $st->execute([$_SESSION['jugador_id']]);
    print_r($st->fetch(PDO::FETCH_ASSOC));
}
$st40 = $db->query("SELECT * FROM jugadores WHERE id = 40");
echo "Jugador ID 40:\n";
print_r($st40->fetch(PDO::FETCH_ASSOC));
?>
