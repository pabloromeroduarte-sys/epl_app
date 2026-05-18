# -*- coding: utf-8 -*-
from pathlib import Path

root = Path(__file__).resolve().parent.parent

PARTNER_BLOCK = """            <form method="POST" id="formAceptarPartner">
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
            </form>"""

PARTNER_FILE_BLOCK = """      <form method="POST" id="formPartner">
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
      </form>"""

def patch_inscribirse():
    path = root / 'inscribirse.php'
    lines = path.read_text(encoding='utf-8').splitlines(keepends=True)
    start = end = None
    for i, line in enumerate(lines):
        if start is None and '<form method="POST">' in line and i > 500:
            start = i
        if start is not None and end is None and '<button type="submit"' in line and 'Confirmar datos' in ''.join(lines[i:i+3]):
            end = i
            break
    if start is None or end is None:
        raise SystemExit('inscribirse.php: no se encontro bloque partner')
    lines[start:end] = [PARTNER_BLOCK + '\n']
    path.write_text(''.join(lines), encoding='utf-8', newline='\n')
    print('Patched inscribirse.php')

def patch_partner_file():
    path = root / 'inscribirse_partner.php'
    text = path.read_text(encoding='utf-8')
    a = text.find('      <form method="POST">')
    b = text.find('      </form>', a)
    if a < 0 or b < 0:
        raise SystemExit('inscribirse_partner.php: no form')
    # include until closing form after button
    b = text.find('      </form>', b + 10)
    header_extra = """
require_once __DIR__ . '/includes/epl_perfil_data.php';

$jugador_actual = epl_jugador_db() ?: epl_jugador_actual();
$pf_val         = epl_perfil_val_desde_jugador($jugador_actual);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($pf_val) as $campo) {
        if (isset($_POST[$campo])) {
            $pf_val[$campo] = trim((string) $_POST[$campo]);
        }
    }
"""
    text = text.replace(
        "$jugador_actual = epl_jugador_actual();",
        header_extra.strip(),
        1,
    )
    a = text.find('      <form method="POST">')
    b = text.find('      </form>', a) + len('      </form>')
    text = text[:a] + PARTNER_FILE_BLOCK + '\n' + text[b:]
    footer = """
<?php
$pf_scripts = ['prefix' => 'partner', 'form_id' => 'formPartner', 'saved_nivel' => $pf_val['nivel'] ?? 5];
include __DIR__ . '/includes/partials/form_perfil_jugador_scripts.php';
require_once __DIR__ . '/includes/footer.php';
"""
    text = text.replace("<?php require_once __DIR__ . '/includes/footer.php'; ?>", footer.strip())
    path.write_text(text, encoding='utf-8', newline='\n')
    print('Patched inscribirse_partner.php')

if __name__ == '__main__':
    patch_inscribirse()
    patch_partner_file()
