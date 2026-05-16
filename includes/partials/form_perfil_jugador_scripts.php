<?php
/** $pf_scripts: prefix, form_id, saved_nivel (int|string) */
declare(strict_types=1);
$pf_prefix   = $pf_scripts['prefix'] ?? 'pf';
$pf_form_id  = $pf_scripts['form_id'] ?? '';
$pf_saved_nv = json_encode($pf_scripts['saved_nivel'] ?? '');
$pf_pfx      = preg_replace('/[^a-zA-Z0-9_]/', '', $pf_prefix);
?>
<script>
(function() {
  var pfx = <?= json_encode($pf_pfx) ?>;

  document.querySelectorAll('.cap-words').forEach(function(el) {
    el.addEventListener('input', function() {
      var pos = this.selectionStart, val = this.value;
      var formatted = val.replace(/\b\w/g, function(c) { return c.toUpperCase(); });
      if (formatted !== val) {
        this.value = formatted;
        this.setSelectionRange(pos, pos);
      }
    });
  });

  var rutInput = document.getElementById(pfx + '_rut');
  if (rutInput) {
    function formatRut(raw) {
      var clean = raw.replace(/[^0-9kK]/g, '').toUpperCase();
      if (clean.length < 2) return clean;
      var dv = clean.slice(-1), body = clean.slice(0, -1), fmt = '';
      for (var i = body.length - 1, cnt = 0; i >= 0; i--, cnt++) {
        if (cnt > 0 && cnt % 3 === 0) fmt = '.' + fmt;
        fmt = body[i] + fmt;
      }
      return fmt + '-' + dv;
    }
    rutInput.addEventListener('input', function() {
      var pos = this.selectionStart, old = this.value, fmt = formatRut(old);
      if (fmt !== old) {
        this.value = fmt;
        this.setSelectionRange(pos + (fmt.length - old.length), pos + (fmt.length - old.length));
      }
    });
    rutInput.addEventListener('blur', function() { this.value = formatRut(this.value); });
  }

  var ccSel  = document.getElementById(pfx + '_tel_cc');
  var numInp = document.getElementById(pfx + '_tel_num');
  var hidden = document.getElementById(pfx + '_telefono');
  var hint   = document.getElementById(pfx + '_tel_hint');
  if (ccSel && numInp && hidden) {
    function formatChilean(digits) {
      if (!digits) return '';
      digits = digits.replace(/\D/g, '');
      if (digits.startsWith('569')) digits = digits.slice(2);
      else if (digits.startsWith('56')) digits = digits.slice(2);
      if (!digits) return '';
      var first = digits.slice(0, 1), rest = digits.slice(1, 9);
      var formatted = first;
      if (rest.length > 0) formatted += ' ' + rest.slice(0, 4);
      if (rest.length > 4) formatted += ' ' + rest.slice(4, 8);
      return formatted;
    }
    function updateHidden() {
      hidden.value = numInp.value.trim() ? (ccSel.value + ' ' + numInp.value.trim()) : '';
    }
    function onNumInput() {
      if (ccSel.value === '+56') {
        var pos = numInp.selectionStart, raw = numInp.value, fmt = formatChilean(raw);
        if (fmt !== raw) {
          numInp.value = fmt;
          numInp.setSelectionRange(pos + (fmt.length - raw.length), pos + (fmt.length - raw.length));
        }
      }
      updateHidden();
    }
    function onCcChange() {
      if (ccSel.value === '+56') {
        if (hint) hint.textContent = 'Formato: 9 XXXX XXXX';
        numInp.placeholder = '9 1234 5678';
      } else {
        if (hint) hint.textContent = 'Ingresa el número sin el código de país';
        numInp.placeholder = 'Número de teléfono';
      }
      updateHidden();
    }
    numInp.addEventListener('input', onNumInput);
    numInp.addEventListener('blur', onNumInput);
    ccSel.addEventListener('change', onCcChange);
    onCcChange();
    updateHidden();
  }

  var sexoSel = document.getElementById(pfx + '_sexo');
  var nivelSel = document.getElementById(pfx + '_nivel');
  var nivelHint = document.getElementById(pfx + '_nivel_hint');
  if (sexoSel && nivelSel) {
    var savedNivel = <?= $pf_saved_nv ?>;
    var catsMasc = [['1','1ª categoría'],['2','2ª categoría'],['3','3ª categoría'],['4','4ª categoría'],['5','5ª categoría'],['6','6ª categoría']];
    var catsFem = [['1','Categoría A'],['2','Categoría B'],['3','Categoría C'],['4','Categoría D']];
    function buildCats() {
      var sexo = sexoSel.value;
      var cats = (sexo === 'F') ? catsFem : catsMasc;
      var def = (sexo === 'F') ? '2' : '5';
      var cur = savedNivel || nivelSel.value || def;
      nivelSel.innerHTML = '';
      cats.forEach(function(c) {
        var opt = document.createElement('option');
        opt.value = c[0];
        opt.textContent = c[1];
        if (c[0] === String(cur)) opt.selected = true;
        nivelSel.appendChild(opt);
      });
      if (!nivelSel.value) nivelSel.value = def;
      if (nivelHint) {
        nivelHint.textContent = (sexo === 'F')
          ? 'A = nivel más alto, D = nivel inicial'
          : '1ª = nivel más alto, 6ª = nivel inicial';
      }
    }
    sexoSel.addEventListener('change', function() { savedNivel = ''; buildCats(); });
    buildCats();
  }

  <?php if ($pf_form_id !== ''): ?>
  var formEl = document.getElementById(<?= json_encode($pf_form_id) ?>);
  if (formEl) {
    formEl.addEventListener('submit', function() {
      if (ccSel && numInp && hidden && numInp.value.trim()) {
        hidden.value = ccSel.value + ' ' + numInp.value.trim();
      }
    });
  }
  <?php endif; ?>
})();
</script>
