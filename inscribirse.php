<?php
$page_title = 'Inscribirse a la Liga';
$player_tab = 'inscribirse';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
epl_require_login();

$jugador = epl_jugador_actual();
$db      = epl_db();

// Ligas en inscripción o activas
$ligas = $db->query("SELECT * FROM ligas WHERE estado IN ('inscripcion','activa') ORDER BY id DESC")->fetchAll();

$ok    = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $liga_id    = (int)($_POST['liga_id']    ?? 0);
    $partner_id = (int)($_POST['partner_id'] ?? 0);

    if (!$liga_id) {
        $error = 'Selecciona una liga.';
    } elseif ($partner_id && $partner_id === $jugador['id']) {
        $error = 'No puedes seleccionarte a ti mismo como compañero.';
    } else {
        // Verificar que no esté ya inscrito en esta liga
        $stCheck = $db->prepare("
            SELECT i.id FROM inscripciones i
            WHERE i.jugador_id=? AND i.liga_id=? AND i.estado != 'rechazada'
        ");
        $stCheck->execute([$jugador['id'], $liga_id]);
        if ($stCheck->fetch()) {
            $error = 'Ya tienes una inscripción activa en esta liga.';
        } else {
            $equipo_id = null;

            // Si eligió compañero, crear/buscar equipo
            if ($partner_id) {
                // Buscar si ya existe ese equipo
                $stEq = $db->prepare("
                    SELECT id FROM equipos
                    WHERE (jugador1_id=? AND jugador2_id=?) OR (jugador1_id=? AND jugador2_id=?)
                    LIMIT 1
                ");
                $stEq->execute([$jugador['id'], $partner_id, $partner_id, $jugador['id']]);
                $eq = $stEq->fetchColumn();
                if ($eq) {
                    $equipo_id = $eq;
                } else {
                    // Crear nuevo equipo: nombre = "Apellido / Apellido"
                    $stP = $db->prepare("SELECT nombre, apellido FROM jugadores WHERE id=?");
                    $stP->execute([$partner_id]);
                    $partner = $stP->fetch();
                    $nombre_equipo = $jugador['apellido'].' / '.($partner['apellido'] ?? 'Compañero');
                    $db->prepare("INSERT INTO equipos (nombre, jugador1_id, jugador2_id) VALUES (?,?,?)")
                       ->execute([$nombre_equipo, $jugador['id'], $partner_id]);
                    $equipo_id = (int)$db->lastInsertId();
                }
            }

            // Registrar inscripción
            $db->prepare("INSERT INTO inscripciones (jugador_id, liga_id, equipo_id) VALUES (?,?,?)")
               ->execute([$jugador['id'], $liga_id, $equipo_id]);

            $ok = 'Inscripción enviada. El administrador la revisará y te confirmará.';
        }
    }
}

// Otros jugadores disponibles como compañeros
$otros_jugadores = $db->query("
    SELECT id, nombre, apellido, alias FROM jugadores
    WHERE estado='activo' AND id != {$jugador['id']}
    ORDER BY apellido, nombre
")->fetchAll();

// Mis inscripciones
$mis_inscripciones = $db->prepare("
    SELECT i.*, l.nombre AS liga_nombre, l.temporada, e.nombre AS equipo_nombre
    FROM inscripciones i
    JOIN ligas l ON l.id = i.liga_id
    LEFT JOIN equipos e ON e.id = i.equipo_id
    WHERE i.jugador_id = ?
    ORDER BY i.fecha DESC
");
$mis_inscripciones->execute([$jugador['id']]);
$mis_inscripciones = $mis_inscripciones->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>


<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">Inscribirse a la Liga</h1>
      <p style="color:var(--gray-600);margin-top:.25rem">Solicita tu inscripción y el administrador la confirmará.</p>
    </div>

    <?php if ($ok): ?>
      <div class="alert alert-success"><?= epl_h($ok) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= epl_h($error) ?></div>
    <?php endif; ?>

    <?php if (empty($ligas)): ?>
      <div class="alert alert-info">No hay ligas abiertas para inscripción en este momento.</div>
    <?php else: ?>
    <div class="card mb-4">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Nueva inscripción</h3>
      </div>
      <div class="card-body">
        <form method="post">
          <div class="form-group">
            <label class="form-label">Liga *</label>
            <select name="liga_id" class="form-control" required>
              <option value="">— Selecciona una liga —</option>
              <?php foreach ($ligas as $l): ?>
                <option value="<?= $l['id'] ?>"><?= epl_h($l['nombre']) ?> <?= $l['temporada']?'('.$l['temporada'].')':'' ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Compañero de equipo <span style="font-weight:400;text-transform:none;font-size:.75rem;color:var(--gray-400)">(opcional, puedes completarlo después)</span></label>
            <select name="partner_id" class="form-control">
              <option value="">— Sin compañero definido aún —</option>
              <?php foreach ($otros_jugadores as $oj): ?>
                <option value="<?= $oj['id'] ?>"><?= epl_h($oj['nombre'].' '.$oj['apellido'].($oj['alias']?' "'.$oj['alias'].'"':'')) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="form-hint">Si tu compañero ya tiene cuenta, lo puedes seleccionar aquí.</span>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
            Enviar solicitud de inscripción
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Mis inscripciones previas -->
    <?php if ($mis_inscripciones): ?>
    <div class="card">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Mis inscripciones</h3>
      </div>
      <div class="card-body" style="padding:0">
        <?php foreach ($mis_inscripciones as $insc):
          $estBadge = match($insc['estado']) {
            'aprobada'  => 'badge-jugado',
            'rechazada' => 'badge-walkover',
            default     => 'badge-pendiente'
          };
          $pagoColor = $insc['pago_estado'] === 'pagado' ? '#22c55e' : ($insc['pago_estado'] === 'exento' ? 'var(--gold)' : '#ef4444');
        ?>
        <div style="padding:.9rem 1.5rem;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
          <div>
            <div style="font-weight:700;font-size:.9rem;color:var(--navy)"><?= epl_h($insc['liga_nombre']) ?></div>
            <div style="font-size:.75rem;color:var(--gray-400)">
              <?= $insc['temporada'] ? epl_h($insc['temporada']).' · ' : '' ?>
              <?= $insc['equipo_nombre'] ? epl_h($insc['equipo_nombre']) : 'Sin equipo asignado' ?>
            </div>
            <div style="font-size:.72rem;color:var(--gray-400);margin-top:.2rem">
              <?= date('d/m/Y', strtotime($insc['fecha'])) ?>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.4rem">
            <span class="badge <?= $estBadge ?>"><?= ucfirst($insc['estado']) ?></span>
            <span style="font-size:.7rem;font-weight:700;color:<?= $pagoColor ?>">
              Pago: <?= ucfirst($insc['pago_estado']) ?>
            </span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

</main>
</div>

<?php require_once 'includes/footer.php'; ?>
