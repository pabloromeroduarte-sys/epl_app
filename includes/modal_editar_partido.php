<?php
// Modal de edición de partido reutilizable (admin).
// Requiere: $todos_recintos (árbol de recintos con ['id','label']).
// Opcional:  $modal_return_to (ruta admin a la que volver tras guardar, ej. 'calendario.php?mes=2026-06&liga=0')
$modal_return_to = $modal_return_to ?? '';
?>
<style>
#modalEditarPartido .score-input-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.35rem 0}
#modalEditarPartido .score-label{font-weight:700;color:var(--navy);font-size:.85rem;min-width:60px}
#modalEditarPartido .score-fields{display:flex;align-items:center;gap:.5rem}
#modalEditarPartido .score-num{width:60px;text-align:center}
#modalEditarPartido .score-sep{color:var(--gray-300)}
</style>
<div id="modalEditarPartido" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:520px;max-height:92vh;overflow-y:auto">
    <div class="card-head" style="position:sticky;top:0;background:#fff;z-index:1">
      <h3 id="editPartidoTitle" style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Editar partido</h3>
      <button onclick="document.getElementById('modalEditarPartido').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post" action="<?= epl_h(epl_url('admin/partidos.php')) ?>">
        <input type="hidden" name="action" value="editar_resultado">
        <input type="hidden" name="partido_id" id="editPartidoId">
        <input type="hidden" name="return_to" value="<?= epl_h($modal_return_to) ?>">

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nombre de fecha</label>
            <input type="text" name="nombre_fecha" id="editNombreFecha" class="form-control" placeholder="Fecha 1...">
          </div>
          <div class="form-group">
            <label class="form-label">Jornada N°</label>
            <input type="number" name="jornada" id="editJornada" class="form-control" min="1">
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Cancha / Recinto</label>
            <select name="recinto_id" id="editRecintoId" class="form-control">
              <option value="">— Sin recinto —</option>
              <?php foreach ($todos_recintos as $rc): ?>
                <option value="<?= $rc['id'] ?>"><?= epl_h($rc['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Fecha programada</label>
            <input type="datetime-local" name="fecha_programada" id="editFechaProg" class="form-control">
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Estado</label>
            <select name="estado" id="editEstado" class="form-control">
              <option value="pendiente">Pendiente</option>
              <option value="jugado">Jugado</option>
              <option value="reprogramado">Reprogramado</option>
              <option value="walkover">Walkover</option>
              <option value="no_presentado">No presentado</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Fecha jugado</label>
            <input type="datetime-local" name="fecha_jugado" id="editFechaJugado" class="form-control">
          </div>
        </div>

        <p style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--navy);margin:.75rem 0 .5rem">Resultado</p>
        <?php for ($s = 1; $s <= 3; $s++): ?>
        <div class="score-input-row">
          <span class="score-label">Set <?= $s ?></span>
          <div class="score-fields">
            <input type="number" name="s<?= $s ?>_l" id="s<?= $s ?>l" class="score-num form-control" min="0" max="7" placeholder="0">
            <span class="score-sep">–</span>
            <input type="number" name="s<?= $s ?>_v" id="s<?= $s ?>v" class="score-num form-control" min="0" max="7" placeholder="0">
          </div>
        </div>
        <?php endfor; ?>

        <div class="form-group">
          <label class="form-label">Alerta / Nota admin (etiqueta roja)</label>
          <input type="text" name="alerta_admin" id="editAlertaAdmin" class="form-control" placeholder="Ej: Falta confirmar cancha...">
        </div>

        <details style="margin-top:.85rem;background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;padding:.6rem .85rem">
          <summary style="cursor:pointer;font-size:.75rem;font-weight:800;color:#92400e;list-style:none">🏟️ Reserva ORIGINAL a dar de baja (opcional)</summary>
          <p style="font-size:.72rem;color:#92400e;margin:.5rem 0">Si fue reprogramado, anotá acá qué fecha/cancha tenía antes para saber qué reserva cancelar en el club.</p>
          <div class="grid-2">
            <div class="form-group" style="margin-bottom:.5rem">
              <label class="form-label" style="font-size:.65rem">Fecha original</label>
              <input type="datetime-local" name="fecha_original" id="editFechaOriginal" class="form-control">
            </div>
            <div class="form-group" style="margin-bottom:.5rem">
              <label class="form-label" style="font-size:.65rem">Cancha original</label>
              <select name="recinto_original_id" id="editRecintoOriginalId" class="form-control">
                <option value="">— Sin registrar —</option>
                <?php foreach ($todos_recintos as $rc): ?>
                  <option value="<?= $rc['id'] ?>"><?= epl_h($rc['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </details>

        <div id="editContactosWrapper" style="margin-top:1.25rem;border-top:1px solid var(--gray-100);padding-top:1rem">
          <p style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--navy);margin-bottom:.5rem">Contacto jugadores</p>
          <div id="editContactosList" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.6rem"></div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:1.25rem">Guardar cambios</button>
      </form>
    </div>
  </div>
</div>

<script>
window.editarPartido = function (btn) {
  var modal = document.getElementById('modalEditarPartido');
  if (!modal) return;
  var raw = btn.getAttribute('data-partido');
  if (!raw) return;
  var bin = atob(raw);
  var bytes = new Uint8Array(bin.length);
  for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
  var p = JSON.parse(new TextDecoder('utf-8').decode(bytes));
  function setVal(sel, v) { var el = modal.querySelector(sel); if (el) el.value = v; }
  modal.querySelector('input[name="partido_id"]').value = p.id;
  document.getElementById('editPartidoTitle').textContent = p.local_nombre + ' vs ' + p.visitante_nombre;
  setVal('input[name="nombre_fecha"]', p.nombre_fecha || '');
  setVal('input[name="jornada"]', p.jornada || '');
  setVal('select[name="recinto_id"]', p.recinto_id || '');
  setVal('select[name="estado"]', p.estado || 'pendiente');
  setVal('input[name="fecha_programada"]', p.fecha_programada ? String(p.fecha_programada).replace(' ', 'T').substring(0,16) : '');
  setVal('input[name="fecha_jugado"]', p.fecha_jugado ? String(p.fecha_jugado).replace(' ', 'T').substring(0,16) : '');
  [1,2,3].forEach(function(s){
    setVal('input[name="s'+s+'_l"]', p['games_s'+s+'_local'] != null ? p['games_s'+s+'_local'] : '');
    setVal('input[name="s'+s+'_v"]', p['games_s'+s+'_visitante'] != null ? p['games_s'+s+'_visitante'] : '');
  });
  setVal('input[name="alerta_admin"]', p.alerta_admin || '');
  setVal('input[name="fecha_original"]', p.fecha_original ? String(p.fecha_original).replace(' ', 'T').substring(0,16) : '');
  setVal('select[name="recinto_original_id"]', p.recinto_original_id || '');

  var contactList = document.getElementById('editContactosList');
  contactList.innerHTML = '';
  [
    { n: p.jl1_n, a: p.jl1_a, t: p.jl1_t, role: 'Local' },
    { n: p.jl2_n, a: p.jl2_a, t: p.jl2_t, role: 'Local' },
    { n: p.jv1_n, a: p.jv1_a, t: p.jv1_t, role: 'Visitante' },
    { n: p.jv2_n, a: p.jv2_a, t: p.jv2_t, role: 'Visitante' },
  ].forEach(function(pl){
    if (!pl.n) return;
    var div = document.createElement('div');
    div.style.cssText = 'background:#f8fafc;padding:.5rem;border-radius:8px;border:1px solid #f1f5f9';
    var tel = pl.t ? pl.t.replace(/\D/g, '') : '';
    var wspLink = '';
    if (tel) {
      var clean = tel.indexOf('56') === 0 ? tel : '56' + tel;
      var msg = encodeURIComponent('Hola ' + pl.n + ', te contacto de Elite Padel League por tu partido: ' + p.local_nombre + ' vs ' + p.visitante_nombre);
      wspLink = 'https://wa.me/' + clean + '?text=' + msg;
    }
    var html = '<div style="font-size:.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase">' + pl.role + '</div>';
    html += '<div style="font-size:.78rem;font-weight:600;color:var(--navy);margin-bottom:.3rem">' + pl.n + ' ' + (pl.a || '') + '</div>';
    html += wspLink
      ? '<a href="' + wspLink + '" target="_blank" style="background:#25D366;color:#fff;padding:.3rem .5rem;border-radius:5px;font-size:.65rem;font-weight:700;text-decoration:none;display:block;text-align:center">WhatsApp</a>'
      : '<span style="font-size:.65rem;color:#94a3b8;font-style:italic">Sin teléfono</span>';
    div.innerHTML = html;
    contactList.appendChild(div);
  });

  modal.style.display = 'flex';
};
</script>
