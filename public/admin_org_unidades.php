<?php
// public/admin_org_unidades.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/conexion.php';

require_login();
require_empresa();
require_password_change_redirect();
require_perm('organizacion.admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$empresa_id = (int)$_SESSION['empresa_id'];

// -------------------------
// CSRF token (simple)
// -------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

// -------------------------
// Helpers
// -------------------------
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

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

function unidad_exists_same_empresa($unidad_id, $empresa_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT unidad_id FROM org_unidades WHERE unidad_id = :id AND empresa_id = :eid LIMIT 1");
    $stmt->execute([':id' => (int)$unidad_id, ':eid' => (int)$empresa_id]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

// -------------------------
// Handle POST actions
// -------------------------
$flash = null;
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    if (!hash_equals($csrf_token, $post_token)) {
        $flash = 'Solicitud inválida (token).';
        $flash_type = 'danger';
    } else {
        $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

        try {
            $pdo->beginTransaction();

            if ($action === 'save') {
                $unidad_id = isset($_POST['unidad_id']) ? (int)$_POST['unidad_id'] : 0;
                $nombre = trim((string)($_POST['nombre'] ?? ''));
                $clave = trim((string)($_POST['clave'] ?? ''));
                $estatus = isset($_POST['estatus']) ? (int)$_POST['estatus'] : 1;
                $unidad_padre_id = isset($_POST['unidad_padre_id']) && $_POST['unidad_padre_id'] !== '' ? (int)$_POST['unidad_padre_id'] : null;

                if ($nombre === '') {
                    throw new RuntimeException('El nombre es obligatorio.');
                }
                if (!in_array($estatus, [0,1], true)) {
                    $estatus = 1;
                }

                if ($unidad_padre_id !== null) {
                    if ($unidad_id > 0 && $unidad_padre_id === $unidad_id) {
                        throw new RuntimeException('La unidad padre no puede ser la misma unidad.');
                    }
                    if (!unidad_exists_same_empresa($unidad_padre_id, $empresa_id)) {
                        throw new RuntimeException('Unidad padre inválida para esta empresa.');
                    }
                }

                if ($unidad_id > 0) {
                    // Update
                    $stmt = $pdo->prepare("
                        UPDATE org_unidades
                           SET nombre = :nombre,
                               clave = :clave,
                               unidad_padre_id = :padre,
                               estatus = :estatus
                         WHERE unidad_id = :id
                           AND empresa_id = :eid
                         LIMIT 1
                    ");
                    $stmt->execute([
                        ':nombre' => $nombre,
                        ':clave' => ($clave !== '' ? $clave : null),
                        ':padre' => $unidad_padre_id,
                        ':estatus' => $estatus,
                        ':id' => $unidad_id,
                        ':eid' => $empresa_id,
                    ]);
                    bitacora('admin_org_unidades', 'update', ['unidad_id' => $unidad_id]);
                    $flash = 'Unidad actualizada.';
                } else {
                    // Insert
                    $stmt = $pdo->prepare("
                        INSERT INTO org_unidades (empresa_id, nombre, clave, unidad_padre_id, estatus)
                        VALUES (:eid, :nombre, :clave, :padre, :estatus)
                    ");
                    $stmt->execute([
                        ':eid' => $empresa_id,
                        ':nombre' => $nombre,
                        ':clave' => ($clave !== '' ? $clave : null),
                        ':padre' => $unidad_padre_id,
                        ':estatus' => $estatus,
                    ]);
                    $new_id = (int)$pdo->lastInsertId();
                    bitacora('admin_org_unidades', 'insert', ['unidad_id' => $new_id]);
                    $flash = 'Unidad creada.';
                }

            } elseif ($action === 'toggle_status') {
                $unidad_id = isset($_POST['unidad_id']) ? (int)$_POST['unidad_id'] : 0;

                $stmt = $pdo->prepare("SELECT estatus FROM org_unidades WHERE unidad_id = :id AND empresa_id = :eid LIMIT 1");
                $stmt->execute([':id' => $unidad_id, ':eid' => $empresa_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new RuntimeException('Unidad no encontrada.');

                $nuevo = ((int)$row['estatus'] === 1) ? 0 : 1;

                $upd = $pdo->prepare("UPDATE org_unidades SET estatus = :n WHERE unidad_id = :id AND empresa_id = :eid LIMIT 1");
                $upd->execute([':n' => $nuevo, ':id' => $unidad_id, ':eid' => $empresa_id]);

                bitacora('admin_org_unidades', 'toggle_status', ['unidad_id' => $unidad_id, 'nuevo' => $nuevo]);
                $flash = 'Estatus actualizado.';
            } else {
                $flash = 'Acción no reconocida.';
                $flash_type = 'warning';
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $flash = 'Error: ' . $e->getMessage();
            $flash_type = 'danger';
        }
    }
}

// -------------------------
// Filters
// -------------------------
$f_q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$f_estatus = isset($_GET['estatus']) ? trim((string)$_GET['estatus']) : '';

$where = ["u.empresa_id = :eid"];
$params = [':eid' => $empresa_id];

if ($f_estatus !== '' && in_array($f_estatus, ['0','1'], true)) {
    $where[] = "u.estatus = :estatus";
    $params[':estatus'] = (int)$f_estatus;
}

if ($f_q !== '') {
    $where[] = "(u.nombre LIKE :q1 OR u.clave LIKE :q2)";
    $q = '%' . $f_q . '%';
    $params[':q1'] = $q;
    $params[':q2'] = $q;
}


$where_sql = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT
      u.unidad_id,
      u.nombre,
      u.clave,
      u.unidad_padre_id,
      u.estatus,
      up.nombre AS padre_nombre
    FROM org_unidades u
    LEFT JOIN org_unidades up ON up.unidad_id = u.unidad_padre_id
    $where_sql
    ORDER BY u.estatus DESC, u.nombre ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Para combos (padre)
$stmt2 = $pdo->prepare("SELECT unidad_id, nombre FROM org_unidades WHERE empresa_id = :eid AND estatus = 1 ORDER BY nombre");
$stmt2->execute([':eid' => $empresa_id]);
$unidades_combo = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// UI vars
$page_title = 'Administración de Unidades (Departamentos)';
$active_menu = 'admin_org_unidades';
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

    $headers = ['ID', 'Nombre', 'Clave', 'Unidad padre', 'Estatus'];

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            $r['unidad_id'],
            $r['nombre'] ?? '',
            $r['clave'] ?? '',
            $r['padre_nombre'] ?? '',
            ((int)$r['estatus'] === 1) ? 'Activo' : 'Inactivo',
        ];
    }

    export_xlsx('unidades.xlsx', $headers, $data);
}

?>

<div class="page-header page-header-light">
    <div class="page-header-content header-elements-lg-inline">
        <div class="page-title d-flex">
            <h4><span class="font-weight-semibold"><?php echo h($page_title); ?></span></h4>
        </div>
    </div>
    <div class="breadcrumb-line breadcrumb-line-light header-elements-lg-inline">
        <div class="d-flex">
            <div class="breadcrumb">
                <a href="<?php echo ASSET_BASE; ?>public/dashboard.php" class="breadcrumb-item">
                    <i class="icon-home2 mr-2"></i> Inicio
                </a>
                <span class="breadcrumb-item">Administración</span>
                <span class="breadcrumb-item active">Unidades</span>
            </div>
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
        <div class="card-header header-elements-inline">
            <h5 class="card-title">Filtros</h5>
        </div>
        <div class="card-body">
            <form method="get" action="" class="row">
                <div class="col-md-6">
                    <label>Búsqueda</label>
                    <input type="text" class="form-control" name="q" value="<?php echo h($f_q); ?>" placeholder="Nombre o clave">
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
                    <button type="submit" class="btn btn-primary">
                        <i class="icon-search4 mr-2"></i> Aplicar
                    </button>
                    <a href="admin_org_unidades.php" class="btn btn-light ml-2">Limpiar</a>
                    <a class="btn btn-success ml-2" href="admin_org_unidades.php?export=1&q=<?php echo urlencode($f_q); ?>&estatus=<?php echo urlencode($f_estatus); ?>">Exportar Excel</a>


                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header header-elements-inline">
            <h5 class="card-title">Unidades</h5>
            <div class="header-elements">
                <span class="badge badge-info"><?php echo count($rows); ?> registros</span>
            </div>
        </div>

        <div class="card-body">
            <button class="btn btn-success" type="button" data-toggle="modal" data-target="#modal_unidad" onclick="openUnidadCreate()">
                <i class="icon-plus2 mr-2"></i>Nueva unidad
            </button>
        </div>

        <div class="table-responsive">
            <table class="table" id="tabla_unidades">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Clave</th>
                        <th>Padre</th>
                        <th>Estatus</th>
                        <th style="width: 200px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo (int)$r['unidad_id']; ?></td>
                            <td><?php echo h($r['nombre']); ?></td>
                            <td><?php echo h($r['clave'] ?? ''); ?></td>
                            <td><?php echo h($r['padre_nombre'] ?? ''); ?></td>
                            <td>
                                <?php if ((int)$r['estatus'] === 1): ?>
                                    <span class="badge badge-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex">
                                    <button type="button" class="btn btn-outline-primary btn-sm mr-1"
                                            data-toggle="modal" data-target="#modal_unidad"
                                            onclick='openUnidadEdit(<?php echo json_encode($r, JSON_UNESCAPED_UNICODE); ?>)'>
                                        <i class="icon-pencil7"></i>
                                    </button>

                                    <form method="post" action="" class="mr-1">
                                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="unidad_id" value="<?php echo (int)$r['unidad_id']; ?>">
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

<!-- Modal: Alta/Edición -->
<div class="modal fade" id="modal_unidad" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form method="post" action="" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal_unidad_title">Unidad</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="unidad_id" id="unidad_id" value="0">

        <div class="row">
          <div class="col-md-8">
            <label>Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="nombre" required>
          </div>
          <div class="col-md-4">
            <label>Clave</label>
            <input type="text" class="form-control" name="clave" id="clave">
          </div>

          <div class="col-md-8 mt-3">
            <label>Unidad padre</label>
            <select class="form-control" name="unidad_padre_id" id="unidad_padre_id">
              <option value="">(Sin padre)</option>
              <?php foreach ($unidades_combo as $u): ?>
                <option value="<?php echo (int)$u['unidad_id']; ?>"><?php echo h($u['nombre']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4 mt-3">
            <label>Estatus</label>
            <select class="form-control" name="estatus" id="estatus">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
          </div>
        </div>

        <small class="text-muted d-block mt-3">
          Recomendación: no elimines unidades. Usa Inactivo para conservar integridad histórica.
        </small>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<?php
require_once __DIR__ . '/../includes/layout/footer.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
?>

<script>
function openUnidadCreate() {
  $('#modal_unidad_title').text('Nueva unidad');
  $('#unidad_id').val('0');
  $('#nombre').val('');
  $('#clave').val('');
  $('#unidad_padre_id').val('');
  $('#estatus').val('1');
}

function openUnidadEdit(r) {
  $('#modal_unidad_title').text('Editar unidad');
  $('#unidad_id').val(r.unidad_id || 0);
  $('#nombre').val(r.nombre || '');
  $('#clave').val(r.clave || '');
  $('#unidad_padre_id').val(r.unidad_padre_id ? String(r.unidad_padre_id) : '');
  $('#estatus').val(String(r.estatus || 0));
}

$(function(){
  if ($.fn.DataTable) {
    $('#tabla_unidades').DataTable({
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
