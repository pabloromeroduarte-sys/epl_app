<?php
$page_title       = 'Ranking de Parejas';
$active_nav       = 'ranking';
$page_css         = 'ranking';
$meta_description = 'Ranking anual de parejas Elite Padel League: suma puntos en cada torneo y clasifica al Máster Final EPL.';
$meta_keywords    = 'ranking parejas padel chile, master final padel, circuito anual padel, torneos padel por puntos, elite padel league ranking';

require_once 'includes/functions.php';

$db = epl_db();
$categoria = isset($_GET['categoria']) && ctype_digit((string)$_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$sexo = in_array($_GET['sexo'] ?? '', ['masculino','femenino','mixto'], true) ? (string)$_GET['sexo'] : '';

$temporada = (int)date('Y');
$where = ["YEAR(rp1.fecha_competicion)=?"];
$params = [$temporada];
if ($categoria > 0) { $where[] = 'l.categoria=?'; $params[] = $categoria; }
if ($sexo !== '') { $where[] = 'l.sexo=?'; $params[] = $sexo; }

$sql = "
    SELECT e.id,e.nombre AS equipo_nombre,e.jugador1_id,e.jugador2_id,
           j1.nombre AS j1_nombre,j1.apellido AS j1_apellido,j1.foto AS j1_foto,
           j2.nombre AS j2_nombre,j2.apellido AS j2_apellido,j2.foto AS j2_foto,
           SUM(LEAST(rp1.puntos,rp2.puntos)) AS puntos_total,
           COUNT(DISTINCT le.liga_id) AS torneos,
           SUM(LEAST(rp1.posicion_final,rp2.posicion_final)=1) AS titulos,
           SUM(LEAST(rp1.posicion_final,rp2.posicion_final)<=3) AS podios,
           MAX(rp1.fecha_competicion) AS ultima_fecha
    FROM liga_equipos le
    JOIN equipos e ON e.id=le.equipo_id
    JOIN jugadores j1 ON j1.id=e.jugador1_id
    JOIN jugadores j2 ON j2.id=e.jugador2_id
    JOIN ligas l ON l.id=le.liga_id
    JOIN ranking_puntos rp1 ON rp1.liga_id=le.liga_id AND rp1.jugador_id=e.jugador1_id
    JOIN ranking_puntos rp2 ON rp2.liga_id=le.liga_id AND rp2.jugador_id=e.jugador2_id
    WHERE ".implode(' AND ', $where)."
    GROUP BY e.id,e.nombre,e.jugador1_id,e.jugador2_id,
             j1.nombre,j1.apellido,j1.foto,j2.nombre,j2.apellido,j2.foto
    ORDER BY puntos_total DESC,titulos DESC,podios DESC,torneos DESC,e.nombre
";
$st = $db->prepare($sql);
$st->execute($params);
$ranking = $st->fetchAll();

$categorias = array_map('intval', $db->query("SELECT DISTINCT categoria FROM ligas WHERE categoria IS NOT NULL ORDER BY categoria")->fetchAll(PDO::FETCH_COLUMN));
$modelo = $db->query("SELECT puntos_1,puntos_2,puntos_3,puntos_4,puntos_grupos FROM ligas ORDER BY (tipo='torneo') DESC,id DESC LIMIT 1")->fetch() ?: [];
$escala = [
    ['puesto'=>'Campeón','puntos'=>(int)($modelo['puntos_1'] ?? 100),'numero'=>'01'],
    ['puesto'=>'Finalista','puntos'=>(int)($modelo['puntos_2'] ?? 70),'numero'=>'02'],
    ['puesto'=>'Tercer lugar','puntos'=>(int)($modelo['puntos_3'] ?? 50),'numero'=>'03'],
    ['puesto'=>'Cuarto lugar','puntos'=>(int)($modelo['puntos_4'] ?? 30),'numero'=>'04'],
    ['puesto'=>'Participación','puntos'=>(int)($modelo['puntos_grupos'] ?? 10),'numero'=>'+'],
];

$total_puntos = array_sum(array_map(static fn($r) => (int)$r['puntos_total'], $ranking));
$total_fechas = 0;
foreach ($ranking as $r) $total_fechas += (int)$r['torneos'];
?>
<?php require_once 'includes/header.php'; ?>

<div class="ranking-page">
    <header class="ranking-hero">
        <div class="ranking-hero__number" aria-hidden="true">01</div>
        <div class="ranking-hero__inner">
            <div class="ranking-hero__copy">
                <span class="ranking-kicker"><i></i> Circuito anual · Temporada <?= $temporada ?></span>
                <p>Carrera de parejas al Máster</p>
                <h1>Juntos.<br><span>Hasta el Máster.</span></h1>
                <div class="ranking-hero__lead">Cada torneo del calendario entrega puntos a la pareja. La suma de toda la temporada define quiénes consiguen un lugar en el Máster Final EPL.</div>
                <a href="<?= epl_url('torneos.php') ?>" class="ranking-btn ranking-btn--gold">Ver calendario y sumar →</a>
            </div>
            <div class="ranking-hero__stats">
                <div><strong><?= count($ranking) ?></strong><span>Parejas<br>clasificadas</span></div>
                <div><strong><?= $total_puntos ?></strong><span>Puntos<br>entregados</span></div>
                <div><strong><?= $total_fechas ?></strong><span>Fechas<br>disputadas</span></div>
            </div>
        </div>
    </header>

    <main class="ranking-main">
        <section class="ranking-toolbar" aria-label="Filtros de ranking">
            <div>
                <span>Temporada <?= $temporada ?></span>
                <strong>Carrera al Máster</strong>
            </div>
            <form method="get" class="ranking-filters">
                <label>
                    <span>Categoría</span>
                    <select name="categoria">
                        <option value="0">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat ?>" <?= $categoria===$cat?'selected':'' ?>><?= $cat ?>ª categoría</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Modalidad</span>
                    <select name="sexo">
                        <option value="">Todas</option>
                        <option value="masculino" <?= $sexo==='masculino'?'selected':'' ?>>Masculino</option>
                        <option value="femenino" <?= $sexo==='femenino'?'selected':'' ?>>Femenino</option>
                        <option value="mixto" <?= $sexo==='mixto'?'selected':'' ?>>Mixto</option>
                    </select>
                </label>
                <button type="submit">Aplicar</button>
            </form>
        </section>

        <?php if ($ranking): ?>
        <section class="ranking-podium" aria-label="Podio de parejas del ranking">
            <?php
            $orden_podio = [1,0,2];
            foreach ($orden_podio as $podio_idx):
                if (empty($ranking[$podio_idx])) continue;
                $r = $ranking[$podio_idx];
                $pos = $podio_idx + 1;
                $jugador1 = trim($r['j1_nombre'].' '.$r['j1_apellido']);
                $jugador2 = trim($r['j2_nombre'].' '.$r['j2_apellido']);
                $nombre_equipo = trim($r['equipo_nombre']) ?: $jugador1.' / '.$jugador2;
            ?>
            <article class="ranking-podium__card ranking-podium__card--<?= $pos ?>">
                <span class="ranking-podium__pos"><?= str_pad((string)$pos,2,'0',STR_PAD_LEFT) ?></span>
                <div class="ranking-podium__duo" aria-label="<?= epl_h($jugador1.' y '.$jugador2) ?>">
                    <img src="<?= epl_h(epl_foto_jugador($r['j1_foto'],$jugador1)) ?>" alt="<?= epl_h($jugador1) ?>">
                    <img src="<?= epl_h(epl_foto_jugador($r['j2_foto'],$jugador2)) ?>" alt="<?= epl_h($jugador2) ?>">
                </div>
                <strong><?= epl_h($nombre_equipo) ?></strong>
                <small><?= epl_h($jugador1) ?> · <?= epl_h($jugador2) ?></small>
                <span class="ranking-podium__meta"><?= (int)$r['titulos'] ?> título<?= (int)$r['titulos']===1?'':'s' ?> · <?= (int)$r['torneos'] ?> fecha<?= (int)$r['torneos']===1?'':'s' ?></span>
                <b><?= (int)$r['puntos_total'] ?> <i>pts</i></b>
            </article>
            <?php endforeach; ?>
        </section>

        <section class="ranking-table-card">
            <div class="ranking-table-card__head">
                <span>Posición</span><span>Pareja</span><span>Fechas</span><span>Podios</span><span>Puntos</span>
            </div>
            <?php foreach ($ranking as $i => $r):
                $jugador1 = trim($r['j1_nombre'].' '.$r['j1_apellido']);
                $jugador2 = trim($r['j2_nombre'].' '.$r['j2_apellido']);
                $nombre_equipo = trim($r['equipo_nombre']) ?: $jugador1.' / '.$jugador2;
            ?>
            <div class="ranking-table-row">
                <b><?= str_pad((string)($i+1),2,'0',STR_PAD_LEFT) ?></b>
                <span class="ranking-table-row__player">
                    <span class="ranking-table-row__duo" aria-hidden="true">
                        <img src="<?= epl_h(epl_foto_jugador($r['j1_foto'],$jugador1)) ?>" alt="" loading="lazy">
                        <img src="<?= epl_h(epl_foto_jugador($r['j2_foto'],$jugador2)) ?>" alt="" loading="lazy">
                    </span>
                    <span><strong><?= epl_h($nombre_equipo) ?></strong><small><?= epl_h($jugador1) ?> · <?= epl_h($jugador2) ?></small></span>
                </span>
                <span><?= (int)$r['torneos'] ?></span>
                <span><?= (int)$r['podios'] ?></span>
                <em><?= (int)$r['puntos_total'] ?> <small>pts</small></em>
            </div>
            <?php endforeach; ?>
        </section>
        <?php else: ?>
        <section class="ranking-zero">
            <span>00</span>
            <div>
                <p>La carrera está lista</p>
                <h2>Todas las parejas parten desde cero.</h2>
                <div>La primera fecha del calendario inaugurará el ranking anual. Compite con tu pareja, acumula puntos y comienza el camino hacia el Máster Final.</div>
                <a href="<?= epl_url('torneos.php') ?>" class="ranking-btn ranking-btn--navy">Ver calendario anual →</a>
            </div>
        </section>
        <?php endif; ?>

        <section class="ranking-rules">
            <div class="ranking-rules__intro">
                <span>Cómo se clasifica</span>
                <h2>Fecha a fecha, hasta el Máster.</h2>
                <p>El ranking suma los resultados de la pareja durante la temporada <?= $temporada ?>. Cuando se cierra el calendario, las mejores parejas de cada categoría clasifican al Máster Final EPL.</p>
            </div>
            <div class="ranking-points-scale">
                <?php foreach ($escala as $item): ?>
                <div><span><?= $item['numero'] ?></span><strong><?= $item['puntos'] ?></strong><small><?= epl_h($item['puesto']) ?></small></div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
