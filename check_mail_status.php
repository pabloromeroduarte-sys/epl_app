<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail.php';
epl_require_admin();

$db = epl_db();

// 1. Mostrar configuración actual (sin revelar contraseña completa)
$cfg = epl_smtp_config();
echo "<h2>Configuración SMTP Actual</h2>";
echo "<ul>";
echo "<li><strong>Habilitado:</strong> " . (epl_smtp_habilitado() ? 'SÍ' : 'NO') . "</li>";
echo "<li><strong>Host:</strong> " . htmlspecialchars($cfg['host']) . "</li>";
echo "<li><strong>Port:</strong> " . htmlspecialchars($cfg['port']) . "</li>";
echo "<li><strong>Encryption:</strong> " . htmlspecialchars($cfg['encryption']) . "</li>";
echo "<li><strong>User:</strong> " . htmlspecialchars($cfg['user']) . "</li>";
echo "<li><strong>Pass length:</strong> " . strlen($cfg['pass']) . "</li>";
echo "<li><strong>From:</strong> " . htmlspecialchars($cfg['from_email']) . "</li>";
echo "</ul>";

// 2. Mostrar cola de correos
echo "<h2>Cola de Correos (Últimos 20)</h2>";
try {
    $st = $db->query("SELECT id, to_email, subject, estado, intentos, error_msg, created_at, sent_at FROM mail_queue ORDER BY id DESC LIMIT 20");
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "<p>No hay correos en la cola (la tabla está vacía).</p>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse; font-family:sans-serif;'>";
        echo "<tr style='background:#eee;'>";
        echo "<th>ID</th><th>Destinatario</th><th>Asunto</th><th>Estado</th><th>Intentos</th><th>Error</th><th>Creado</th><th>Enviado</th>";
        echo "</tr>";
        foreach ($rows as $r) {
            $color = '#fff';
            if ($r['estado'] === 'enviado') $color = '#e2f0d9';
            if ($r['estado'] === 'error') $color = '#fce4d6';
            if ($r['estado'] === 'enviando') $color = '#fff2cc';

            echo "<tr style='background:$color;'>";
            echo "<td>" . $r['id'] . "</td>";
            echo "<td>" . htmlspecialchars($r['to_email']) . "</td>";
            echo "<td>" . htmlspecialchars($r['subject']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($r['estado']) . "</strong></td>";
            echo "<td>" . $r['intentos'] . "</td>";
            echo "<td><pre style='margin:0; max-width:400px; overflow:auto;'>" . htmlspecialchars($r['error_msg'] ?? '') . "</pre></td>";
            echo "<td>" . $r['created_at'] . "</td>";
            echo "<td>" . ($r['sent_at'] ?? '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Throwable $e) {
    echo "<p>Error al leer la cola: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 3. Ofrecer opción de forzar ejecución del cron desde la web para ver depuración en vivo
echo "<h2>Depuración de Envío Directo</h2>";
echo "<p><a href='?force_send=1'>[Hacer clic aquí para forzar ejecución manual de prueba del cron en vivo]</a></p>";

if (isset($_GET['force_send'])) {
    echo "<h3>Resultados de la ejecución en vivo:</h3>";
    echo "<pre style='background:#f4f4f4; padding:10px;'>";
    
    // Obtener los pendientes
    $st = $db->query("
        SELECT id, to_email, to_name, subject, body_html, intentos
        FROM mail_queue
        WHERE estado IN ('pendiente','enviando')
          AND intentos < 3
        ORDER BY created_at ASC
        LIMIT 5
    ");
    $pendientes = $st->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pendientes)) {
        echo "Sin correos pendientes en la cola.\n";
    } else {
        echo "Procesando " . count($pendientes) . " correo(s) pendientes...\n";
        foreach ($pendientes as $mail) {
            $id = (int)$mail['id'];
            echo "Enviando #{$id} a {$mail['to_email']}...\n";
            
            // Forzar marcar intento
            $db->prepare("UPDATE mail_queue SET estado='enviando', intentos=intentos+1 WHERE id=?")->execute([$id]);
            
            $result = epl_mail_enviar_directo(
                $mail['to_email'],
                $mail['subject'],
                $mail['body_html'],
                $mail['to_name']
            );
            
            if ($result['ok']) {
                $db->prepare("UPDATE mail_queue SET estado='enviado', sent_at=NOW() WHERE id=?")->execute([$id]);
                echo "  ✓ ÉXITO\n";
            } else {
                $err = $result['error'] ?? 'Error desconocido';
                $nuevo_estado = ((int)$mail['intentos'] + 1) >= 3 ? 'error' : 'pendiente';
                $db->prepare("UPDATE mail_queue SET estado=?, error_msg=? WHERE id=?")->execute([$nuevo_estado, $err, $id]);
                echo "  ✗ ERROR: {$err}\n";
            }
        }
    }
    echo "</pre>";
}
