<?php
$page_title = 'Admin — Jugadores';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db  = epl_db();
$ok  = '';
$err = '';

// ── Exportar CSV ─────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="jugadores_epl_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['ID','Nombre','Apellido','Alias','RUT','Email','Teléfono','Sexo','Fecha nac.','Comuna','Profesión','Nivel','Lado','Pala','Talla','Frecuencia','Rol','Estado','Creado'], ';');
    $rows = $db->query("SELECT * FROM jugadores ORDER BY apellido, nombre")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],$r['nombre'],$r['apellido'],$r['alias']??'',$r['rut']??'',$r['email'],
            $r['telefono']??'',$r['sexo']??'',$r['fecha_nacimiento']??'',
            $r['comuna']??'',$r['profesion']??'',$r['nivel']??'',$r['lado']??'',$r['pala']??'',
            $r['talla']??'',$r['frecuencia_juego']??'',$r['rol'],$r['estado'],
            $r['created_at']
        ], ';');
    }
    fclose($out);
    exit;
}

// ── Acciones POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'crear') {
        $email    = strtolower(trim($_POST['email']    ?? ''));
        $nombre   = trim($_POST['nombre']   ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $rol      = in_array($_POST['rol']??'jugador',['jugador','admin']) ? $_POST['rol'] : 'jugador';
        $pass_raw = trim($_POST['password'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $nivel    = (int)($_POST['nivel'] ?? 5);

        if (!$email || !$nombre || !$pass_raw) { $err = 'Rellena los campos obligatorios.'; }
        else {
            try {
                $db->prepare("INSERT INTO jugadores (email,password,nombre,apellido,rol,telefono,nivel) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$email, epl_hash_password($pass_raw), $nombre, $apellido, $rol, $telefono?:null, $nivel]);
                $ok = "Jugador <strong>{$nombre} {$apellido}</strong> creado.";
            } catch (PDOException $e) {
                $err = $e->getCode() == 23000 ? 'Ese email ya existe.' : 'Error al crear.';
            }
        }

    } elseif ($action === 'toggle_estado') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = $_POST['estado'] ?? 'activo';
        $new = $cur === 'activo' ? 'inactivo' : 'activo';
        $db->prepare("UPDATE jugadores SET estado=? WHERE id=?")->execute([$new, $id]);
        $ok = 'Estado actualizado.';

    } elseif ($action === 'reset_pass') {
        $id  = (int)($_POST['id']       ?? 0);
        $new = trim($_POST['new_pass']  ?? '');
        if (strlen($new) < 6) { $err = 'Contraseña mínimo 6 caracteres.'; }
        else {
            $db->prepare("UPDATE jugadores SET password=? WHERE id=?")->execute([epl_hash_password($new), $id]);
            $ok = 'Contraseña actualizada.';
        }

    } elseif ($action === 'editar') {
        $id        = (int)($_POST['id']        ?? 0);
        $nombre    = trim($_POST['nombre']     ?? '');
        $apellido  = trim($_POST['apellido']   ?? '');
        $alias     = trim($_POST['alias']      ?? '');
        $rut       = trim($_POST['rut']        ?? '');
        $telefono  = trim($_POST['telefono']   ?? '');
        $fnac      = trim($_POST['fecha_nacimiento'] ?? '') ?: null;
        $sexo      = in_array($_POST['sexo']??'',['M','F','otro']) ? $_POST['sexo'] : null;
        $comuna    = trim($_POST['comuna']     ?? '');
        $profesion = trim($_POST['profesion']  ?? '');
        $nivel     = (int)($_POST['nivel']     ?? 5);
        $lado      = in_array($_POST['lado']??'',['derecha','reves','ambos']) ? $_POST['lado'] : null;
        $pala      = trim($_POST['pala']       ?? '');
        $talla     = trim($_POST['talla']      ?? '');
        $frec      = in_array($_POST['frecuencia_juego']??'',['1_semana','2_semana','3_o_mas','ocasional']) ? $_POST['frecuencia_juego'] : null;
        $rol       = in_array($_POST['rol']??'jugador',['jugador','admin']) ? $_POST['rol'] : 'jugador';
        $estado    = in_array($_POST['estado']??'activo',['activo','inactivo','suspendido']) ? $_POST['estado'] : 'activo';

        if (!$nombre) { $err = 'El nombre es obligatorio.'; }
        else {
            $db->prepare("UPDATE jugadores SET
                nombre=?,apellido=?,alias=?,rut=?,telefono=?,
                fecha_nacimiento=?,sexo=?,comuna=?,profesion=?,
                nivel=?,lado=?,pala=?,talla=?,frecuencia_juego=?,
                rol=?,estado=? WHERE id=?")
               ->execute([
                   $nombre,$apellido,$alias?:null,$rut?:null,$telefono?:null,
                   $fnac,$sexo,$comuna?:null,$profesion?:null,
                   $nivel,$lado,$pala?:null,$talla?:null,$frec,
                   $rol,$estado,$id
               ]);
            $ok = 'Jugador actualizado.';
        }
    }
}

$profesiones = [
    'Arquitectura / Diseño','Arte / Creatividad','Comercial / Ventas',
    'Construcción / Inmobiliaria','Derecho / Jurídico','Educación / Docencia',
    'Emprendedor / Independiente','Finanzas / Banca','Gerencia / Dirección',
    'Ingeniería / Construcción','Logística / Operaciones','Minería / Energía',
    'Publicidad / Marketing','Recursos Humanos','Salud / Medicina',
    'Estudiante','Tecnología / IT','Otro',
];

// ── Filtros ───────────────────────────────────────────────
$f_nivel  = (int)($_GET['nivel']  ?? 0);
$f_estado = $_GET['estado'] ?? '';
$f_q      = trim($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];
if ($f_nivel)  { $where[] = 'nivel=?'; $params[] = $f_nivel; }
if ($f_estado) { $where[] = 'estado=?'; $params[] = $f_estado; }
if ($f_q) {
    $where[] = "(nombre LIKE ? OR apellido LIKE ? OR email LIKE ? OR rut LIKE ?)";
    $like = "%{$f_q}%";
    $params = array_merge($params, [$like,$like,$like,$like]);
}
$whereStr = implode(' AND ', $where);
$st = $db->prepare("SELECT * FROM jugadores WHERE $whereStr ORDER BY apellido, nombre");
$st->execute($params);
$jugadores = $st->fetchAll();
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header" style="display:flex;flex-wrap:wrap;gap:.75rem;justify-content:space-between;align-items:center">
      <h1 class="dash-title">Jugadores <span style="font-size:1rem;color:var(--gray-400)">(<?= count($jugadores) ?>)</span></h1>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;width:100%">
        <a href="?export=csv&nivel=<?= $f_nivel ?>&estado=<?= urlencode($f_estado) ?>&q=<?= urlencode($f_q) ?>"
           class="btn btn-sm btn-outline-navy" style="flex:1;text-align:center">↓ Exportar CSV</a>
        <button onclick="document.getElementById('modalCrear').style.display='flex'"
                class="btn btn-primary btn-sm" style="flex:1">+ Nuevo jugador</button>
      </div>
    </div>

    <?php if ($ok): ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>

    <!-- Filtros -->
    <div class="card mb-4" style="padding:1rem 1.5rem">
      <form method="get" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <div>
          <label style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Buscar</label>
          <input type="text" name="q" value="<?= epl_h($f_q) ?>" class="form-control" style="width:200px" placeholder="Nombre, email, RUT...">
        </div>
        <div>
          <label style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Nivel</label>
          <select name="nivel" class="form-control" style="width:auto">
            <option value="">Todos</option>
            <?php for ($n=1;$n<=8;$n++): ?>
              <option value="<?= $n ?>" <?= $f_nivel==$n?'selected':'' ?>><?= $n ?>ª cat.</option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Estado</label>
          <select name="estado" class="form-control" style="width:auto">
            <option value="">Todos</option>
            <option value="activo"    <?= $f_estado==='activo'?'selected':'' ?>>Activo</option>
            <option value="inactivo"  <?= $f_estado==='inactivo'?'selected':'' ?>>Inactivo</option>
            <option value="suspendido" <?= $f_estado==='suspendido'?'selected':'' ?>>Suspendido</option>
          </select>
        </div>
        <button type="submit" class="btn btn-navy btn-sm">Filtrar</button>
        <?php if ($f_q || $f_nivel || $f_estado): ?>
          <a href="jugadores.php" class="btn btn-sm" style="border:1px solid var(--gray-200);color:var(--gray-600)">Limpiar</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="card">
      <div style="overflow-x:auto">
        <table class="admin-table-cards" style="width:100%;border-collapse:collapse;font-size:.83rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Jugador</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Contacto</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Deportivo</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Rol</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Estado</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jugadores as $j): ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td style="padding:.7rem 1rem">
                <div style="display:flex;align-items:center;gap:.65rem">
                  <img src="<?= epl_h(epl_foto_jugador($j['foto'],$j['nombre'].' '.$j['apellido'])) ?>"
                       style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0" alt="">
                  <div>
                    <div style="font-weight:700;color:var(--navy)"><?= epl_h($j['nombre'].' '.$j['apellido']) ?></div>
                    <?php if ($j['alias']): ?><div style="font-size:.7rem;color:var(--gold)">"<?= epl_h($j['alias']) ?>"</div><?php endif; ?>
                    <?php if ($j['rut']):   ?><div style="font-size:.7rem;color:var(--gray-400)"><?= epl_h($j['rut']) ?></div><?php endif; ?>
                  </div>
                </div>
              </td>
              <td data-label="Contacto" style="padding:.7rem 1rem">
                <div style="font-size:.8rem;color:var(--gray-600)"><?= epl_h($j['email']) ?></div>
                <?php if ($j['telefono']): ?>
                  <?php
                    $tel = $j['telefono'];
                    $d   = preg_replace('/\D/', '', $tel);
                    if (strlen($d)===11 && str_starts_with($d,'569'))
                        $fmt = '+56 9 '.substr($d,3,4).' '.substr($d,7,4);
                    elseif (strlen($d)===9 && str_starts_with($d,'9'))
                        $fmt = '+56 '.$d[0].' '.substr($d,1,4).' '.substr($d,5,4);
                    else $fmt = $tel;
                  ?>
                  <div style="font-size:.75rem;margin-top:.15rem">
                    <a href="https://wa.me/<?= $d ?>" target="_blank" style="color:#25d366;font-weight:600">
                      <?= epl_h($fmt) ?>
                    </a>
                  </div>
                <?php endif; ?>
                <?php if ($j['comuna']): ?><div style="font-size:.7rem;color:var(--gray-400)"><?= epl_h($j['comuna']) ?></div><?php endif; ?>
              </td>
              <td data-label="Deportivo" class="hide-mobile" style="padding:.7rem 1rem;text-align:center">
                <?php if ($j['nivel']): ?>
                  <span style="font-size:.75rem;font-weight:700;color:var(--navy)"><?= $j['nivel'] ?>ª cat.</span>
                <?php endif; ?>
                <?php if ($j['lado']): ?>
                  <div style="font-size:.7rem;color:var(--gray-400)"><?= ucfirst($j['lado']) ?></div>
                <?php endif; ?>
                <?php if ($j['pala']): ?>
                  <div style="font-size:.68rem;color:var(--gray-400)"><?= epl_h($j['pala']) ?></div>
                <?php endif; ?>
              </td>
              <td data-label="Rol" class="hide-mobile" style="padding:.7rem 1rem;text-align:center">
                <span class="badge <?= $j['rol']==='admin'?'badge-walkover':'badge-pendiente' ?>"><?= $j['rol'] ?></span>
              </td>
              <td data-label="Estado" style="padding:.7rem 1rem;text-align:center">
                <span class="badge <?= $j['estado']==='activo'?'badge-jugado':($j['estado']==='suspendido'?'badge-walkover':'badge-reprog') ?>"><?= $j['estado'] ?></span>
              </td>
              <td data-label="Acciones" style="padding:.7rem 1rem">
                <div style="display:flex;gap:.35rem;flex-wrap:wrap;width:100%">
                  <button onclick='showEditar(<?= json_encode($j) ?>)' class="btn btn-sm" style="border:1px solid var(--gold);color:var(--gold-dark);font-size:.7rem">Editar</button>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="toggle_estado">
                    <input type="hidden" name="id" value="<?= $j['id'] ?>">
                    <input type="hidden" name="estado" value="<?= $j['estado'] ?>">
                    <button type="submit" class="btn btn-sm" style="border:1px solid var(--gray-200);color:var(--gray-600);font-size:.7rem">
                      <?= $j['estado']==='activo'?'Desactivar':'Activar' ?>
                    </button>
                  </form>
                  <button onclick="showResetPass(<?= $j['id'] ?>, '<?= epl_h($j['nombre']) ?>')"
                          class="btn btn-sm" style="border:1px solid var(--gray-200);color:var(--gray-600);font-size:.7rem">Pass</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($jugadores)): ?>
              <tr><td colspan="6" style="padding:2rem;text-align:center;color:var(--gray-400)">No hay jugadores con ese filtro.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Modal crear -->
<div id="modalCrear" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:500px;max-height:90vh;overflow-y:auto">
    <div class="card-head">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Nuevo Jugador</h3>
      <button onclick="document.getElementById('modalCrear').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="crear">
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Nombre *</label><input type="text" name="nombre" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Apellido</label><input type="text" name="apellido" class="form-control"></div>
        </div>
        <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input type="tel" name="telefono" class="form-control" placeholder="+56 9 1234 5678">
          </div>
          <div class="form-group">
            <label class="form-label">Nivel</label>
            <select name="nivel" class="form-control">
              <?php for ($n=1;$n<=8;$n++): ?><option value="<?= $n ?>" <?= $n==5?'selected':'' ?>><?= $n ?>ª cat.</option><?php endfor; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Contraseña *</label>
          <input type="password" name="password" class="form-control" required minlength="6">
          <span class="form-hint">El jugador puede cambiarla desde su perfil.</span>
        </div>
        <div class="form-group">
          <label class="form-label">Rol</label>
          <select name="rol" class="form-control"><option value="jugador">Jugador</option><option value="admin">Admin</option></select>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Crear jugador</button>
      </form>
    </div>
  </div>
</div>

<!-- Modal editar -->
<div id="modalEditar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:600px;max-height:92vh;overflow-y:auto">
    <div class="card-head" style="position:sticky;top:0;background:#fff;z-index:1">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)" id="editarTitle">Editar Jugador</h3>
      <button onclick="document.getElementById('modalEditar').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id" id="editarId">

        <!-- Email (solo lectura) -->
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" id="editarEmailDisplay" class="form-control" disabled style="background:var(--gray-100);color:var(--gray-400)">
        </div>

        <!-- Nombre / Apellido -->
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Nombre *</label><input type="text" name="nombre" id="editarNombre" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Apellido</label><input type="text" name="apellido" id="editarApellido" class="form-control"></div>
        </div>

        <!-- Alias / RUT -->
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Alias</label><input type="text" name="alias" id="editarAlias" class="form-control" placeholder="Opcional"></div>
          <div class="form-group"><label class="form-label">RUT</label><input type="text" name="rut" id="editarRut" class="form-control" placeholder="12.345.678-9"></div>
        </div>

        <!-- Fecha nac / Sexo -->
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" id="editarFnac" class="form-control"></div>
          <div class="form-group">
            <label class="form-label">Sexo</label>
            <select name="sexo" id="editarSexo" class="form-control">
              <option value="">— No indicar —</option>
              <option value="M">Masculino</option>
              <option value="F">Femenino</option>
              <option value="otro">Otro</option>
            </select>
          </div>
        </div>

        <!-- Teléfono -->
        <div class="form-group">
          <label class="form-label">Teléfono</label>
          <input type="tel" name="telefono" id="editarTelefono" class="form-control" placeholder="+56 9 1234 5678">
          <span class="form-hint">Formato: +56 9 1234 5678</span>
        </div>

        <!-- Comuna / Profesión -->
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Comuna</label><input type="text" name="comuna" id="editarComuna" class="form-control" placeholder="Las Condes..."></div>
          <div class="form-group">
            <label class="form-label">Profesión</label>
            <select name="profesion" id="editarProfesion" class="form-control">
              <option value="">— Selecciona —</option>
              <?php foreach ($profesiones as $pr): ?><option value="<?= epl_h($pr) ?>"><?= epl_h($pr) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Nivel / Lado -->
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nivel</label>
            <select name="nivel" id="editarNivel" class="form-control">
              <?php for ($n=1;$n<=8;$n++): ?><option value="<?= $n ?>"><?= $n ?>ª cat.</option><?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Lado</label>
            <select name="lado" id="editarLado" class="form-control">
              <option value="">— No definido —</option>
              <option value="derecha">Drive</option>
              <option value="reves">Revés</option>
              <option value="ambos">Ambos</option>
            </select>
          </div>
        </div>

        <!-- Pala / Talla -->
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Pala</label><input type="text" name="pala" id="editarPala" class="form-control" placeholder="Marca / Modelo"></div>
          <div class="form-group">
            <label class="form-label">Talla camiseta</label>
            <select name="talla" id="editarTalla" class="form-control">
              <option value="">— No indicar —</option>
              <?php foreach (['XS','S','M','L','XL','XXL','XXXL'] as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Frecuencia -->
        <div class="form-group">
          <label class="form-label">Frecuencia de juego</label>
          <select name="frecuencia_juego" id="editarFrecuencia" class="form-control">
            <option value="">— No indicar —</option>
            <option value="1_semana">1 vez por semana</option>
            <option value="2_semana">2 veces por semana</option>
            <option value="3_o_mas">3 o más veces por semana</option>
            <option value="ocasional">Ocasional</option>
          </select>
        </div>

        <!-- Rol / Estado -->
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Rol</label>
            <select name="rol" id="editarRol" class="form-control">
              <option value="jugador">Jugador</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Estado</label>
            <select name="estado" id="editarEstado" class="form-control">
              <option value="activo">Activo</option>
              <option value="inactivo">Inactivo</option>
              <option value="suspendido">Suspendido</option>
            </select>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem">Guardar cambios</button>
      </form>
    </div>
  </div>
</div>

<!-- Modal reset contraseña -->
<div id="modalResetPass" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center">
  <div class="card" style="width:100%;max-width:360px">
    <div class="card-head">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)" id="resetPassTitle">Reset contraseña</h3>
      <button onclick="document.getElementById('modalResetPass').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="reset_pass">
        <input type="hidden" name="id" id="resetPassId">
        <div class="form-group">
          <label class="form-label">Nueva contraseña</label>
          <input type="text" name="new_pass" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
        </div>
        <button type="submit" class="btn btn-navy" style="width:100%;justify-content:center">Cambiar contraseña</button>
      </form>
    </div>
  </div>
</div>

<script>
function showResetPass(id, nombre) {
  document.getElementById('resetPassId').value = id;
  document.getElementById('resetPassTitle').textContent = 'Reset — ' + nombre;
  document.getElementById('modalResetPass').style.display = 'flex';
}
function showEditar(j) {
  document.getElementById('editarId').value            = j.id;
  document.getElementById('editarTitle').textContent   = 'Editar — ' + j.nombre.toUpperCase() + ' ' + (j.apellido||'').toUpperCase();
  document.getElementById('editarEmailDisplay').value  = j.email        || '';
  document.getElementById('editarNombre').value        = j.nombre       || '';
  document.getElementById('editarApellido').value      = j.apellido     || '';
  document.getElementById('editarAlias').value         = j.alias        || '';
  document.getElementById('editarRut').value           = j.rut          || '';
  document.getElementById('editarFnac').value          = j.fecha_nacimiento || '';
  document.getElementById('editarSexo').value          = j.sexo         || '';
  document.getElementById('editarTelefono').value      = j.telefono     || '';
  document.getElementById('editarComuna').value        = j.comuna       || '';
  document.getElementById('editarProfesion').value     = j.profesion    || '';
  document.getElementById('editarNivel').value         = j.nivel        || 5;
  document.getElementById('editarLado').value          = j.lado         || '';
  document.getElementById('editarPala').value          = j.pala         || '';
  document.getElementById('editarTalla').value         = j.talla        || '';
  document.getElementById('editarFrecuencia').value    = j.frecuencia_juego || '';
  document.getElementById('editarRol').value           = j.rol          || 'jugador';
  document.getElementById('editarEstado').value        = j.estado       || 'activo';
  document.getElementById('modalEditar').style.display = 'flex';
}
</script>

<?php require_once '../includes/footer.php'; ?>
