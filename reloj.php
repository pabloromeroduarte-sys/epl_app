<?php
declare(strict_types=1);

$page_title = 'Vincular reloj';
$page_css = 'reloj';
$player_tab = '';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/watch.php';
epl_require_login();

$player = epl_jugador_actual();
$message = '';
$error = '';
epl_session_start();
if (empty($_SESSION['epl_watch_csrf'])) {
    $_SESSION['epl_watch_csrf'] = bin2hex(random_bytes(24));
}
$csrf = (string)$_SESSION['epl_watch_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'La sesión venció. Actualiza la página e inténtalo nuevamente.';
    } elseif (isset($_POST['authorize'])) {
        $result = epl_watch_authorize_code((string)($_POST['code'] ?? ''), (int)$player['id']);
        if ($result['ok']) {
            $message = (string)$result['message'];
        } else {
            $error = (string)$result['error'];
        }
    } elseif (isset($_POST['revoke'])) {
        $message = epl_watch_revoke_device((int)($_POST['token_id'] ?? 0), (int)$player['id'])
            ? 'Se desvinculó el reloj.'
            : 'El reloj ya no estaba vinculado.';
    }
}

$prefill = epl_watch_clean_code((string)($_GET['code'] ?? $_POST['code'] ?? ''));
if (strlen($prefill) === 6) {
    $prefill = substr($prefill, 0, 3) . '-' . substr($prefill, 3);
}
$devices = epl_watch_devices_for_player((int)$player['id']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main watch-link-main">
  <section class="watch-link-hero">
    <div>
      <span class="watch-link-kicker">EPL SCORE · WEAR OS</span>
      <h1>Vincula tu reloj</h1>
      <p>Ingresa el código que aparece en tu Galaxy Watch. EPL nunca enviará tu contraseña al reloj.</p>
    </div>
    <div class="watch-link-orbit" aria-hidden="true">⌚</div>
  </section>

  <?php if ($message): ?><div class="alert alert-success"><?= epl_h($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= epl_h($error) ?></div><?php endif; ?>

  <section class="watch-link-card">
    <div class="watch-link-step"><strong>1</strong><span>Abre EPL Score en el reloj</span></div>
    <div class="watch-link-step"><strong>2</strong><span>Escribe aquí el código de seis caracteres</span></div>
    <div class="watch-link-step"><strong>3</strong><span>Confirma y vuelve al reloj</span></div>

    <form method="post" class="watch-code-form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= epl_h($csrf) ?>">
      <label for="watchCode">Código del reloj</label>
      <input id="watchCode" name="code" value="<?= epl_h($prefill) ?>" maxlength="7"
             placeholder="ABC-234" inputmode="text" autocapitalize="characters" required>
      <button class="btn btn-primary" type="submit" name="authorize" value="1">Vincular Galaxy Watch</button>
    </form>
  </section>

  <section class="watch-devices">
    <div class="watch-devices-head">
      <div><span>SEGURIDAD</span><h2>Relojes vinculados</h2></div>
      <strong><?= count($devices) ?></strong>
    </div>
    <?php if (!$devices): ?>
      <p class="watch-empty">Todavía no tienes relojes vinculados.</p>
    <?php else: ?>
      <?php foreach ($devices as $device): ?>
        <article class="watch-device">
          <div class="watch-device-icon">⌚</div>
          <div>
            <h3><?= epl_h($device['device_name']) ?></h3>
            <p>Token ····<?= epl_h($device['token_hint']) ?> · Último uso: <?= $device['last_used_at'] ? date('d/m/Y H:i', strtotime($device['last_used_at'])) : 'Aún no utilizado' ?></p>
          </div>
          <form method="post" onsubmit="return confirm('¿Desvincular este reloj?');">
            <input type="hidden" name="csrf" value="<?= epl_h($csrf) ?>">
            <input type="hidden" name="token_id" value="<?= (int)$device['id'] ?>">
            <button type="submit" name="revoke" value="1">Desvincular</button>
          </form>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</main>
</div>

<script>
document.getElementById('watchCode')?.addEventListener('input', function () {
  const clean = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
  this.value = clean.length > 3 ? clean.slice(0, 3) + '-' + clean.slice(3) : clean;
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

