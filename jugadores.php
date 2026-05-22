<?php
$page_title       = 'Jugadores';
$active_nav       = 'jugadores';
$meta_description = 'Comunidad de jugadores Elite Padel League: el directorio completo de la liga de pádel más competitiva de Santiago. Conocé a tus próximos rivales.';
$meta_keywords    = 'jugadores padel santiago, comunidad padel chile, ranking jugadores EPL, perfiles padel';
require_once 'includes/functions.php';

$db = epl_db();
$jugadores = $db->query("
    SELECT j.*,
           e.nombre AS equipo_nombre
    FROM jugadores j
    LEFT JOIN equipos e ON (e.jugador1_id = j.id OR e.jugador2_id = j.id)
    WHERE j.estado = 'activo'
    ORDER BY j.apellido, j.nombre
")->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>

<!-- HERO premium -->
<section class="epl-hero epl-hero-sm" style="background-image: linear-gradient(135deg, rgba(28,47,72,.94) 0%, rgba(10,20,33,.92) 100%), url('<?= epl_url('assets/img/landing/tercer-tiempo-1.jpg') ?>')">
  <div class="epl-container">
    <span class="epl-eyebrow">Temporada 2026 · <?= count($jugadores) ?> jugadores activos</span>
    <h1>La <span class="epl-hero-gold">Comunidad</span></h1>
    <p>Conocé a los jugadores del circuito EPL. Tocá cualquier perfil para ver su historial, estadísticas y próximos partidos.</p>
  </div>
</section>

<section class="epl-section" style="padding-top:2rem">
  <div class="epl-container">

    <!-- Búsqueda con look premium -->
    <div style="position:relative;max-width:480px;margin-bottom:2rem">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);pointer-events:none"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      <input type="text" id="buscarJugador"
             style="width:100%;padding:.85rem 1rem .85rem 2.85rem;border:1.5px solid #e2e8f0;border-radius:12px;font-size:.92rem;font-family:'Montserrat',sans-serif;background:#fff;transition:border-color .2s"
             onfocus="this.style.borderColor='var(--gold)'"
             onblur="this.style.borderColor='#e2e8f0'"
             placeholder="Buscar jugador por nombre o alias...">
      <span id="busquedaResultados" style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);font-size:.72rem;color:#94a3b8;font-weight:700"></span>
    </div>

    <div class="jugadores-grid" id="jugadoresGrid">
      <?php foreach ($jugadores as $j): ?>
      <a href="jugador.php?id=<?= $j['id'] ?>" class="jugador-card" data-nombre="<?= strtolower(epl_h($j['nombre'].' '.$j['apellido'])) ?>" style="display:block;text-decoration:none">
        <img class="jugador-foto"
             src="<?= epl_h(epl_foto_jugador($j['foto'], $j['nombre'].' '.$j['apellido'])) ?>"
             alt="<?= epl_h($j['nombre'].' '.$j['apellido']) ?>"
             loading="lazy">
        <div class="jugador-info">
          <div class="jugador-nombre"><?= epl_h($j['nombre'].' '.$j['apellido']) ?></div>
          <?php if ($j['alias']): ?>
            <div class="jugador-alias">"<?= epl_h($j['alias']) ?>"</div>
          <?php endif; ?>
          <?php if ($j['equipo_nombre']): ?>
            <div style="font-size:.72rem;color:var(--gray-400);margin-top:.35rem"><?= epl_h($j['equipo_nombre']) ?></div>
          <?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<script>
document.getElementById('buscarJugador').addEventListener('input', function() {
  const q = this.value.toLowerCase().trim();
  let visibles = 0;
  document.querySelectorAll('#jugadoresGrid a[data-nombre]').forEach(c => {
    const show = c.dataset.nombre.includes(q);
    c.style.display = show ? '' : 'none';
    if (show) visibles++;
  });
  document.getElementById('busquedaResultados').textContent = q ? visibles + ' resultados' : '';
});
</script>

<?php require_once 'includes/footer.php'; ?>
