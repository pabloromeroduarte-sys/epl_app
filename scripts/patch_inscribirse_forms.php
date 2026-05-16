<?php
/** Parche UTF-8: formularios partner con partials. */
declare(strict_types=1);

$root = dirname(__DIR__);

$partnerBlock = <<<'HTML'
            <form method="POST" id="formAceptarPartner">
              <input type="hidden" name="epl_aceptar" value="1">
              <input type="hidden" name="insc_id"     value="<?= $insc['id'] ?>">
              <?php
              require_once __DIR__ . '/includes/epl_perfil_data.php';
              $pf_val = epl_perfil_val_desde_jugador($jugador);
              $pf = [
                  'val' => $pf_val, 'prefix' => 'insc', 'compact' => true,
                  'show_email' => false, 'show_talla' => true, 'require_telefono' => false,
              ];
              include __DIR__ . '/includes/partials/form_perfil_jugador.php';
              $pf_scripts = ['prefix' => 'insc', 'form_id' => 'formAceptarPartner', 'saved_nivel' => $pf_val['nivel'] ?? 5];
              include __DIR__ . '/includes/partials/form_perfil_jugador_scripts.php';
              ?>
              <button type="submit"
                style="margin-top:1rem;width:100%;padding:.75rem;background:#22c55e;color:#fff;font-weight:800;font-size:.9rem;text-transform:uppercase;letter-spacing:.05em;border:none;border-radius:10px;cursor:pointer">
                Confirmar datos y aceptar invitación
              </button>
            </form>
HTML;

$partnerFileBlock = <<<'HTML'
      <form method="POST" id="formPartner">
        <input type="hidden" name="epl_partner" value="1">
        <?php if (!$jugador_actual): ?>
        <p style="font-size:.73rem;color:#94a3b8;margin:0 0 .85rem">Si ya tenés cuenta, iniciá sesión arriba o usá el mismo email.</p>
        <?php endif; ?>
        <?php
        $pf = [
            'val' => $pf_val, 'prefix' => 'partner', 'compact' => true,
            'show_email' => true, 'email_readonly' => (bool) $jugador_actual,
            'require_telefono' => true, 'show_talla' => true,
        ];
        include __DIR__ . '/includes/partials/form_perfil_jugador.php';
        ?>
        <button type="submit" style="width:100%;padding:.9rem;background:#1C2F48;color:#C9A762;font-weight:800;font-size:.95rem;text-transform:uppercase;border:none;border-radius:12px;cursor:pointer;letter-spacing:.04em;margin-top:.5rem">
          <?= $insc_cap['precio'] > 0 ? 'Confirmar e ir a pagar →' : 'Confirmar mi inscripción →' ?>
        </button>
      </form>
HTML;

// inscribirse.php
$path = $root . '/inscribirse.php';
$text = file_get_contents($path);
if (!preg_match(
    '/<form method="POST"[^>]*>\s*<input type="hidden" name="epl_aceptar"[^>]*>.*?Confirmar datos y aceptar invitación.*?<\/form>/s',
    $text,
    $m,
    PREG_OFFSET_CAPTURE
)) {
    fwrite(STDERR, "inscribirse: bloque partner no encontrado\n");
    exit(1);
}
$start = $m[0][1];
$len   = strlen($m[0][0]);
$text  = substr($text, 0, $start) . $partnerBlock . substr($text, $start + $len);
file_put_contents($path, $text);
echo "Patched inscribirse.php\n";

// inscribirse_partner.php
$path = $root . '/inscribirse_partner.php';
$text = file_get_contents($path);

$needle = "\$jugador_actual = epl_jugador_actual();";
$insert = <<<'PHP'
require_once __DIR__ . '/includes/epl_perfil_data.php';

$jugador_actual = epl_jugador_db() ?: epl_jugador_actual();
$pf_val         = epl_perfil_val_desde_jugador($jugador_actual);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['epl_partner'])) {
    foreach (array_keys($pf_val) as $campo) {
        if (isset($_POST[$campo])) {
            $pf_val[$campo] = trim((string) $_POST[$campo]);
        }
    }
}
PHP;

if (!str_contains($text, 'epl_perfil_val_desde_jugador')) {
    $pos = strpos($text, "// ── Procesar formulario");
    if ($pos === false) {
        fwrite(STDERR, "partner: POST block no encontrado\n");
        exit(1);
    }
    $text = substr($text, 0, $pos) . $insert . "\n\n" . substr($text, $pos);
}

$a = strpos($text, "      <form method=\"POST\">");
if ($a === false) {
    $a = strpos($text, '      <form method="POST" id="formPartner">');
}
if ($a === false) {
    fwrite(STDERR, "partner: form no encontrado\n");
    exit(1);
}
$b = strpos($text, '      </form>', $a);
$b += strlen('      </form>');
$text = substr($text, 0, $a) . $partnerFileBlock . "\n" . substr($text, $b);

$text = str_replace(
    "<?php require_once __DIR__ . '/includes/footer.php'; ?>",
    "<?php\n\$pf_scripts = ['prefix' => 'partner', 'form_id' => 'formPartner', 'saved_nivel' => \$pf_val['nivel'] ?? 5];\ninclude __DIR__ . '/includes/partials/form_perfil_jugador_scripts.php';\nrequire_once __DIR__ . '/includes/footer.php';\n?>",
    $text
);

$text = preg_replace(
    '/\$jugador_actual = epl_jugador_actual\(\);\s*\?>/',
    '?>',
    $text,
    1
);

file_put_contents($path, $text);
echo "Patched inscribirse_partner.php\n";
