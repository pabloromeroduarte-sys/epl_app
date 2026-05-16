<?php
/**
 * Campos de perfil jugador (mismos que registro.php).
 * $pf: val (array), prefix, compact, show_email, email_readonly, require_telefono, show_talla
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/epl_perfil_data.php';

$pf_val            = $pf['val'] ?? [];
$pf_prefix         = $pf['prefix'] ?? 'pf';
$pf_compact        = !empty($pf['compact']);
$pf_show_email     = !empty($pf['show_email']);
$pf_email_ro       = !empty($pf['email_readonly']);
$pf_req_tel        = ($pf['require_telefono'] ?? true);
$pf_show_talla     = !empty($pf['show_talla']);

$pf_h = static function (string $k) use ($pf_val): string {
    return epl_h($pf_val[$k] ?? '');
};

$inp = $pf_compact
    ? 'width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem'
    : 'form-control';
$lbl = $pf_compact
    ? 'font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem'
    : 'form-label';
$wrap = $pf_compact ? 'margin-bottom:.85rem' : 'form-group';
$grid = $pf_compact
    ? 'display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.85rem'
    : 'grid-2';

$tel = epl_parse_telefono($pf_val['telefono'] ?? '');
$tel_cc  = $tel['cc'];
$tel_num = $tel['num'];

$id = static fn(string $s) => $pf_prefix . '_' . $s;
$list_comunas = 'lista_comunas_' . $pf_prefix;
$list_palas   = 'lista_palas_' . $pf_prefix;
?>

<?php if ($pf_show_email): ?>
<div class="<?= $pf_compact ? '' : 'form-group' ?>" style="<?= $pf_compact ? $wrap : '' ?>">
  <label class="<?= $lbl ?>" for="<?= $id('email') ?>">Email *</label>
  <input type="email" name="email" id="<?= $id('email') ?>" required value="<?= $pf_h('email') ?>"
         placeholder="tu@email.com" class="<?= $pf_compact ? '' : 'form-control' ?>"
         style="<?= $pf_compact ? $inp . ($pf_email_ro ? ';background:#f8fafc' : '') : '' ?>"
         <?= $pf_email_ro ? 'readonly' : '' ?>>
</div>
<?php endif; ?>

<div style="<?= $grid ?>">
  <div class="<?= $wrap ?>" style="<?= $pf_compact ? 'margin:0' : '' ?>">
    <label class="<?= $lbl ?>" for="<?= $id('nombre') ?>">Nombre *</label>
    <input type="text" name="nombre" id="<?= $id('nombre') ?>" required value="<?= $pf_h('nombre') ?>"
           class="<?= $pf_compact ? '' : 'form-control cap-words' ?>" style="<?= $pf_compact ? $inp : '' ?>"
           autocapitalize="words" autocomplete="given-name">
  </div>
  <div class="<?= $wrap ?>" style="<?= $pf_compact ? 'margin:0' : '' ?>">
    <label class="<?= $lbl ?>" for="<?= $id('apellido') ?>">Apellido *</label>
    <input type="text" name="apellido" id="<?= $id('apellido') ?>" required value="<?= $pf_h('apellido') ?>"
           class="<?= $pf_compact ? '' : 'form-control cap-words' ?>" style="<?= $pf_compact ? $inp : '' ?>"
           autocapitalize="words" autocomplete="family-name">
  </div>
</div>

<div style="<?= $grid ?>">
  <div class="<?= $wrap ?>" style="<?= $pf_compact ? 'margin:0' : '' ?>">
    <label class="<?= $lbl ?>" for="<?= $id('rut') ?>">RUT</label>
    <input type="text" name="rut" id="<?= $id('rut') ?>" value="<?= $pf_h('rut') ?>"
           placeholder="11.111.111-1" maxlength="12" inputmode="numeric" autocomplete="off"
           class="<?= $pf_compact ? '' : 'form-control' ?>" style="<?= $pf_compact ? $inp : '' ?>">
  </div>
  <div class="<?= $wrap ?>" style="<?= $pf_compact ? 'margin:0' : '' ?>">
    <label class="<?= $lbl ?>" for="<?= $id('fecha_nacimiento') ?>">Fecha de nacimiento</label>
    <input type="date" name="fecha_nacimiento" id="<?= $id('fecha_nacimiento') ?>" value="<?= $pf_h('fecha_nacimiento') ?>"
           class="<?= $pf_compact ? '' : 'form-control' ?>" style="<?= $pf_compact ? $inp : '' ?>">
  </div>
</div>

<div style="<?= $grid ?>">
  <div class="<?= $wrap ?>" style="<?= $pf_compact ? 'margin:0' : '' ?>">
    <label class="<?= $lbl ?>" for="<?= $id('sexo') ?>">Sexo</label>
    <select name="sexo" id="<?= $id('sexo') ?>" class="<?= $pf_compact ? '' : 'form-control' ?>" style="<?= $pf_compact ? $inp : '' ?>">
      <option value="">- No indicar -</option>
      <option value="M"    <?= ($pf_val['sexo'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
      <option value="F"    <?= ($pf_val['sexo'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option>
      <option value="otro" <?= ($pf_val['sexo'] ?? '') === 'otro' ? 'selected' : '' ?>>Otro</option>
    </select>
  </div>
  <div class="<?= $wrap ?>" style="<?= $pf_compact ? 'margin:0' : '' ?>">
    <label class="<?= $lbl ?>" for="<?= $id('tel_num') ?>">Teléfono / WhatsApp<?= $pf_req_tel ? ' *' : '' ?></label>
    <div class="tel-wrap" style="display:flex;gap:.5rem;flex-wrap:wrap">
      <select id="<?= $id('tel_cc') ?>" class="<?= $pf_compact ? '' : 'form-control' ?>" style="<?= $pf_compact ? $inp : '' ?>;max-width:7rem;flex-shrink:0" aria-label="Código de país">
        <?php foreach (['+56' => '🇨🇱 +56', '+54' => '🇦🇷 +54', '+55' => '🇧🇷 +55', '+51' => '🇵🇪 +51', '+57' => '🇨🇴 +57', '+34' => '🇪🇸 +34', '+1' => '🇺🇸 +1'] as $cc => $lab): ?>
          <option value="<?= $cc ?>" <?= $tel_cc === $cc ? 'selected' : '' ?>><?= $lab ?></option>
        <?php endforeach; ?>
      </select>
      <input type="tel" id="<?= $id('tel_num') ?>" value="<?= epl_h($tel_num) ?>"
             placeholder="9 1234 5678" inputmode="tel" autocomplete="tel-national"
             class="<?= $pf_compact ? '' : 'form-control' ?>" style="<?= $pf_compact ? $inp : '' ?>;flex:1;min-width:10rem"
             <?= $pf_req_tel ? 'required' : '' ?>>
    </div>
    <input type="hidden" name="telefono" id="<?= $id('telefono') ?>" value="<?= $pf_h('telefono') ?>">
    <span class="form-hint" id="<?= $id('tel_hint') ?>" style="<?= $pf_compact ? 'font-size:.73rem;color:#94a3b8;margin-top:.2rem;display:block' : '' ?>">Formato Chile: 9 XXXX XXXX</span>
  </div>
</div>

<div style="<?= $grid ?>">
  <div class="<?= $wrap ?>" style="<?= $pf_compact ? 'margin:0' : '' ?>">
    <label class="<?= $lbl ?>" for="<?= $id('comuna') ?>">Comuna</label>
    <input type="text" name="comuna" id="<?= $id('comuna') ?>" value="<?= $pf_h('comuna') ?>"
           list="<?= $list_comunas ?>" placeholder="Escribe tu comuna..." autocomplete="off"
           class="<?= $pf_compact ? '' : 'form-control' ?>" style="<?= $pf_compact ? $inp : '' ?>">
    <datalist id="<?= $list_comunas ?>">
      <?php foreach (epl_lista_comunas_chile() as $c): ?>
        <option value="<?= epl_h($c) ?>">
      <?php endforeach; ?>
    </datalist>
  </div>
  <div class="<?= $wrap ?>" style="<?= $pf_compact ? 'margin:0' : '' ?>">
    <label class="<?= $lbl ?>" for="<?= $id('profesion') ?>">Sector / Profesión</label>
    <select name="profesion" id="<?= $id('profesion') ?>" class="<?= $pf_compact ? '' : 'form-control' ?>" style="<?= $pf_compact ? $inp : '' ?>">
      <option value="">- Selecciona -</option>
      <?php foreach (epl_lista_profesiones() as $pr): ?>
        <option value="<?= epl_h($pr) ?>" <?= ($pf_val['profesion'] ?? '') === $pr ? 'selected' : '' ?>><?= epl_h($pr) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<?php if ($pf_compact): ?>
<div style="border-top:1px solid #f1f5f9;margin:1.1rem 0 1rem;padding-top:1rem">
  <div style="font-size:.78rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.85rem">Datos deportivos</div>
<?php else: ?>
<p style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--navy);margin:1.25rem 0 .75rem;border-top:1px solid var(--gray-200);padding-top:1rem">Perfil deportivo</p>
<?php endif; ?>

<div style="<?= $grid ?>">
  <div class="<?= $wrap ?>" style="<?= $pf_compact ? 'margin:0' : '' ?>">
    <label class="<?= $lbl ?>" for="<?= $id('nivel') ?>">Categoría</label>
    <select name="nivel" id="<?= $id('nivel') ?>" class="<?= $pf_compact ? '' : 'form-control' ?>" style="<?= $pf_compact ? $inp : '' ?>">
      <option value="<?= (int)($pf_val['nivel'] ?? 5) ?>"><?= (int)($pf_val['nivel'] ?? 5) ?></option>
    </select>
    <span class="form-hint" id="<?= $id('nivel_hint') ?>" style="<?= $pf_compact ? 'font-size:.73rem;color:#94a3b8' : '' ?>"></span>
  </div>
  <div class="<?= $wrap ?>" style="<?= $pf_compact ? 'margin:0' : '' ?>">
    <label class="<?= $lbl ?>" for="<?= $id('lado') ?>">Lado de juego</label>
    <select name="lado" id="<?= $id('lado') ?>" class="<?= $pf_compact ? '' : 'form-control' ?>" style="<?= $pf_compact ? $inp : '' ?>">
      <option value="">- No definido -</option>
      <option value="derecha" <?= ($pf_val['lado'] ?? '') === 'derecha' ? 'selected' : '' ?>>Drive</option>
      <option value="reves"   <?= ($pf_val['lado'] ?? '') === 'reves'   ? 'selected' : '' ?>>Revés</option>
      <option value="ambos"   <?= ($pf_val['lado'] ?? '') === 'ambos'   ? 'selected' : '' ?>>Ambos</option>
    </select>
  </div>
</div>

<div style="<?= $grid ?>">
  <div class="<?= $wrap ?>" style="<?= $pf_compact ? 'margin:0' : '' ?>">
    <label class="<?= $lbl ?>" for="<?= $id('pala') ?>">Marca de pala</label>
    <input type="text" name="pala" id="<?= $id('pala') ?>" value="<?= $pf_h('pala') ?>"
           list="<?= $list_palas ?>" placeholder="Marca / Modelo" autocomplete="off"
           class="<?= $pf_compact ? '' : 'form-control' ?>" style="<?= $pf_compact ? $inp : '' ?>">
    <datalist id="<?= $list_palas ?>">
      <?php foreach (epl_lista_marcas_pala() as $mp): ?>
        <option value="<?= epl_h($mp) ?>">
      <?php endforeach; ?>
    </datalist>
  </div>
  <?php if ($pf_show_talla): ?>
  <div class="<?= $wrap ?>" style="<?= $pf_compact ? 'margin:0' : '' ?>">
    <label class="<?= $lbl ?>" for="<?= $id('talla') ?>">Talla de camiseta</label>
    <select name="talla" id="<?= $id('talla') ?>" class="<?= $pf_compact ? '' : 'form-control' ?>" style="<?= $pf_compact ? $inp : '' ?>">
      <option value="">- Selecciona -</option>
      <?php foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'] as $t): ?>
        <option value="<?= $t ?>" <?= ($pf_val['talla'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php else: ?>
  <div class="<?= $wrap ?>" style="<?= $pf_compact ? 'margin:0' : '' ?>">
    <label class="<?= $lbl ?>" for="<?= $id('frecuencia_juego') ?>">Frecuencia de juego</label>
    <select name="frecuencia_juego" id="<?= $id('frecuencia_juego') ?>" class="<?= $pf_compact ? '' : 'form-control' ?>" style="<?= $pf_compact ? $inp : '' ?>">
      <option value="">- No indicar -</option>
      <option value="1_semana"  <?= ($pf_val['frecuencia_juego'] ?? '') === '1_semana' ? 'selected' : '' ?>>1 vez por semana</option>
      <option value="2_semana"  <?= ($pf_val['frecuencia_juego'] ?? '') === '2_semana' ? 'selected' : '' ?>>2 veces por semana</option>
      <option value="3_o_mas"   <?= ($pf_val['frecuencia_juego'] ?? '') === '3_o_mas' ? 'selected' : '' ?>>3 o más por semana</option>
      <option value="ocasional" <?= ($pf_val['frecuencia_juego'] ?? '') === 'ocasional' ? 'selected' : '' ?>>Ocasional</option>
    </select>
  </div>
  <?php endif; ?>
</div>

<?php if ($pf_show_talla): ?>
<div style="<?= $pf_compact ? $wrap : 'form-group' ?>">
  <label class="<?= $lbl ?>" for="<?= $id('frecuencia_juego') ?>">Frecuencia de juego</label>
  <select name="frecuencia_juego" id="<?= $id('frecuencia_juego') ?>" class="<?= $pf_compact ? '' : 'form-control' ?>" style="<?= $pf_compact ? $inp : '' ?>">
    <option value="">- Selecciona -</option>
    <option value="1_semana"  <?= ($pf_val['frecuencia_juego'] ?? '') === '1_semana' ? 'selected' : '' ?>>1 vez por semana</option>
    <option value="2_semana"  <?= ($pf_val['frecuencia_juego'] ?? '') === '2_semana' ? 'selected' : '' ?>>2 veces por semana</option>
    <option value="3_o_mas"   <?= ($pf_val['frecuencia_juego'] ?? '') === '3_o_mas' ? 'selected' : '' ?>>3 o más veces</option>
    <option value="ocasional" <?= ($pf_val['frecuencia_juego'] ?? '') === 'ocasional' ? 'selected' : '' ?>>Ocasional</option>
  </select>
</div>
<?php endif; ?>

<?php if ($pf_compact): ?></div><?php endif; ?>
