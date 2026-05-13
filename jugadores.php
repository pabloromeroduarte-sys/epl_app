<?php
$page_title = 'Jugadores';
$active_nav = 'jugadores';
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

<section class="section-sm" style="background:var(--navy)">
  <div class="container">
    <p class="section-eyebrow" style="color:var(--gold)">Temporada 2026</p>
    <h1 class="section-title" style="color:var(--white)">Jugadores</h1>
  </div>
</section>

<section class="section">
  <div class="container">

    <!-- Búsqueda -->
    <div class="mb-4" style="max-width:380px">
      <input type="text" id="buscarJugador" class="form-control" placeholder="Buscar jugador...">
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
  document.querySelectorAll('#jugadoresGrid a[data-nombre]').forEach(c => {
    c.style.display = c.dataset.nombre.includes(q) ? '' : 'none';
  });
});
</script>

<?php require_once 'includes/footer.php'; ?>
