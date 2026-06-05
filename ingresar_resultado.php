<?php
$page_title = 'Ingresar Resultado';
$player_tab = 'resultado';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/mail.php';
epl_require_login();

$jugador = epl_jugador_actual();
$db      = epl_db();
$liga    = epl_liga_activa();
$equipo  = $liga ? epl_equipo_del_jugador($jugador['id'], $liga['id']) : null;

$_flash = epl_flash_get();
$ok     = ($_flash && $_flash['tipo']==='ok');
$error  = ($_flash && $_flash['tipo']==='error') ? $_flash['msg'] : '';

$partidos_pendientes = [];
$requested_partido_id = isset($_GET['partido_id']) ? (int)$_GET['partido_id'] : 0;
$has_requested_in_list = false;

if ($equipo) {
    $st = $db->prepare("
        SELECT p.*,
               el.nombre AS local_nombre,
               ev.nombre AS visitante_nombre,
               CASE WHEN p.fecha_programada IS NOT NULL AND p.fecha_programada < NOW() AND DATE(p.fecha_programada) != '2026-12-31' THEN 1 ELSE 0 END AS vencido
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        WHERE p.liga_id = ?
          AND (p.equipo_local_id = ? OR p.equipo_visitante_id = ?)
          AND p.estado IN ('pendiente', 'reprogramado')
        ORDER BY
            vencido DESC,
            p.fecha_programada ASC,
            p.id ASC
    ");
    $st->execute([$liga['id'], $equipo['id'], $equipo['id']]);
    $partidos_pendientes = $st->fetchAll();

    if ($requested_partido_id > 0) {
        foreach ($partidos_pendientes as $p) {
            if ((int)$p['id'] === $requested_partido_id) {
                $has_requested_in_list = true;
                break;
            }
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && $equipo) {
    $partido_id   = (int)($_POST['partido_id'] ?? 0);

    epl_ensure_disputas_schema();
    $stP = $db->prepare("
        SELECT p.*,
               el.nombre AS local_nombre,
               ev.nombre AS visitante_nombre
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        WHERE p.id=? 
          AND (p.equipo_local_id=? OR p.equipo_visitante_id=?) 
          AND p.estado IN ('pendiente','reprogramado')
    ");
    $stP->execute([$partido_id, $equipo['id'], $equipo['id']]);
    $partido = $stP->fetch();

    if (!$partido) {
        $error = 'Partido no válido.';
    } else {
        // Validar si tiene partidos vencidos
        $stV = $db->prepare("
            SELECT COUNT(*) 
            FROM partidos 
            WHERE liga_id = ? 
              AND (equipo_local_id = ? OR equipo_visitante_id = ?)
              AND estado IN ('pendiente', 'reprogramado')
              AND fecha_programada IS NOT NULL 
              AND fecha_programada < NOW() 
              AND DATE(fecha_programada) != '2026-12-31'
        ");
        $stV->execute([$liga['id'], $equipo['id'], $equipo['id']]);
        $num_vencidos = (int)$stV->fetchColumn();

        // Verificar si el partido seleccionado es vencido
        $es_vencido = false;
        if ($partido['fecha_programada']) {
            $fecha_prog = strtotime($partido['fecha_programada']);
            $es_vencido = ($fecha_prog < time() && date('Y-m-d', $fecha_prog) !== '2026-12-31');
        }

        if ($num_vencidos > 0 && !$es_vencido) {
            $error = 'Debes registrar primero los resultados de tus partidos vencidos/atrasados.';
        } else {
            $sets = [];
            for ($s = 1; $s <= 3; $s++) {
                $gl = isset($_POST["s{$s}_local"])     ? (int)$_POST["s{$s}_local"]     : null;
                $gv = isset($_POST["s{$s}_visitante"]) ? (int)$_POST["s{$s}_visitante"] : null;
                if ($gl !== null && $gv !== null && ($gl > 0 || $gv > 0)) {
                    $sets[] = ['local' => $gl, 'visitante' => $gv];
                }
            }
            if (empty($sets)) {
                $error = 'Debes ingresar al menos un set.';
            } else {
                $sets_local = 0; $sets_vis = 0;
                foreach ($sets as $sv) {
                    if ($sv['local'] > $sv['visitante']) $sets_local++;
                    else $sets_vis++;
                }
                $ganador_id = $sets_local > $sets_vis ? $partido['equipo_local_id'] : $partido['equipo_visitante_id'];

                $ahora = date('Y-m-d H:i:s');
                $fecha_jugado = $partido['fecha_programada'] ?: $ahora;
                $db->prepare("
                    UPDATE partidos SET
                      estado='jugado', fecha_jugado=?,
                      sets_local=?, sets_visitante=?,
                      games_s1_local=?, games_s1_visitante=?,
                      games_s2_local=?, games_s2_visitante=?,
                      games_s3_local=?, games_s3_visitante=?,
                      ganador_id=?, ingresado_por=?,
                      resultado_ingresado_at=?
                    WHERE id=?
                ")->execute([
                    $fecha_jugado,
                    $sets_local, $sets_vis,
                    $sets[0]['local'] ?? null, $sets[0]['visitante'] ?? null,
                    $sets[1]['local'] ?? null, $sets[1]['visitante'] ?? null,
                    $sets[2]['local'] ?? null, $sets[2]['visitante'] ?? null,
                    $ganador_id, $jugador['id'], $ahora, $partido_id
                ]);

                epl_recalcular_clasificacion($liga['id']);

                // Notificar al equipo rival
                $rival_id = $partido['equipo_local_id'] == $equipo['id']
                    ? $partido['equipo_visitante_id']
                    : $partido['equipo_local_id'];
                $rival_nombre = $partido['equipo_local_id'] == $equipo['id']
                    ? $partido['visitante_nombre']
                    : $partido['local_nombre'];
                $mi_nombre = $equipo['nombre'];
                $resultado = "{$sets_local}-{$sets_vis}";
                $gano = $ganador_id == $equipo['id'];

                // Notificar a jugadores del equipo rival
                $asunto_res = epl_mail_asunto(
                    '⚽ Resultado ingresado',
                    $partido['local_nombre'],
                    $partido['visitante_nombre'],
                    $partido['jornada'] ?? null
                );
                $ganador_nombre  = $gano ? $mi_nombre : $rival_nombre;
                $resultado_sets  = implode(' / ', array_map(fn($s) => "{$s['local']}-{$s['visitante']}", $sets));
                $url_reclamar    = epl_url("reclamar_resultado.php?partido_id={$partido_id}");
                
                $nombre_quien_ingresa = trim(($jugador['nombre'] ?? '') . ' ' . ($jugador['apellido'] ?? ''));
                $texto_rival_subtitulo = "El jugador {$nombre_quien_ingresa} ingresó el resultado de tu partido {$partido['local_nombre']} vs {$partido['visitante_nombre']} (Jornada " . ($partido['jornada'] ?? '—') . ").";
                $texto_rival_tip = "⚠️ En caso de tener algún problema con el resultado contáctate con los organizadores (tienes 24 horas para reclamar).";

                $re_st = $db->prepare("SELECT jugador1_id, jugador2_id FROM equipos WHERE id = ?");
                $re_st->execute([$rival_id]);
                $re = $re_st->fetch(PDO::FETCH_ASSOC);
                $rivales_ids = array_values(array_filter([
                    (int)($re['jugador1_id'] ?? 0),
                    (int)($re['jugador2_id'] ?? 0),
                ]));
                foreach ($rivales_ids as $rival_jugador_id) {
                    epl_notif_crear(
                        $rival_jugador_id,
                        'resultado',
                        $asunto_res,
                        $texto_rival_subtitulo . ' ' . $texto_rival_tip,
                        $url_reclamar,
                        true // skip_email: enviamos visual por separado
                    );
                    epl_mail_partido_visual(
                        $rival_jugador_id,
                        $asunto_res,
                        $partido['local_nombre'],
                        $partido['visitante_nombre'],
                        [
                            ['icon' => '🏆', 'label' => 'Ganador',   'valor' => $ganador_nombre],
                            ['icon' => '🎾', 'label' => 'Resultado', 'valor' => $resultado_sets],
                        ],
                        $texto_rival_subtitulo,
                        $texto_rival_tip,
                        $url_reclamar,
                        '⚠️ Reclamar Resultado'
                    );
                }

                // Notificar a los administradores
                $admins_st = $db->query("SELECT id FROM jugadores WHERE rol = 'admin'");
                $admins_ids = $admins_st->fetchAll(PDO::FETCH_COLUMN);
                $fecha_hora_fmt = date('d/m/Y H:i', strtotime($ahora));
                
                $texto_admin_subtitulo = "El jugador {$nombre_quien_ingresa} registró el resultado {$resultado_sets} del partido {$partido['local_nombre']} vs {$partido['visitante_nombre']} de la jornada " . ($partido['jornada'] ?? '—') . ".";
                $texto_admin_tip = "Fecha y hora de registro: {$fecha_hora_fmt}";
                $url_admin = epl_url("admin/partido_detalle.php?id={$partido_id}");

                foreach ($admins_ids as $admin_id) {
                    epl_notif_crear(
                        (int)$admin_id,
                        'resultado',
                        $asunto_res,
                        $texto_admin_subtitulo . ' ' . $texto_admin_tip,
                        $url_admin,
                        true // skip_email
                    );
                    epl_mail_partido_visual(
                        (int)$admin_id,
                        $asunto_res,
                        $partido['local_nombre'],
                        $partido['visitante_nombre'],
                        [
                            ['icon' => '🏆', 'label' => 'Ganador',   'valor' => $ganador_nombre],
                            ['icon' => '🎾', 'label' => 'Resultado', 'valor' => $resultado_sets],
                        ],
                        $texto_admin_subtitulo,
                        $texto_admin_tip,
                        $url_admin,
                        'Ver en panel admin'
                    );
                }

                epl_redirect_ok('resultado_ok');
            }
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main">

  <div class="dash-header">
    <h1 class="dash-title">Ingresar Resultado</h1>
    <p style="color:var(--gray-400);font-size:.88rem">Registra el marcador de tu partido.</p>
  </div>

  <?php if ($ok): ?>
  <div class="ir-success">
    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <div>
      <strong>¡Resultado registrado!</strong>
      <p style="margin:.2rem 0 0;font-size:.85rem;opacity:.85">La clasificación ha sido actualizada automáticamente.</p>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alert alert-error"><?= epl_h($error) ?></div>
  <?php endif; ?>

  <?php if (!$equipo): ?>
    <div class="alert alert-info">No estás inscrito en ningún equipo de la liga activa.</div>

  <?php elseif (empty($partidos_pendientes)): ?>
  <div class="ir-empty">
    <div class="ir-empty-icon">🎾</div>
    <h3>Sin partidos pendientes</h3>
    <p>Todos tus partidos tienen resultado registrado.</p>
    <a href="dashboard.php" class="btn btn-navy" style="margin-top:1rem">Volver al Dashboard</a>
  </div>

  <?php else: ?>
  <?php
    $tiene_vencidos = false;
    foreach ($partidos_pendientes as $p) {
        if (!empty($p['vencido'])) {
            $tiene_vencidos = true;
            break;
        }
    }
  ?>

  <!-- Selector de partido -->
  <div class="ir-selector-card">
    <p class="ir-step-label">Paso 1 — Selecciona el partido</p>
    <?php if ($tiene_vencidos): ?>
      <div style="background:#fee2e2;border-left:4px solid #dc2626;padding:.75rem 1rem;border-radius:8px;font-size:.85rem;color:#991b1b;margin-top:.75rem;font-weight:700">
        ⚠️ Tienes partidos vencidos pendientes de registrar. Debes ingresar sus resultados para poder registrar otros partidos.
      </div>
    <?php endif; ?>
    <div class="ir-partidos-list" id="listaPartidos">
      <?php foreach ($partidos_pendientes as $i => $p):
        $vencido = !empty($p['vencido']);
        $reprog  = ($p['estado'] === 'reprogramado');
        $disabled = ($tiene_vencidos && !$vencido);
        $selected = $has_requested_in_list 
            ? ((int)$p['id'] === $requested_partido_id) 
            : ($i === 0);
        if ($disabled) {
            $selected = false;
        }
      ?>
      <label class="ir-partido-option<?= $vencido ? ' ir-vencido' : '' ?><?= $disabled ? ' ir-disabled' : '' ?>" for="p<?= $p['id'] ?>">
        <input type="radio" name="_partido_pick" id="p<?= $p['id'] ?>" value="<?= $p['id'] ?>"
               data-local="<?= epl_h($p['local_nombre']) ?>"
               data-visitante="<?= epl_h($p['visitante_nombre']) ?>"
               data-fecha="<?= epl_h($p['fecha_programada'] ?? '') ?>"
               <?= $selected ? 'checked' : '' ?>
               <?= $disabled ? 'disabled' : '' ?>
               onchange="seleccionarPartido(this)">
        <div class="ir-partido-content">
          <div class="ir-partido-fecha<?= $vencido ? ' ir-fecha-vencida' : '' ?>">
            <?php if ($p['fecha_programada']): ?>
              <span class="ir-dia"><?= date('d', strtotime($p['fecha_programada'])) ?></span>
              <span class="ir-mes"><?= strtoupper(date('M', strtotime($p['fecha_programada']))) ?></span>
            <?php else: ?>
              <span class="ir-dia">—</span><span class="ir-mes">TBD</span>
            <?php endif; ?>
          </div>
          <div class="ir-partido-teams">
            <span><?= epl_h($p['local_nombre']) ?></span>
            <span class="ir-vs">VS</span>
            <span><?= epl_h($p['visitante_nombre']) ?></span>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.25rem;flex-shrink:0">
            <div class="ir-partido-jornada">F.<?= $p['jornada'] ?? '—' ?></div>
            <?php if ($vencido): ?>
              <span style="font-size:.55rem;font-weight:800;color:#dc2626;background:#fee2e2;border-radius:4px;padding:1px 5px;text-transform:uppercase;letter-spacing:.04em">Vencido</span>
            <?php elseif ($disabled): ?>
              <span style="font-size:.55rem;font-weight:800;color:#64748b;background:#e2e8f0;border-radius:4px;padding:1px 5px;text-transform:uppercase;letter-spacing:.04em">Bloqueado</span>
            <?php elseif ($reprog): ?>
              <span style="font-size:.55rem;font-weight:800;color:#d97706;background:#fef3c7;border-radius:4px;padding:1px 5px;text-transform:uppercase;letter-spacing:.04em">Reprog.</span>
            <?php endif; ?>
            <?php if (!$tiene_vencidos && $i === 0): ?>
              <span style="font-size:.55rem;font-weight:800;color:#16a34a;background:#dcfce7;border-radius:4px;padding:1px 5px;text-transform:uppercase;letter-spacing:.04em">⭐ Próximo</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="ir-partido-check">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
      </label>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Formulario de marcador -->
  <form method="post" id="formResultado">
    <input type="hidden" name="partido_id" id="hiddenPartidoId">

    <div id="seccionSets" style="display:none">

      <!-- Teams display -->
      <div class="ir-match-header">
        <div class="ir-team-name" id="nombreLocal">—</div>
        <div class="ir-match-badge">RESULTADO</div>
        <div class="ir-team-name ir-team-right" id="nombreVisitante">—</div>
      </div>

      <!-- Sets -->
      <div class="ir-sets-container">
        <p class="ir-step-label" style="margin-bottom:1.25rem">Paso 2 — Ingresa el marcador por set</p>

        <?php for ($s = 1; $s <= 3; $s++): ?>
        <div class="ir-set-block <?= $s===3?'ir-set-optional':'' ?>">
          <div class="ir-set-row">
            <div class="ir-set-label">
              <span class="ir-set-num">Set <?= $s ?></span>
              <?php if ($s===3): ?><span class="ir-set-opt-tag">opcional</span><?php endif; ?>
            </div>
            <div class="ir-score-group">
              <input type="number" name="s<?= $s ?>_local" id="s<?= $s ?>_local" class="ir-score-input"
                     min="0" max="7" placeholder="0" inputmode="numeric" <?= $s===3?'':'required' ?>
                     oninput="clearChip(<?= $s ?>)">
              <span class="ir-score-dash">—</span>
              <input type="number" name="s<?= $s ?>_visitante" id="s<?= $s ?>_visitante" class="ir-score-input"
                     min="0" max="7" placeholder="0" inputmode="numeric" <?= $s===3?'':'required' ?>
                     oninput="clearChip(<?= $s ?>)">
            </div>
          </div>
          <!-- Chips de marcadores rápidos -->
          <div class="ir-chips-row" id="chips_s<?= $s ?>">
            <span class="ir-chips-lbl" id="chipLbl<?= $s ?>_l">Local</span>
            <?php foreach ([[6,0],[6,1],[6,2],[6,3],[6,4],[7,5],[7,6]] as [$a,$b]): ?>
            <button type="button" class="ir-chip" onclick="setChip(<?= $s ?>,<?= $a ?>,<?= $b ?>,this)"><?= $a ?>-<?= $b ?></button>
            <?php endforeach; ?>
            <span class="ir-chips-sep">|</span>
            <span class="ir-chips-lbl" id="chipLbl<?= $s ?>_v">Visit.</span>
            <?php foreach ([[0,6],[1,6],[2,6],[3,6],[4,6],[5,7],[6,7]] as [$a,$b]): ?>
            <button type="button" class="ir-chip" onclick="setChip(<?= $s ?>,<?= $a ?>,<?= $b ?>,this)"><?= $a ?>-<?= $b ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endfor; ?>
      </div>



      <button type="submit" class="ir-submit-btn">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Confirmar resultado
      </button>

    </div>
  </form>

  <?php endif; ?>
</main>
</div>

<style>
@keyframes ir-fade-up {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes ir-pop {
  0%   { transform: scale(.94); opacity: 0; }
  60%  { transform: scale(1.02); }
  100% { transform: scale(1); opacity: 1; }
}

.ir-success {
  display: flex; align-items: flex-start; gap: 1rem;
  background: linear-gradient(135deg, #f0fdf4, #dcfce7);
  border: 1px solid #86efac; border-radius: 14px;
  padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; color: #166534;
  animation: ir-fade-up .35s ease both;
  box-shadow: 0 4px 16px rgba(34,197,94,.12);
}
.ir-success svg { flex-shrink: 0; margin-top: .1rem; }

.ir-empty { text-align: center; padding: 4rem 2rem; animation: ir-fade-up .4s ease both; }
.ir-empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.ir-empty h3 { font-family: var(--font-head); text-transform: uppercase; color: var(--navy); margin: 0 0 .5rem; }
.ir-empty p { color: var(--gray-400); }

.ir-step-label {
  font-size: .62rem; font-weight: 800; text-transform: uppercase; letter-spacing: .12em;
  color: var(--gold); margin: 0;
  display: flex; align-items: center; gap: .5rem;
}
.ir-step-label::before {
  content: ''; display: inline-block; width: 18px; height: 2px;
  background: var(--gold); border-radius: 2px;
}

/* Selector */
.ir-selector-card {
  background: var(--white); border-radius: 20px;
  border: 1px solid var(--gray-100); padding: 1.5rem; margin-bottom: 1.5rem;
  box-shadow: 0 4px 24px rgba(0,0,0,.06);
  animation: ir-fade-up .35s ease both;
}
.ir-partidos-list { display: flex; flex-direction: column; gap: .5rem; margin-top: 1.1rem; }
/* Partido vencido (sin resultado y fecha pasada) */
.ir-vencido { border-color: rgba(220,38,38,.3) !important; background: rgba(220,38,38,.03) !important; }
.ir-vencido:hover { border-color: #dc2626 !important; }
.ir-fecha-vencida { background: linear-gradient(135deg, #fee2e2, #fecaca) !important; }
.ir-fecha-vencida .ir-dia { color: #dc2626 !important; }
.ir-fecha-vencida .ir-mes { color: #ef4444 !important; }
.ir-partido-option.ir-disabled {
  opacity: 0.55;
  cursor: not-allowed;
  pointer-events: none;
  background: #f9fafb !important;
  border-color: #e5e7eb !important;
}
.ir-partido-option.ir-disabled * {
  cursor: not-allowed;
}
.ir-partido-option {
  display: flex; align-items: center; gap: 1rem;
  border: 1.5px solid var(--gray-100); border-radius: 14px;
  padding: .9rem 1rem; cursor: pointer;
  transition: all .22s cubic-bezier(.4,0,.2,1);
  background: var(--white);
}
.ir-partido-option:hover {
  border-color: var(--gold); background: rgba(201,167,98,.04);
  box-shadow: 0 2px 12px rgba(201,167,98,.14); transform: translateX(2px);
}
.ir-partido-option input[type=radio] { display: none; }
.ir-partido-option:has(input:checked) {
  border-color: var(--navy); background: linear-gradient(135deg, rgba(28,47,72,.04), rgba(28,47,72,.07));
  box-shadow: 0 4px 16px rgba(28,47,72,.1);
}
.ir-partido-content { display: flex; align-items: center; gap: 1rem; flex: 1; min-width: 0; }
.ir-partido-fecha {
  background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
  border-radius: 10px; padding: .45rem .65rem; text-align: center; min-width: 44px; flex-shrink: 0;
  transition: all .22s;
}
.ir-dia { display: block; font-family: var(--font-head); font-size: 1.15rem; color: var(--navy); line-height: 1; }
.ir-mes { display: block; font-size: .52rem; font-weight: 800; color: var(--gray-400); letter-spacing: .08em; text-transform: uppercase; }
.ir-partido-option:has(input:checked) .ir-partido-fecha {
  background: linear-gradient(135deg, var(--navy), #1e3a5f);
  box-shadow: 0 4px 12px rgba(28,47,72,.3);
}
.ir-partido-option:has(input:checked) .ir-dia,
.ir-partido-option:has(input:checked) .ir-mes { color: var(--gold); }
.ir-partido-teams { flex: 1; display: flex; align-items: center; gap: .6rem; font-size: .85rem; font-weight: 700; color: var(--navy); min-width: 0; flex-wrap: wrap; }
.ir-vs {
  background: linear-gradient(135deg, var(--navy), #2d5a8e);
  color: var(--gold); border-radius: 6px; padding: .15rem .45rem;
  font-size: .62rem; font-weight: 800; flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(28,47,72,.2);
}
.ir-partido-option:has(input:checked) .ir-vs { background: linear-gradient(135deg, var(--gold), #b8975a); color: var(--navy); }
.ir-partido-jornada { font-size: .65rem; font-weight: 700; color: var(--gray-400); flex-shrink: 0; }
.ir-partido-check {
  width: 28px; height: 28px; border-radius: 50%; border: 2px solid var(--gray-200);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  color: transparent; transition: all .22s cubic-bezier(.4,0,.2,1);
}
.ir-partido-option:has(input:checked) .ir-partido-check {
  background: linear-gradient(135deg, var(--navy), #1e3a5f);
  border-color: var(--navy); color: var(--white);
  box-shadow: 0 3px 10px rgba(28,47,72,.3);
  animation: ir-pop .3s ease both;
}

/* Match header */
.ir-match-header {
  display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 1rem;
  background: linear-gradient(135deg, #0f1f38, var(--navy), #1a3a64);
  border-radius: 20px; padding: 1.75rem 2rem; margin-bottom: 1.5rem;
  box-shadow: 0 8px 32px rgba(28,47,72,.25);
  position: relative; overflow: hidden;
  animation: ir-fade-up .3s ease both;
}
.ir-match-header::before {
  content: ''; position: absolute; top: -40%; right: -10%;
  width: 200px; height: 200px; border-radius: 50%;
  background: rgba(201,167,98,.08); pointer-events: none;
}
.ir-team-name {
  font-family: var(--font-head); font-size: clamp(.8rem, 2vw, 1.15rem);
  text-transform: uppercase; color: var(--white); line-height: 1.2;
  text-shadow: 0 1px 3px rgba(0,0,0,.3);
}
.ir-team-right { text-align: right; }
.ir-match-badge {
  background: linear-gradient(135deg, var(--gold), #b8975a);
  color: var(--navy); font-size: .62rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .12em;
  border-radius: 20px; padding: .45rem 1rem; text-align: center; white-space: nowrap;
  box-shadow: 0 4px 14px rgba(201,167,98,.4);
}

/* Sets */
.ir-sets-container {
  background: var(--white); border-radius: 20px;
  border: 1px solid var(--gray-100); padding: 1.5rem; margin-bottom: 1rem;
  box-shadow: 0 4px 24px rgba(0,0,0,.05);
  animation: ir-fade-up .35s .05s ease both;
}
.ir-set-row {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  padding: 1rem 0; border-bottom: 1px solid var(--gray-100);
  transition: background .18s;
}
.ir-set-row:last-child { border-bottom: none; }
.ir-set-optional { opacity: .65; }
.ir-set-label { display: flex; align-items: center; gap: .5rem; min-width: 80px; }
.ir-set-num {
  font-weight: 800; font-size: .9rem; color: var(--navy);
  background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
  border-radius: 8px; padding: .3rem .65rem;
}
.ir-set-opt-tag {
  font-size: .58rem; font-weight: 700; color: var(--gray-400);
  background: var(--gray-100); border-radius: 4px; padding: .1rem .4rem;
  text-transform: uppercase; letter-spacing: .06em;
}
/* Set block wrapper */
.ir-set-block { border-bottom: 1px solid var(--gray-100); }
.ir-set-block:last-child { border-bottom: none; }
.ir-set-row { border-bottom: none; padding-bottom: .5rem; }

/* Chips */
.ir-chips-row {
  display: flex; align-items: center; gap: .3rem;
  flex-wrap: nowrap; overflow-x: auto; padding: .4rem 0 .75rem;
  scrollbar-width: none; -ms-overflow-style: none;
}
.ir-chips-row::-webkit-scrollbar { display: none; }
.ir-chip {
  flex-shrink: 0; padding: .28rem .6rem;
  font-size: .72rem; font-weight: 700; font-family: var(--font-head);
  background: var(--gray-100); color: var(--gray-500);
  border: 1.5px solid transparent; border-radius: 20px;
  cursor: pointer; transition: all .18s;
  white-space: nowrap;
}
.ir-chip:hover { background: rgba(201,167,98,.15); color: var(--navy); border-color: var(--gold); }
.ir-chip.active {
  background: var(--navy); color: var(--gold);
  border-color: var(--navy);
  box-shadow: 0 2px 8px rgba(28,47,72,.25);
}
.ir-chips-lbl {
  font-size: .58rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: .06em; color: var(--gray-400); flex-shrink: 0; white-space: nowrap;
}
.ir-chips-sep { color: var(--gray-200); font-size: .9rem; flex-shrink: 0; }

.ir-score-group { display: flex; align-items: center; gap: .75rem; }
.ir-score-input {
  width: 68px; height: 68px;
  border: 2px solid var(--gray-200); border-radius: 14px;
  font-family: var(--font-head); font-size: 2rem; color: var(--navy);
  text-align: center; background: linear-gradient(135deg, #fafafa, var(--white));
  transition: border-color .22s, box-shadow .22s, transform .15s;
  -moz-appearance: textfield;
  box-shadow: 0 2px 8px rgba(0,0,0,.05);
}
.ir-score-input::-webkit-outer-spin-button,
.ir-score-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.ir-score-input:focus {
  outline: none; border-color: var(--gold);
  box-shadow: 0 0 0 4px rgba(201,167,98,.18), 0 4px 16px rgba(201,167,98,.15);
  transform: scale(1.04);
}
.ir-score-dash { font-family: var(--font-head); font-size: 1.6rem; color: var(--gray-300); }

.ir-fecha-card {
  background: var(--white); border-radius: 20px;
  border: 1px solid var(--gray-100); padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;
  box-shadow: 0 4px 24px rgba(0,0,0,.05);
  animation: ir-fade-up .35s .1s ease both;
}

.ir-submit-btn {
  width: 100%; display: flex; align-items: center; justify-content: center; gap: .7rem;
  background: linear-gradient(135deg, var(--navy), #1a3a64);
  color: var(--white); border: none; border-radius: 16px; padding: 1.2rem;
  font-family: var(--font-head); font-size: 1rem; text-transform: uppercase; letter-spacing: .08em;
  cursor: pointer; transition: all .25s cubic-bezier(.4,0,.2,1);
  box-shadow: 0 6px 20px rgba(28,47,72,.25);
  animation: ir-fade-up .35s .15s ease both;
}
.ir-submit-btn:hover {
  background: linear-gradient(135deg, var(--gold), #b8975a);
  color: var(--navy); transform: translateY(-2px);
  box-shadow: 0 10px 28px rgba(201,167,98,.4);
}
.ir-submit-btn:active { transform: translateY(0); }

@media(max-width:480px){
  .ir-match-header { padding: 1.1rem; gap: .5rem; }
  .ir-score-input { width: 58px; height: 58px; font-size: 1.6rem; }
  .ir-partido-teams { font-size: .78rem; }
}
</style>

<script>
function seleccionarPartido(radio) {
  document.getElementById('hiddenPartidoId').value = radio.value;
  const local     = radio.dataset.local;
  const visitante = radio.dataset.visitante;
  document.getElementById('nombreLocal').textContent     = local;
  document.getElementById('nombreVisitante').textContent = visitante;

  // Actualizar etiquetas de los chips con los nombres reales
  const abrev = s => s.length > 10 ? s.substring(0,10)+'.' : s;
  for (let i = 1; i <= 3; i++) {
    const ll = document.getElementById('chipLbl'+i+'_l');
    const lv = document.getElementById('chipLbl'+i+'_v');
    if (ll) ll.textContent = abrev(local);
    if (lv) lv.textContent = abrev(visitante);
  }

  // Limpiar chips activos
  document.querySelectorAll('.ir-chip.active').forEach(c => c.classList.remove('active'));

  const sec = document.getElementById('seccionSets');
  sec.style.display = 'block';
  setTimeout(() => sec.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
}

function setChip(setNum, local, visitante, btn) {
  document.getElementById('s'+setNum+'_local').value     = local;
  document.getElementById('s'+setNum+'_visitante').value = visitante;
  // Deseleccionar otros chips del mismo set
  document.querySelectorAll('#chips_s'+setNum+' .ir-chip').forEach(c => c.classList.remove('active'));
  btn.classList.add('active');
}

function clearChip(setNum) {
  document.querySelectorAll('#chips_s'+setNum+' .ir-chip').forEach(c => c.classList.remove('active'));
}
// Auto-seleccionar el partido correspondiente (o el primero si no hay pre-selección)
document.addEventListener('DOMContentLoaded', function() {
  const selected = document.querySelector('#listaPartidos input[type=radio]:checked:not([disabled])') || document.querySelector('#listaPartidos input[type=radio]:not([disabled])');
  if (selected) {
    selected.checked = true;
    seleccionarPartido(selected);
  }
});
</script>

<?php require_once 'includes/footer.php'; ?>
