<?php
// public/admin_org_centros_trabajo.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/conexion.php';

require_login();
require_empresa();
require_password_change_redirect();
require_demograficos_redirect();
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
                $is_edit = (int)($_POST['is_edit'] ?? 0);

                $centro_trabajo_id = (int)($_POST['centro_trabajo_id'] ?? 0);
                $nombre = trim((string)($_POST['nombre'] ?? ''));
                $clave = trim((string)($_POST['clave'] ?? ''));
                $estatus = (int)($_POST['estatus'] ?? 1);

                if ($centro_trabajo_id <= 0) throw new RuntimeException('El ID de centro de trabajo es obligatorio.');
                if ($nombre === '') throw new RuntimeException('El nombre es obligatorio.');
                if (!in_array($estatus, [0,1], true)) $estatus = 1;

                if ($is_edit === 1) {
                    $upd = $pdo->prepare("
                        UPDATE org_centros_trabajo
                           SET nombre = :nombre,
                               clave = :clave,
                               estatus = :estatus
                         WHERE empresa_id = :eid AND centro_trabajo_id = :ctid
                         LIMIT 1
                    ");
                    $upd->execute([
                        ':nombre'=>$nombre,
                        ':clave'=>($clave!==''?$clave:null),
                        ':estatus'=>$estatus,
                        ':eid'=>$empresa_id,
                        ':ctid'=>$centro_trabajo_id,
                    ]);
                    bitacora('admin_org_centros_trabajo','update',['centro_trabajo_id'=>$centro_trabajo_id]);
                    $flash = 'Centro de trabajo actualizado.';
                } else {
                    // Insert (si ya existe, mejor actualizar para que sea “upsert” manual)
                    $st = $pdo->prepare("SELECT centro_trabajo_id FROM org_centros_trabajo WHERE empresa_id = :eid AND centro_trabajo_id = :ctid LIMIT 1");
                    $st->execute([':eid'=>$empresa_id, ':ctid'=>$centro_trabajo_id]);
                    $exists = (bool)$st->fetch(PDO::FETCH_ASSOC);

                    if ($exists) {
                        $upd = $pdo->prepare("
                            UPDATE org_centros_trabajo
                               SET nombre = :nombre,
                                   clave = :clave,
                                   estatus = :estatus
                             WHERE empresa_id = :eid AND centro_trabajo_id = :ctid
                             LIMIT 1
                        ");
                        $upd->execute([
                            ':nombre'=>$nombre,
                            ':clave'=>($clave!==''?$clave:null),
                            ':estatus'=>$estatus,
                            ':eid'=>$empresa_id,
                            ':ctid'=>$centro_trabajo_id,
                        ]);
                        bitacora('admin_org_centros_trabajo','upsert',['centro_trabajo_id'=>$centro_trabajo_id]);
                        $flash = 'Centro de trabajo actualizado (ya existía).';
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO org_centros_trabajo (empresa_id, centro_trabajo_id, nombre, clave, estatus)
                            VALUES (:eid, :ctid, :nombre, :clave, :estatus)
                        ");
                        $ins->execute([
                            ':eid'=>$empresa_id,
                            ':ctid'=>$centro_trabajo_id,
                            ':nombre'=>$nombre,
                            ':clave'=>($clave!==''?$clave:null),
                            ':estatus'=>$estatus,
                        ]);
                        bitacora('admin_org_centros_trabajo','insert',['centro_trabajo_id'=>$centro_trabajo_id]);
                        $flash = 'Centro de trabajo creado.';
                    }
                }

            } elseif ($action === 'toggle_status') {
                $centro_trabajo_id = (int)($_POST['centro_trabajo_id'] ?? 0);

                $st = $pdo->prepare("SELECT estatus FROM org_centros_trabajo WHERE empresa_id = :eid AND centro_trabajo_id = :ctid LIMIT 1");
                $st->execute([':eid'=>$empresa_id, ':ctid'=>$centro_trabajo_id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new RuntimeException('Centro de trabajo no encontrado.');

                $nuevo = ((int)$row['estatus'] === 1) ? 0 : 1;

                $upd = $pdo->prepare("UPDATE org_centros_trabajo SET estatus = :n WHERE empresa_id = :eid AND centro_trabajo_id = :ctid LIMIT 1");
                $upd->execute([':n'=>$nuevo, ':eid'=>$empresa_id, ':ctid'=>$centro_trabajo_id]);

                bitacora('admin_org_centros_trabajo','toggle_status',['centro_trabajo_id'=>$centro_trabajo_id,'nuevo'=>$nuevo]);
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

$where = ["empresa_id = :eid"];
$params = [':eid'=>$empresa_id];

if ($f_estatus !== '' && in_array($f_estatus, ['0','1'], true)) {
    $where[] = "estatus = :estatus";
    $params[':estatus'] = (int)$f_estatus;
}

if ($f_q !== '') {
    $where[] = "(CAST(centro_trabajo_id AS CHAR) LIKE :q1 OR nombre LIKE :q2 OR clave LIKE :q3)";
    $q = '%' . $f_q . '%';
    $params[':q1'] = $q;
    $params[':q2'] = $q;
    $params[':q3'] = $q;
}


$sql = "SELECT empresa_id, centro_trabajo_id, nombre, clave, estatus
          FROM org_centros_trabajo
         WHERE " . implode(' AND ', $where) . "
         ORDER BY estatus DESC, centro_trabajo_id ASC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Administración de Centros de Trabajo';
$active_menu = 'admin_org_centros_trabajo';
$extra_css = [
  'global_assets/css/icons/icomoon/styles.min.css',
  'global_assets/css/plugins/tables/datatables/datatables.min.css',
];
$extra_js = [
  'global_assets/js/plugins/tables/datatables/datatables.min.js',
];

require_once __DIR__ . '/../includes/layout/head.php';
require_once __DIR__ . '/../includes/layout/navbar.php';
require_once __DIR__ . '/../includes/layout/sidebar.php';
require_once __DIR__ . '/../includes/layout/content_open.php';
require_once __DIR__ . '/../includes/export_excel.php';

if (!empty($_GET['export']) && $_GET['export'] == '1') {
    $headers = ['ID', 'Nombre', 'Clave', 'Estatus'];

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            $r['centro_trabajo_id'],
            $r['nombre'] ?? '',
            $r['clave'] ?? '',
            ((int)$r['estatus'] === 1) ? 'Activo' : 'Inactivo',
        ];
    }

    export_xlsx('centros_trabajo.xlsx', $headers, $data);
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
        <div class="col-md-6">
          <label>Búsqueda</label>
          <input type="text" class="form-control" name="q" value="<?php echo h($f_q); ?>" placeholder="ID, nombre o clave">
        </div>
        <div class="col-md-3">
          <label>Estatus</label>
          <select class="form-control" name="estatus">
            <option value="" <?php echo $f_estatus === '' ? 'selected' : ''; ?>>Todos</option>
            <option value="1" <?php echo $f_estatus === '1' ? 'selected' : ''; ?>>Activo</option>
            <option value="0" <?php echo $f_estatus === '0' ? 'selected' : ''; ?>>Inactivo</option>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-primary" type="submit"><i class="icon-search4 mr-2"></i>Aplicar</button>
          <a class="btn btn-light ml-2" href="admin_org_centros_trabajo.php">Limpiar</a>
          <a class="btn btn-success ml-2" href="admin_org_centros_trabajo.php?export=1&q=<?php echo urlencode($f_q); ?>&estatus=<?php echo urlencode($f_estatus); ?>">Exportar Excel</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header header-elements-inline">
      <h5 class="card-title">Centros de Trabajo</h5>
      <div class="header-elements"><span class="badge badge-info"><?php echo count($rows); ?> registros</span></div>
    </div>

    <div class="card-body">
      <button class="btn btn-success" type="button" data-toggle="modal" data-target="#modal_ct" onclick="openCTCreate()">
        <i class="icon-plus2 mr-2"></i>Nuevo centro
      </button>
      <small class="text-muted d-block mt-2">Si tu nómina controla el ID, no lo edites después de creado.</small>
    </div>

    <div class="table-responsive">
      <table class="table" id="tabla_ct">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Clave</th>
            <th>Estatus</th>
            <th style="width:200px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['centro_trabajo_id']; ?></td>
              <td><?php echo h($r['nombre']); ?></td>
              <td><?php echo h($r['clave'] ?? ''); ?></td>
              <td><?php echo ((int)$r['estatus']===1) ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-secondary">Inactivo</span>'; ?></td>
              <td>
                <div class="d-flex">
                  <button type="button" class="btn btn-outline-primary btn-sm mr-1"
                          data-toggle="modal" data-target="#modal_ct"
                          onclick='openCTEdit(<?php echo json_encode($r, JSON_UNESCAPED_UNICODE); ?>)'>
                    <i class="icon-pencil7"></i>
                  </button>
                  <form method="post" class="mr-1">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="centro_trabajo_id" value="<?php echo (int)$r['centro_trabajo_id']; ?>">
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

<div class="modal fade" id="modal_ct" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal_ct_title">Centro de trabajo</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="is_edit" id="is_edit" value="0">

        <div class="row">
          <div class="col-md-4">
            <label>ID centro *</label>
            <input type="number" class="form-control" name="centro_trabajo_id" id="centro_trabajo_id" min="1" required>
          </div>
          <div class="col-md-8">
            <label>Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="nombre" required>
          </div>

          <div class="col-md-6 mt-3">
            <label>Clave</label>
            <input type="text" class="form-control" name="clave" id="clave">
          </div>

          <div class="col-md-6 mt-3">
            <label>Estatus</label>
            <select class="form-control" name="estatus" id="estatus">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
          </div>
        </div>

        <small class="text-muted d-block mt-3">
          Para edición, el ID queda bloqueado (evita inconsistencias con nómina/importación).
        </small>
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
function openCTCreate(){
  $('#modal_ct_title').text('Nuevo centro de trabajo');
  $('#is_edit').val('0');
  $('#centro_trabajo_id').prop('readonly', false).val('');
  $('#nombre').val('');
  $('#clave').val('');
  $('#estatus').val('1');
}
function openCTEdit(r){
  $('#modal_ct_title').text('Editar centro de trabajo');
  $('#is_edit').val('1');
  $('#centro_trabajo_id').val(r.centro_trabajo_id || '').prop('readonly', true);
  $('#nombre').val(r.nombre || '');
  $('#clave').val(r.clave || '');
  $('#estatus').val(String(r.estatus || 0));
}
$(function(){
  if ($.fn.DataTable) {
    $('#tabla_ct').DataTable({
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
