<?php
$page_title = 'Buscar Jugadores';
$player_tab = 'buscar_jugadores';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
epl_require_login();

$jugador = epl_jugador_actual();
$db      = epl_db();

// ── Filtros ─────────────────────────────────────────────────────────────────
$f_q       = trim($_GET['q']        ?? '');
$f_cat_min = isset($_GET['cat_min']) && $_GET['cat_min'] !== '' ? (int)$_GET['cat_min'] : null;
$f_cat_max = isset($_GET['cat_max']) && $_GET['cat_max'] !== '' ? (int)$_GET['cat_max'] : null;
$f_comuna  = trim($_GET['comuna']   ?? '');
$f_lado    = in_array($_GET['lado'] ?? '', ['derecha','reves','ambos'], true) ? $_GET['lado'] : '';
$f_solo_inscritos = !isset($_GET['todos']);

// Si solo cat_min está seteado, igual lo usamos como exacto
if ($f_cat_min !== null && $f_cat_max === null) {
    $f_cat_max = $f_cat_min;
} elseif ($f_cat_min === null && $f_cat_max !== null) {
    $f_cat_min = $f_cat_max;
}
// Asegurar orden
if ($f_cat_min !== null && $f_cat_max !== null && $f_cat_min > $f_cat_max) {
    [$f_cat_min, $f_cat_max] = [$f_cat_max, $f_cat_min];
}

// ── Construir query ─────────────────────────────────────────────────────────
$where  = ["j.estado = 'activo'", "j.id != ?"];
$params = [(int)$jugador['id']];

if ($f_solo_inscritos) {
    // Jugadores que están en al menos un equipo de una liga activa
    $where[] = "EXISTS (
        SELECT 1 FROM equipos e
        JOIN liga_equipos le ON le.equipo_id = e.id
        JOIN ligas l ON l.id = le.liga_id
        WHERE l.estado = 'activa'
          AND (e.jugador1_id = j.id OR e.jugador2_id = j.id)
    )";
}

if ($f_q !== '') {
    $where[] = "(j.nombre LIKE ? OR j.apellido LIKE ? OR j.alias LIKE ?)";
    $like = "%{$f_q}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

if ($f_cat_min !== null && $f_cat_max !== null) {
    $where[] = "j.nivel BETWEEN ? AND ?";
    $params[] = $f_cat_min;
    $params[] = $f_cat_max;
}

if ($f_comuna !== '') {
    $where[] = "j.comuna = ?";
    $params[] = $f_comuna;
}

if ($f_lado !== '') {
    $where[] = "j.lado = ?";
    $params[] = $f_lado;
}

$whereStr = implode(' AND ', $where);
$st = $db->prepare("
    SELECT j.id, j.nombre, j.apellido, j.alias, j.foto, j.telefono,
           j.nivel, j.lado, j.comuna, j.pala, j.frecuencia_juego,
           j.created_at
    FROM jugadores j
    WHERE $whereStr
    ORDER BY j.apellido ASC, j.nombre ASC
    LIMIT 200
");
$st->execute($params);
$jugadores_lista = $st->fetchAll(PDO::FETCH_ASSOC);

// ── Comunas únicas para el filtro ──────────────────────────────────────────
$comunas_opts = $db->query("
    SELECT DISTINCT comuna FROM jugadores
    WHERE estado='activo' AND comuna IS NOT NULL AND comuna <> ''
    ORDER BY comuna ASC
")->fetchAll(PDO::FETCH_COLUMN);

$lado_labels = ['derecha' => 'Drive', 'reves' => 'Revés', 'ambos' => 'Ambos'];
$frec_labels = [
    '1_semana'  => '1 vez/sem',
    '2_semana'  => '2 veces/sem',
    '3_o_mas'   => '3+ veces/sem',
    'ocasional' => 'Ocasional',
];

// Hay filtros activos?
$hay_filtros = $f_q !== '' || $f_cat_min !== null || $f_comuna !== '' || $f_lado !== '' || !$f_solo_inscritos;
?>
<?php require_once 'includes/header.php'; ?>

<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>

<main class="dash-main">

  <div class="dash-header">
    <h1 class="dash-title">💬 Buscar Jugadores</h1>
    <p style="color:var(--gray-400);font-size:.88rem">Encontrá compañeros y rivales en la comunidad EPL — coordinen partidos por WhatsApp.</p>
  </div>

  <!-- ─────────── FILTROS ─────────── -->
  <form method="get" class="bj-filtros">
    <div class="bj-filtros-grid">
      <div class="form-group">
        <label class="form-label">🔎 Nombre o apellido</label>
        <input type="text" name="q" value="<?= epl_h($f_q) ?>" class="form-control" placeholder="Ej: García, Carlos, Cuky...">
      </div>

      <div class="form-group">
        <label class="form-label">🏆 Categoría desde</label>
        <select name="cat_min" class="form-control">
          <option value="">— Cualquiera —</option>
          <?php for ($n=1;$n<=8;$n++): ?>
            <option value="<?= $n ?>" <?= $f_cat_min===$n?'selected':'' ?>><?= $n ?>ª cat.</option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">🏆 Hasta</label>
        <select name="cat_max" class="form-control">
          <option value="">— Igual que desde —</option>
          <?php for ($n=1;$n<=8;$n++): ?>
            <option value="<?= $n ?>" <?= $f_cat_max===$n && $f_cat_min!==$f_cat_max?'selected':'' ?>><?= $n ?>ª cat.</option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">📍 Comuna</label>
        <select name="comuna" class="form-control">
          <option value="">— Todas —</option>
          <?php foreach ($comunas_opts as $c): ?>
            <option value="<?= epl_h($c) ?>" <?= $f_comuna===$c?'selected':'' ?>><?= epl_h($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">🎾 Lado</label>
        <select name="lado" class="form-control">
          <option value="">— Cualquiera —</option>
          <option value="derecha" <?= $f_lado==='derecha'?'selected':'' ?>>Drive</option>
          <option value="reves"   <?= $f_lado==='reves'  ?'selected':'' ?>>Revés</option>
          <option value="ambos"   <?= $f_lado==='ambos'  ?'selected':'' ?>>Ambos</option>
        </select>
      </div>

      <div class="form-group bj-filtros-acciones">
        <label class="form-label" style="visibility:hidden">.</label>
        <div style="display:flex;gap:.5rem">
          <button type="submit" class="btn btn-navy" style="flex:1">Filtrar</button>
          <?php if ($hay_filtros): ?>
            <a href="buscar_jugadores.php" class="btn btn-sm" style="background:var(--gray-100);color:var(--gray-600);display:inline-flex;align-items:center">Limpiar</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <label class="bj-toggle">
      <input type="checkbox" name="todos" value="1" <?= !$f_solo_inscritos?'checked':'' ?>
             onchange="this.form.submit()">
      <span>Mostrar también jugadores no inscritos en torneos activos</span>
    </label>
  </form>

  <!-- ─────────── RESULTADOS ─────────── -->
  <div class="bj-resultados-head">
    <h2 class="bj-h2">
      <?= count($jugadores_lista) ?> jugador<?= count($jugadores_lista) === 1 ? '' : 'es' ?>
      <?php if ($f_solo_inscritos): ?>
        <span class="bj-sub">inscritos en torneos activos</span>
      <?php endif; ?>
    </h2>
  </div>

  <?php if (empty($jugadores_lista)): ?>
    <div class="bj-empty">
      <div style="font-size:2.5rem;margin-bottom:.5rem">🔍</div>
      <h3 style="font-family:var(--font-head);text-transform:uppercase;color:var(--navy)">Sin resultados</h3>
      <p style="color:var(--gray-500);font-size:.9rem">Probá cambiar los filtros o ampliar la búsqueda.</p>
    </div>
  <?php else: ?>
    <div class="bj-grid">
      <?php foreach ($jugadores_lista as $j):
        $iniciales = mb_strtoupper(mb_substr($j['nombre'], 0, 1) . mb_substr($j['apellido'] ?? '', 0, 1));
        $tel = preg_replace('/[^0-9]/', '', (string)($j['telefono'] ?? ''));
        $clean = $tel ? (str_starts_with($tel, '56') ? $tel : '56' . $tel) : '';
        $msg_default = "Hola {$j['nombre']}, te vi en la Elite Padel League. ¿Tenés ganas de coordinar un partido?";
        $wsp = $clean ? "https://wa.me/{$clean}?text=" . rawurlencode($msg_default) : '';
        $perfil_url = epl_url('jugador.php?id=' . (int)$j['id']);
      ?>
      <div class="bj-card">
        <div class="bj-card-head">
          <a href="<?= $perfil_url ?>" class="bj-avatar">
            <?php if (!empty($j['foto'])): ?>
              <img src="<?= epl_h(epl_foto_jugador($j['foto'], $j['nombre'].' '.$j['apellido'])) ?>" alt="<?= epl_h($j['nombre']) ?>">
            <?php else: ?>
              <span><?= $iniciales ?></span>
            <?php endif; ?>
          </a>
          <div style="flex:1;min-width:0">
            <a href="<?= $perfil_url ?>" class="bj-nombre">
              <?= epl_h($j['nombre'].' '.$j['apellido']) ?>
            </a>
            <?php if (!empty($j['alias'])): ?>
              <div class="bj-alias">"<?= epl_h($j['alias']) ?>"</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="bj-chips">
          <?php if (!empty($j['nivel'])): ?>
            <span class="bj-chip bj-chip-cat"><?= (int)$j['nivel'] ?>ª cat.</span>
          <?php endif; ?>
          <?php if (!empty($j['lado'])): ?>
            <span class="bj-chip">🎾 <?= $lado_labels[$j['lado']] ?? ucfirst($j['lado']) ?></span>
          <?php endif; ?>
          <?php if (!empty($j['comuna'])): ?>
            <span class="bj-chip">📍 <?= epl_h($j['comuna']) ?></span>
          <?php endif; ?>
          <?php if (!empty($j['pala'])): ?>
            <span class="bj-chip">🏓 <?= epl_h($j['pala']) ?></span>
          <?php endif; ?>
          <?php if (!empty($j['frecuencia_juego']) && isset($frec_labels[$j['frecuencia_juego']])): ?>
            <span class="bj-chip">📅 <?= $frec_labels[$j['frecuencia_juego']] ?></span>
          <?php endif; ?>
        </div>

        <div class="bj-actions">
          <?php if ($wsp): ?>
            <a href="<?= $wsp ?>" target="_blank" rel="noopener" class="bj-btn bj-btn-wsp" title="Coordinar partido por WhatsApp">
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.588-5.946 0-6.556 5.332-11.888 11.888-11.888 3.176 0 6.161 1.237 8.404 3.48s3.481 5.229 3.481 8.404c0 6.556-5.332 11.888-11.888 11.888-2.003 0-3.963-.505-5.698-1.465l-6.305 1.693zm6.443-4.045c1.474.873 3.103 1.332 4.775 1.332 5.054 0 9.163-4.109 9.163-9.163s-4.109-9.163-9.163-9.163-9.163 4.109-9.163 9.163c0 1.95.623 3.856 1.799 5.437l-1.002 3.659 3.743-.999zm10.742-5.466c-.303-.151-1.788-.882-2.067-.981-.278-.099-.481-.151-.683.151-.202.303-.783.981-.96 1.183-.177.202-.354.227-.657.076-.303-.151-1.28-.471-2.438-1.504-.901-.803-1.508-1.796-1.685-2.098-.177-.302-.019-.465.132-.615.136-.135.303-.354.455-.53.151-.177.202-.303.303-.505.101-.202.051-.379-.025-.53-.076-.151-.683-1.643-.935-2.249-.245-.59-.495-.51-.683-.52l-.582-.01c-.202 0-.531.076-.809.379-.278.303-1.062 1.037-1.062 2.529 0 1.492 1.087 2.932 1.239 3.134.151.202 2.14 3.268 5.184 4.582.724.312 1.29.499 1.731.639.727.231 1.388.199 1.911.121.582-.087 1.788-.731 2.041-1.439.253-.708.253-1.313.177-1.439-.076-.126-.278-.202-.581-.353z"/></svg>
              WhatsApp
            </a>
          <?php else: ?>
            <span class="bj-no-tel">Sin teléfono cargado</span>
          <?php endif; ?>
          <button type="button" class="bj-btn bj-btn-msg"
                  onclick='bjAbrirMensaje(<?= json_encode([
                    "id" => (int)$j["id"],
                    "nombre" => $j["nombre"]." ".$j["apellido"],
                    "iniciales" => $iniciales,
                    "wsp" => $clean,
                  ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>
            ✉ Enviar mensaje
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>
</div>

<!-- Modal: Componer mensaje -->
<div id="bjModalMsg" class="bj-modal-overlay" style="display:none" onclick="if(event.target===this)bjCerrarMensaje()">
  <div class="bj-modal-content">
    <div class="bj-modal-head">
      <div style="display:flex;align-items:center;gap:.75rem;min-width:0">
        <div class="bj-modal-avatar" id="bjModalAvatar"></div>
        <div style="min-width:0">
          <div style="font-size:.7rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;font-weight:700">Mensaje para</div>
          <div style="font-weight:800;color:var(--navy);font-size:1rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" id="bjModalNombre"></div>
        </div>
      </div>
      <button onclick="bjCerrarMensaje()" class="bj-modal-close">×</button>
    </div>

    <div style="padding:1rem 1.5rem">
      <label class="form-label" style="margin-bottom:.4rem">Plantilla rápida</label>
      <div class="bj-plantillas">
        <button type="button" class="bj-plantilla" data-tpl="Hola {nombre}, te vi en la Elite Padel League. ¿Tenés ganas de coordinar un partido?">🎾 Coordinar partido</button>
        <button type="button" class="bj-plantilla" data-tpl="Hola {nombre}, ando buscando un cuarto para jugar este fin de semana. ¿Te tinca?">👥 Buscar 4to</button>
        <button type="button" class="bj-plantilla" data-tpl="Hola {nombre}, te escribo desde Elite Padel League. ¿Estás buscando partido?">👋 Saludo casual</button>
        <button type="button" class="bj-plantilla" data-tpl="Hola {nombre}, te escribo desde Elite Padel League porque podrías ser un buen rival. ¿Coordinamos?">🥇 Buen rival</button>
      </div>

      <label class="form-label" style="margin-top:1rem">Tu mensaje</label>
      <textarea id="bjMensajeTexto" class="form-control" rows="5" placeholder="Escribí tu mensaje aquí..."></textarea>
      <div style="font-size:.72rem;color:var(--gray-500);margin-top:.25rem"><span id="bjCharCount">0</span> caracteres</div>
    </div>

    <div class="bj-modal-actions">
      <button type="button" onclick="bjCerrarMensaje()" class="bj-btn-cancel">Cancelar</button>
      <a id="bjBtnWsp" href="#" target="_blank" rel="noopener" class="bj-btn bj-btn-wsp" style="flex:2;justify-content:center">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.588-5.946 0-6.556 5.332-11.888 11.888-11.888 3.176 0 6.161 1.237 8.404 3.48s3.481 5.229 3.481 8.404c0 6.556-5.332 11.888-11.888 11.888-2.003 0-3.963-.505-5.698-1.465l-6.305 1.693zm6.443-4.045c1.474.873 3.103 1.332 4.775 1.332 5.054 0 9.163-4.109 9.163-9.163s-4.109-9.163-9.163-9.163-9.163 4.109-9.163 9.163c0 1.95.623 3.856 1.799 5.437l-1.002 3.659 3.743-.999z"/></svg>
        Enviar por WhatsApp
      </a>
    </div>
  </div>
</div>

<style>
/* Filtros */
.bj-filtros {
  background: #fff;
  border: 1px solid var(--gray-100);
  border-radius: 16px;
  padding: 1.25rem;
  margin-bottom: 1.25rem;
  box-shadow: 0 2px 12px rgba(0,0,0,.04);
}
.bj-filtros-grid {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1fr 1.2fr 1fr auto;
  gap: .75rem;
  align-items: end;
}
.bj-filtros .form-group { margin: 0; }
.bj-filtros .form-label { font-size: .72rem; }
.bj-filtros-acciones { min-width: 150px; }
.bj-toggle {
  display: flex; align-items: center; gap: .5rem;
  margin-top: .85rem; padding-top: .85rem;
  border-top: 1px dashed var(--gray-100);
  font-size: .82rem; color: var(--gray-600); cursor: pointer;
}
.bj-toggle input { width: 16px; height: 16px; accent-color: var(--navy); cursor: pointer; }

@media (max-width: 900px) {
  .bj-filtros-grid { grid-template-columns: 1fr 1fr; }
  .bj-filtros-acciones { grid-column: 1 / -1; }
}
@media (max-width: 480px) {
  .bj-filtros-grid { grid-template-columns: 1fr; }
}

/* Resultados */
.bj-resultados-head {
  display: flex; align-items: baseline; gap: .85rem;
  margin-bottom: .85rem;
}
.bj-h2 {
  font-family: var(--font-head);
  font-size: 1rem;
  color: var(--navy);
  text-transform: uppercase;
  letter-spacing: .04em;
  margin: 0;
}
.bj-sub {
  font-size: .75rem;
  color: var(--gray-500);
  font-weight: 500;
  text-transform: none;
  letter-spacing: 0;
}
.bj-empty {
  background: #fff;
  border: 1.5px dashed var(--gray-200);
  border-radius: 16px;
  padding: 3rem 1rem;
  text-align: center;
}

/* Grid de cards */
.bj-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
}
.bj-card {
  background: #fff;
  border: 1px solid var(--gray-100);
  border-radius: 16px;
  padding: 1.1rem 1.2rem 1rem;
  display: flex;
  flex-direction: column;
  gap: .8rem;
  transition: box-shadow .2s, border-color .2s, transform .2s;
}
.bj-card:hover {
  box-shadow: 0 8px 24px rgba(28,47,72,.08);
  border-color: var(--gray-200);
  transform: translateY(-2px);
}
.bj-card-head {
  display: flex; align-items: center; gap: .75rem;
}
.bj-avatar {
  width: 48px; height: 48px; border-radius: 50%;
  background: linear-gradient(135deg, var(--navy), #1a3a64);
  color: var(--gold); font-weight: 800; font-size: 1rem;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; text-decoration: none;
  border: 2px solid var(--gold); overflow: hidden;
}
.bj-avatar img { width: 100%; height: 100%; object-fit: cover; }
.bj-nombre {
  font-family: var(--font-head);
  font-weight: 800; color: var(--navy);
  font-size: .98rem; line-height: 1.2;
  text-decoration: none;
  display: block;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.bj-nombre:hover { color: var(--gold); }
.bj-alias {
  font-size: .72rem; color: var(--gray-400); font-style: italic;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.bj-chips {
  display: flex; flex-wrap: wrap; gap: .35rem;
}
.bj-chip {
  background: #f1f5f9;
  color: var(--gray-600);
  font-size: .72rem; font-weight: 600;
  padding: .25rem .55rem;
  border-radius: 999px;
}
.bj-chip-cat {
  background: var(--navy); color: var(--gold);
  font-weight: 800;
}

/* Acciones */
.bj-actions {
  display: flex; gap: .45rem; margin-top: auto;
}
.bj-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
  border: none; border-radius: 10px;
  padding: .55rem .85rem;
  font-weight: 700; font-size: .78rem;
  cursor: pointer; transition: all .15s;
  text-decoration: none;
  flex: 1;
}
.bj-btn-wsp {
  background: #25D366; color: #fff;
  box-shadow: 0 3px 10px rgba(37,211,102,.25);
}
.bj-btn-wsp:hover { background: #20ba5a; transform: translateY(-1px); box-shadow: 0 5px 14px rgba(37,211,102,.4); }
.bj-btn-msg {
  background: var(--navy); color: var(--gold);
}
.bj-btn-msg:hover { background: #1a3a64; }
.bj-no-tel {
  flex: 1; font-size: .72rem; color: var(--gray-400);
  font-style: italic; padding: .55rem; text-align: center;
}

/* Modal */
.bj-modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,.55);
  backdrop-filter: blur(4px);
  z-index: 9998;
  display: flex; align-items: center; justify-content: center;
  padding: 1rem;
  animation: bj-fade-in .2s ease both;
}
@keyframes bj-fade-in { from { opacity: 0; } to { opacity: 1; } }
.bj-modal-content {
  background: #fff;
  border-radius: 20px;
  max-width: 500px; width: 100%;
  max-height: 90vh; overflow: hidden;
  display: flex; flex-direction: column;
  box-shadow: 0 25px 60px rgba(15,23,42,.3);
}
.bj-modal-head {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--gray-100);
  display: flex; justify-content: space-between; align-items: center; gap: .5rem;
}
.bj-modal-avatar {
  width: 42px; height: 42px; border-radius: 50%;
  background: linear-gradient(135deg, var(--navy), #1a3a64);
  color: var(--gold); font-weight: 800; font-size: .85rem;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid var(--gold); flex-shrink: 0;
}
.bj-modal-close {
  background: transparent; border: none;
  font-size: 1.8rem; color: var(--gray-400);
  cursor: pointer; line-height: 1;
  width: 32px; height: 32px; border-radius: 50%;
}
.bj-modal-close:hover { background: var(--gray-100); color: var(--navy); }
.bj-plantillas {
  display: flex; flex-wrap: wrap; gap: .35rem;
}
.bj-plantilla {
  background: var(--gray-100); border: 1px solid var(--gray-200);
  border-radius: 8px; padding: .4rem .75rem;
  font-size: .75rem; font-weight: 600;
  color: var(--gray-700); cursor: pointer;
  transition: all .15s;
}
.bj-plantilla:hover {
  background: var(--navy); color: var(--gold); border-color: var(--navy);
}
.bj-modal-actions {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--gray-100);
  display: flex; gap: .65rem;
}
.bj-btn-cancel {
  flex: 1; background: var(--gray-100); color: var(--gray-600);
  border: none; border-radius: 10px;
  padding: .65rem 1rem; font-weight: 700; font-size: .82rem;
  cursor: pointer; transition: background .15s;
}
.bj-btn-cancel:hover { background: var(--gray-200); }
</style>

<script>
let bjJugadorActual = null;

function bjAbrirMensaje(j) {
  bjJugadorActual = j;
  document.getElementById('bjModalAvatar').textContent = j.iniciales;
  document.getElementById('bjModalNombre').textContent = j.nombre;
  const ta = document.getElementById('bjMensajeTexto');
  ta.value = 'Hola ' + j.nombre.split(' ')[0] + ', te vi en la Elite Padel League. ¿Tenés ganas de coordinar un partido?';
  bjActualizarContador();
  bjActualizarLinkWsp();
  document.getElementById('bjModalMsg').style.display = 'flex';
  document.body.style.overflow = 'hidden';
  setTimeout(() => ta.focus(), 50);
}

function bjCerrarMensaje() {
  document.getElementById('bjModalMsg').style.display = 'none';
  document.body.style.overflow = '';
  bjJugadorActual = null;
}

function bjActualizarContador() {
  const ta = document.getElementById('bjMensajeTexto');
  document.getElementById('bjCharCount').textContent = ta.value.length;
}

function bjActualizarLinkWsp() {
  if (!bjJugadorActual) return;
  const texto = document.getElementById('bjMensajeTexto').value;
  const link = document.getElementById('bjBtnWsp');
  if (!bjJugadorActual.wsp || !texto.trim()) {
    link.style.opacity = '.5';
    link.style.pointerEvents = 'none';
    link.href = '#';
    return;
  }
  link.style.opacity = '1';
  link.style.pointerEvents = '';
  link.href = 'https://wa.me/' + bjJugadorActual.wsp + '?text=' + encodeURIComponent(texto);
}

document.getElementById('bjMensajeTexto')?.addEventListener('input', () => {
  bjActualizarContador();
  bjActualizarLinkWsp();
});

// Plantillas
document.querySelectorAll('.bj-plantilla').forEach(btn => {
  btn.addEventListener('click', () => {
    if (!bjJugadorActual) return;
    const tpl = btn.dataset.tpl;
    const nombre1 = bjJugadorActual.nombre.split(' ')[0];
    document.getElementById('bjMensajeTexto').value = tpl.replace(/\{nombre\}/g, nombre1);
    bjActualizarContador();
    bjActualizarLinkWsp();
  });
});

// ESC cierra modal
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') bjCerrarMensaje();
});
</script>

<?php require_once 'includes/footer.php'; ?>
