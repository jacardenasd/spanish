<?php
// public/admin_org_puestos.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/conexion.php';

require_login();
require_empresa();
require_password_change_redirect();
require_perm('organizacion.admin');

if (session_status() === PHP_SESSION_NONE) session_start();
$empresa_id = (int)$_SESSION['empresa_id'];

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf_token = $_SESSION['csrf_token'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function bitacora($modulo, $accion, $detalle = null) {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE) session_start();
    $empresa_id = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : null;
    $usuario_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

    $sql = "INSERT INTO bitacora (empresa_id, usuario_id, modulo, accion, detalle_json, ip)
            VALUES (:empresa_id, :usuario_id, :modulo, :accion, :detalle_json, :ip)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':empresa_id' => $empresa_id,
        ':usuario_id' => $usuario_id,
        ':modulo' => (string)$modulo,
        ':accion' => (string)$accion,
        ':detalle_json' => $detalle !== null ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : null,
        ':ip' => $ip,
    ]);
}

$flash = null; $flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf_token, $post_token)) {
        $flash = 'Solicitud inválida (token).'; $flash_type = 'danger';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            $pdo->beginTransaction();

            if ($action === 'save') {
                $puesto_id = (int)($_POST['puesto_id'] ?? 0);
                $unidad_id = isset($_POST['unidad_id']) && $_POST['unidad_id'] !== '' ? (int)$_POST['unidad_id'] : null;
                $nombre = trim((string)($_POST['nombre'] ?? ''));
                $clave = trim((string)($_POST['clave'] ?? ''));
                $nivel = trim((string)($_POST['nivel'] ?? ''));
                $puntos_hay = isset($_POST['puntos_hay']) && $_POST['puntos_hay'] !== '' ? (int)$_POST['puntos_hay'] : null;
                $estatus = (int)($_POST['estatus'] ?? 1);

                if ($nombre === '') throw new RuntimeException('El nombre es obligatorio.');
                if (!in_array($estatus, [0,1], true)) $estatus = 1;

                if ($unidad_id !== null) {
                    $st = $pdo->prepare("SELECT unidad_id FROM org_unidades WHERE unidad_id = :uid AND empresa_id = :eid LIMIT 1");
                    $st->execute([':uid'=>$unidad_id, ':eid'=>$empresa_id]);
                    if (!$st->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('Unidad inválida para esta empresa.');
                }

                if ($puesto_id > 0) {
                    $upd = $pdo->prepare("
                        UPDATE org_puestos
                           SET unidad_id = :unidad_id,
                               nombre = :nombre,
                               clave = :clave,
                               nivel = :nivel,
                               puntos_hay = :puntos_hay,
                               estatus = :estatus
                         WHERE puesto_id = :id AND empresa_id = :eid
                         LIMIT 1
                    ");
                    $upd->execute([
                        ':unidad_id'=>$unidad_id,
                        ':nombre'=>$nombre,
                        ':clave'=>($clave!==''?$clave:null),
                        ':nivel'=>($nivel!==''?$nivel:null),
                        ':puntos_hay'=>$puntos_hay,
                        ':estatus'=>$estatus,
                        ':id'=>$puesto_id,
                        ':eid'=>$empresa_id,
                    ]);
                    bitacora('admin_org_puestos','update',['puesto_id'=>$puesto_id]);
                    $flash = 'Puesto actualizado.';
                } else {
                    $ins = $pdo->prepare("
                        INSERT INTO org_puestos (empresa_id, unidad_id, nombre, clave, nivel, puntos_hay, estatus)
                        VALUES (:eid, :unidad_id, :nombre, :clave, :nivel, :puntos_hay, :estatus)
                    ");
                    $ins->execute([
                        ':eid'=>$empresa_id,
                        ':unidad_id'=>$unidad_id,
                        ':nombre'=>$nombre,
                        ':clave'=>($clave!==''?$clave:null),
                        ':nivel'=>($nivel!==''?$nivel:null),
                        ':puntos_hay'=>$puntos_hay,
                        ':estatus'=>$estatus,
                    ]);
                    $new_id = (int)$pdo->lastInsertId();
                    bitacora('admin_org_puestos','insert',['puesto_id'=>$new_id]);
                    $flash = 'Puesto creado.';
                }

            } elseif ($action === 'toggle_status') {
                $puesto_id = (int)($_POST['puesto_id'] ?? 0);
                $st = $pdo->prepare("SELECT estatus FROM org_puestos WHERE puesto_id = :id AND empresa_id = :eid LIMIT 1");
                $st->execute([':id'=>$puesto_id, ':eid'=>$empresa_id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new RuntimeException('Puesto no encontrado.');
                $nuevo = ((int)$row['estatus'] === 1) ? 0 : 1;

                $upd = $pdo->prepare("UPDATE org_puestos SET estatus = :n WHERE puesto_id = :id AND empresa_id = :eid LIMIT 1");
                $upd->execute([':n'=>$nuevo, ':id'=>$puesto_id, ':eid'=>$empresa_id]);
                bitacora('admin_org_puestos','toggle_status',['puesto_id'=>$puesto_id,'nuevo'=>$nuevo]);
                $flash = 'Estatus actualizado.';
            } else {
                $flash = 'Acción no reconocida.'; $flash_type = 'warning';
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $flash = 'Error: ' . $e->getMessage(); $flash_type = 'danger';
        }
    }
}

// filtros
$f_q = trim((string)($_GET['q'] ?? ''));
$f_estatus = (string)($_GET['estatus'] ?? '');
$f_unidad_id = (int)($_GET['unidad_id'] ?? 0);

$st2 = $pdo->prepare("SELECT unidad_id, nombre FROM org_unidades WHERE empresa_id = :eid AND estatus = 1 ORDER BY nombre");
$st2->execute([':eid'=>$empresa_id]);
$unidades_combo = $st2->fetchAll(PDO::FETCH_ASSOC);

$where = ["p.empresa_id = :eid"];
$params = [':eid'=>$empresa_id];

if ($f_estatus !== '' && in_array($f_estatus, ['0','1'], true)) {
    $where[] = "p.estatus = :estatus";
    $params[':estatus'] = (int)$f_estatus;
}
if ($f_unidad_id > 0) {
    $where[] = "p.unidad_id = :uid";
    $params[':uid'] = $f_unidad_id;
}
if ($f_q !== '') {
    $where[] = "(p.nombre LIKE :q1 OR p.clave LIKE :q2 OR p.nivel LIKE :q3 OR u.nombre LIKE :q4)";
    $q = '%' . $f_q . '%';
    $params[':q1'] = $q;
    $params[':q2'] = $q;
    $params[':q3'] = $q;
    $params[':q4'] = $q;
}


$sql = "
  SELECT p.puesto_id, p.unidad_id, p.nombre, p.clave, p.nivel, p.puntos_hay, p.estatus, u.nombre AS unidad_nombre
    FROM org_puestos p
    LEFT JOIN org_unidades u ON u.unidad_id = p.unidad_id
   WHERE " . implode(' AND ', $where) . "
   ORDER BY p.estatus DESC, p.nombre ASC
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Administración de Puestos';
$active_menu = 'admin_org_puestos';
$extra_css = [
  'global_assets/css/icons/icomoon/styles.min.css',
  'global_assets/css/plugins/tables/datatables/datatables.min.css',
  'global_assets/css/plugins/forms/selects/select2.min.css',
];
$extra_js = [
  'global_assets/js/plugins/tables/datatables/datatables.min.js',
  'global_assets/js/plugins/forms/selects/select2.min.js',
];

require_once __DIR__ . '/../includes/layout/head.php';
require_once __DIR__ . '/../includes/layout/navbar.php';
require_once __DIR__ . '/../includes/layout/sidebar.php';
require_once __DIR__ . '/../includes/layout/content_open.php';
require_once __DIR__ . '/../includes/export_excel.php';

if (!empty($_GET['export']) && $_GET['export'] == '1') {
    $headers = ['ID', 'Unidad', 'Puesto', 'Clave', 'Nivel', 'Puntos Hay', 'Estatus'];

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            $r['puesto_id'],
            $r['unidad_nombre'] ?? '',
            $r['nombre'] ?? '',
            $r['clave'] ?? '',
            $r['nivel'] ?? '',
            $r['puntos_hay'] !== null ? $r['puntos_hay'] : '',
            ((int)$r['estatus'] === 1) ? 'Activo' : 'Inactivo',
        ];
    }

    export_xlsx('puestos.xlsx', $headers, $data);
}
?>

<div class="page-header page-header-light">
  <div class="page-header-content header-elements-lg-inline">
    <div class="page-title d-flex">
      <h4><span class="font-weight-semibold"><?php echo h($page_title); ?></span></h4>
    </div>
  </div>
</div>

<div class="content">
  <?php if ($flash): ?>
    <div class="alert alert-<?php echo h($flash_type); ?> alert-styled-left alert-dismissible">
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      <?php echo h($flash); ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header header-elements-inline"><h5 class="card-title">Filtros</h5></div>
    <div class="card-body">
      <form method="get" class="row">
        <div class="col-md-5">
          <label>Búsqueda</label>
          <input type="text" class="form-control" name="q" value="<?php echo h($f_q); ?>" placeholder="Puesto, clave, nivel o unidad">
        </div>
        <div class="col-md-4">
          <label>Unidad</label>
          <select class="form-control" name="unidad_id">
            <option value="0">Todas</option>
            <?php foreach ($unidades_combo as $u): ?>
              <option value="<?php echo (int)$u['unidad_id']; ?>" <?php echo ((int)$u['unidad_id'] === $f_unidad_id) ? 'selected' : ''; ?>>
                <?php echo h($u['nombre']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label>Estatus</label>
          <select class="form-control" name="estatus">
            <option value="" <?php echo $f_estatus === '' ? 'selected' : ''; ?>>Todos</option>
            <option value="1" <?php echo $f_estatus === '1' ? 'selected' : ''; ?>>Activo</option>
            <option value="0" <?php echo $f_estatus === '0' ? 'selected' : ''; ?>>Inactivo</option>
          </select>
        </div>
        <div class="col-md-12 mt-3">
          <button class="btn btn-primary" type="submit"><i class="icon-search4 mr-2"></i>Aplicar</button>
          <a class="btn btn-light ml-2" href="admin_org_puestos.php">Limpiar</a>
          <a class="btn btn-success ml-2" href="admin_org_puestos.php?export=1&q=<?php echo urlencode($f_q); ?>&unidad_id=<?php echo (int)$f_unidad_id; ?>&estatus=<?php echo urlencode($f_estatus); ?>">Exportar Excel</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header header-elements-inline">
      <h5 class="card-title">Puestos</h5>
      <div class="header-elements"><span class="badge badge-info"><?php echo count($rows); ?> registros</span></div>
    </div>

    <div class="card-body">
      <button class="btn btn-success" type="button" data-toggle="modal" data-target="#modal_puesto" onclick="openPuestoCreate()">
        <i class="icon-plus2 mr-2"></i>Nuevo puesto
      </button>
    </div>

    <div class="table-responsive">
      <table class="table" id="tabla_puestos">
        <thead>
          <tr>
            <th>ID</th>
            <th>Unidad</th>
            <th>Puesto</th>
            <th>Clave</th>
            <th>Nivel</th>
            <th>Puntos Hay</th>
            <th>Estatus</th>
            <th style="width:200px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['puesto_id']; ?></td>
              <td><?php echo h($r['unidad_nombre'] ?? ''); ?></td>
              <td><?php echo h($r['nombre']); ?></td>
              <td><?php echo h($r['clave'] ?? ''); ?></td>
              <td><?php echo h($r['nivel'] ?? ''); ?></td>
              <td><?php echo $r['puntos_hay'] !== null ? (int)$r['puntos_hay'] : ''; ?></td>
              <td><?php echo ((int)$r['estatus']===1) ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-secondary">Inactivo</span>'; ?></td>
              <td>
                <div class="d-flex">
                  <button type="button" class="btn btn-outline-primary btn-sm mr-1"
                          data-toggle="modal" data-target="#modal_puesto"
                          onclick='openPuestoEdit(<?php echo json_encode($r, JSON_UNESCAPED_UNICODE); ?>)'>
                    <i class="icon-pencil7"></i>
                  </button>
                  <form method="post" class="mr-1">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="puesto_id" value="<?php echo (int)$r['puesto_id']; ?>">
                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Activar / inactivar">
                      <i class="icon-sync"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="modal_puesto" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal_puesto_title">Puesto</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="puesto_id" id="puesto_id" value="0">

        <div class="row">
          <div class="col-md-6">
            <label>Unidad</label>
            <select class="form-control" name="unidad_id" id="unidad_id">
              <option value="">(Sin unidad)</option>
              <?php foreach ($unidades_combo as $u): ?>
                <option value="<?php echo (int)$u['unidad_id']; ?>"><?php echo h($u['nombre']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label>Puesto *</label>
            <input type="text" class="form-control" name="nombre" id="nombre" required>
          </div>

          <div class="col-md-4 mt-3">
            <label>Clave</label>
            <input type="text" class="form-control" name="clave" id="clave">
          </div>

          <div class="col-md-4 mt-3">
            <label>Nivel</label>
            <input type="text" class="form-control" name="nivel" id="nivel" placeholder="Ej. Jefe, Coordinador, Analista">
          </div>

          <div class="col-md-4 mt-3">
            <label>Puntos Hay</label>
            <input type="number" class="form-control" name="puntos_hay" id="puntos_hay" min="0">
          </div>

          <div class="col-md-4 mt-3">
            <label>Estatus</label>
            <select class="form-control" name="estatus" id="estatus">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" type="button" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

<?php
require_once __DIR__ . '/../includes/layout/footer.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
?>

<script>
function openPuestoCreate(){
  $('#modal_puesto_title').text('Nuevo puesto');
  $('#puesto_id').val('0');
  $('#unidad_id').val('');
  $('#nombre').val('');
  $('#clave').val('');
  $('#nivel').val('');
  $('#puntos_hay').val('');
  $('#estatus').val('1');
}
function openPuestoEdit(r){
  $('#modal_puesto_title').text('Editar puesto');
  $('#puesto_id').val(r.puesto_id || 0);
  $('#unidad_id').val(r.unidad_id ? String(r.unidad_id) : '');
  $('#nombre').val(r.nombre || '');
  $('#clave').val(r.clave || '');
  $('#nivel').val(r.nivel || '');
  $('#puntos_hay').val(r.puntos_hay !== null ? String(r.puntos_hay) : '');
  $('#estatus').val(String(r.estatus || 0));
}
$(function(){
  if ($.fn.DataTable) {
    $('#tabla_puestos').DataTable({
      pageLength: 25,
      order: [],
      language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_',
        info: 'Mostrando _START_ a _END_ de _TOTAL_',
        paginate: { previous: 'Anterior', next: 'Siguiente' },
        zeroRecords: 'No se encontraron registros',
        infoEmpty: 'Sin registros'
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout/scripts.php'; ?>
