<?php
$file = 'admin/proximos_partidos.php';
$content = file_get_contents($file);

// 1. Rename page title
$content = str_replace("\$page_title = 'Admin — Partidos';", "\$page_title = 'Admin — Próximos Partidos';", $content);

// 2. Change redirects
$content = str_replace("function partidos_redirect", "function prox_partidos_redirect", $content);
$content = str_replace("'partidos.php'", "'proximos_partidos.php'", $content);
$content = preg_replace("/partidos_redirect\(/", "prox_partidos_redirect(", $content);

// 3. Remove bulk actions and create
$content = preg_replace("/if \(\\$action === 'bulk_partidos'\) \{.*?\n    \}[\r\n\s]*\/\/ ── Crear partido/s", "// ── Crear partido", $content);
$content = preg_replace("/if \(\\$action === 'crear_partido'\) \{.*?\n    \}[\r\n\s]*\/\/ ── Editar resultado/s", "// ── Editar resultado", $content);

// 4. Overwrite WHERE and Params
$where_replace = <<<'PHP'
$where_p = "WHERE p.estado IN ('pendiente', 'reprogramado') AND p.fecha_programada IS NOT NULL AND DATE(p.fecha_programada) >= CURDATE() AND DATE(p.fecha_programada) != '2026-12-31'";
$params_p = [];
if ($liga_id) { $where_p .= " AND p.liga_id=?"; $params_p[] = $liga_id; }
if ($f_search) {
    foreach (explode(' ', $f_search) as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $where_p .= " AND (el.nombre LIKE ? OR ev.nombre LIKE ? OR
                           jl1.nombre LIKE ? OR jl1.apellido LIKE ? OR jl2.nombre LIKE ? OR jl2.apellido LIKE ? OR
                           jv1.nombre LIKE ? OR jv1.apellido LIKE ? OR jv2.nombre LIKE ? OR jv2.apellido LIKE ? OR
                           l.nombre LIKE ?)";
        $p_val = "%$part%";
        for ($i = 0; $i < 11; $i++) $params_p[] = $p_val;
    }
}
PHP;

$content = preg_replace('/\$where_p = "WHERE 1=1";.*?(?=\/\/ Asegurar columnas originales)/s', $where_replace . "\n\n", $content);

// 5. Overwrite ORDER BY
$content = preg_replace('/ORDER BY l\.id DESC, p\.jornada ASC, p\.fecha_programada ASC, p\.id ASC/', 'ORDER BY p.fecha_programada ASC, p.id ASC', $content);

// 6. Title and Headers inside UI
$content = str_replace("Gestión de <span style=\"color:#C9A762\">Partidos</span>", "Próximos <span style=\"color:#C9A762\">Partidos</span>", $content);
$content = preg_replace('/<p style="color:rgba\(255,255,255,\.7\).*?<\/p>/', '<p style="color:rgba(255,255,255,.7);margin-top:.15rem;font-size:.75rem;line-height:1.3"><?= count($partidos) ?> partido<?= count($partidos)!==1?\'s\':\'\' ?> pendientes a futuro</p>', $content);
$content = preg_replace('/<button onclick="document\.getElementById\(\'modalCrearPartido\'.*?<\/button>/', '', $content);

// 7. Remove Bulk Bar HTML and creation Modal
$content = preg_replace('/<!-- ── BARRA BULK STICKY ─────────────────── -->.*?<!-- ── TABLA PARTIDOS/s', '<!-- ── TABLA PARTIDOS', $content);
$content = preg_replace('/<!-- ════════ MODAL CREAR PARTIDO ════════ -->.*?<!-- ════════ MODAL EDITAR PARTIDO/s', '<!-- ════════ MODAL EDITAR PARTIDO', $content);

// 8. Adjust grouping to by Date instead of by Liga
$grouping_logic = <<<'PHP'
            <?php
            $current_grouping = null;
            $hoy = date('Y-m-d');
            $manana = date('Y-m-d', strtotime('+1 day'));
            
            foreach ($partidos as $p):
              $dia_str = date('Y-m-d', strtotime($p['fecha_programada']));
              $grouping = $dia_str;
              if ($grouping !== $current_grouping):
                $current_grouping = $grouping;
                
                $lbl_dia = date('d/m/Y', strtotime($dia_str));
                if ($dia_str === $hoy) $lbl_dia = 'Hoy';
                elseif ($dia_str === $manana) $lbl_dia = 'Mañana';
                else {
                    $dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                    $lbl_dia = $dias[date('w', strtotime($dia_str))] . ' ' . $lbl_dia;
                }
            ?>
            <tr style="background:#f1f5f9">
              <td colspan="7" style="padding:.6rem 1rem;font-size:.8rem;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:var(--navy)">
                📅 <?= epl_h($lbl_dia) ?>
              </td>
            </tr>
            <?php endif; ?>
PHP;

$content = preg_replace('/<\?php\s*\$current_grouping = null;\s*foreach \(\$partidos as \$p\):\s*\$grouping = \(\$p\[\'liga_nombre\'\].*?<\?php endif; \?>/s', $grouping_logic, $content);
$content = preg_replace('/<th style="padding:\.65rem \.75rem;width:40px;text-align:center"><input type="checkbox" id="checkAllPartidos"><\/th>/', '', $content);
$content = preg_replace('/<td style="padding:\.6rem \.75rem;text-align:center">\s*<input type="checkbox"[^>]*>\s*<\/td>/', '', $content);

// 9. Mobile Grouping
$grouping_mobile = <<<'PHP'
          <?php
          $current_grouping_m = null;
          foreach ($partidos as $p):
            $dia_str = date('Y-m-d', strtotime($p['fecha_programada']));
            $grouping_m = $dia_str;
            
            $_fp_m = $p['fecha_programada'];
            $_sf_m = false;
            $_bc_m = 'badge-pendiente';
            $_lbl_m = 'Pendiente';
            if ($p['estado'] === 'reprogramado') { $_bc_m = 'badge-reprog'; $_lbl_m = 'Reprog.'; }
            
            if ($grouping_m !== $current_grouping_m):
              $current_grouping_m = $grouping_m;
              $lbl_dia = date('d/m/Y', strtotime($dia_str));
              if ($dia_str === date('Y-m-d')) $lbl_dia = 'HOY';
              elseif ($dia_str === date('Y-m-d', strtotime('+1 day'))) $lbl_dia = 'MAÑANA';
              else {
                  $dias = ['DOMINGO','LUNES','MARTES','MIÉRCOLES','JUEVES','VIERNES','SÁBADO'];
                  $lbl_dia = $dias[date('w', strtotime($dia_str))] . ' ' . $lbl_dia;
              }
          ?>
            <div class="pf-card-group-header" style="justify-content:center;background:var(--navy);color:var(--gold);padding:.6rem;border-radius:6px;margin:1rem 0 .5rem">
                <div style="font-weight:900;font-size:.85rem">📅 <?= epl_h($lbl_dia) ?></div>
            </div>
          <?php endif; ?>
PHP;

$content = preg_replace('/<\?php\s*\$current_grouping_m = null;\s*foreach \(\$partidos as \$p\):.*?(?=<div class="pf-card-partido")/s', $grouping_mobile, $content);
$content = preg_replace('/<input type="checkbox" name="partido_ids\[\]"[^>]*>/', '', $content);

file_put_contents($file, $content);
echo "Done.";
?>
