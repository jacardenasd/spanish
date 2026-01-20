<?php
// public/admin_org_adscripciones.php
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
                $adscripcion_id = (int)($_POST['adscripcion_id'] ?? 0);
                $unidad_id = (int)($_POST['unidad_id'] ?? 0);
                $nombre = trim((string)($_POST['nombre'] ?? ''));
                $clave = trim((string)($_POST['clave'] ?? ''));
                $estatus = (int)($_POST['estatus'] ?? 1);
                if ($nombre === '') throw new RuntimeException('El nombre es obligatorio.');
                if ($unidad_id <= 0) throw new RuntimeException('La unidad (departamento) es obligatoria.');
                if (!in_array($estatus, [0,1], true)) $estatus = 1;

                // validar unidad en empresa
                $st = $pdo->prepare("SELECT unidad_id FROM org_unidades WHERE unidad_id = :uid AND empresa_id = :eid LIMIT 1");
                $st->execute([':uid'=>$unidad_id, ':eid'=>$empresa_id]);
                if (!$st->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('Unidad inválida para esta empresa.');

                if ($adscripcion_id > 0) {
                    $upd = $pdo->prepare("
                        UPDATE org_adscripciones
                           SET unidad_id = :unidad_id,
                               nombre = :nombre,
                               clave = :clave,
                               estatus = :estatus
                         WHERE adscripcion_id = :id AND empresa_id = :eid
                         LIMIT 1
                    ");
                    $upd->execute([
                        ':unidad_id'=>$unidad_id,
                        ':nombre'=>$nombre,
                        ':clave'=>($clave!==''?$clave:null),
                        ':estatus'=>$estatus,
                        ':id'=>$adscripcion_id,
                        ':eid'=>$empresa_id,
                    ]);
                    bitacora('admin_org_adscripciones','update',['adscripcion_id'=>$adscripcion_id]);
                    $flash = 'Subárea actualizada.';
                } else {
                    $ins = $pdo->prepare("
                        INSERT INTO org_adscripciones (empresa_id, unidad_id, nombre, clave, estatus)
                        VALUES (:eid, :unidad_id, :nombre, :clave, :estatus)
                    ");
                    $ins->execute([
                        ':eid'=>$empresa_id,
                        ':unidad_id'=>$unidad_id,
                        ':nombre'=>$nombre,
                        ':clave'=>($clave!==''?$clave:null),
                        ':estatus'=>$estatus,
                    ]);
                    $new_id = (int)$pdo->lastInsertId();
                    bitacora('admin_org_adscripciones','insert',['adscripcion_id'=>$new_id]);
                    $flash = 'Subárea creada.';
                }

            } elseif ($action === 'toggle_status') {
                $adscripcion_id = (int)($_POST['adscripcion_id'] ?? 0);
                $st = $pdo->prepare("SELECT estatus FROM org_adscripciones WHERE adscripcion_id = :id AND empresa_id = :eid LIMIT 1");
                $st->execute([':id'=>$adscripcion_id, ':eid'=>$empresa_id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new RuntimeException('Subárea no encontrada.');
                $nuevo = ((int)$row['estatus'] === 1) ? 0 : 1;

                $upd = $pdo->prepare("UPDATE org_adscripciones SET estatus = :n WHERE adscripcion_id = :id AND empresa_id = :eid LIMIT 1");
                $upd->execute([':n'=>$nuevo, ':id'=>$adscripcion_id, ':eid'=>$empresa_id]);
                bitacora('admin_org_adscripciones','toggle_status',['adscripcion_id'=>$adscripcion_id,'nuevo'=>$nuevo]);
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

$where = ["a.empresa_id = :eid"];
$params = [':eid'=>$empresa_id];

if ($f_estatus !== '' && in_array($f_estatus, ['0','1'], true)) {
    $where[] = "a.estatus = :estatus";
    $params[':estatus'] = (int)$f_estatus;
}
if ($f_unidad_id > 0) {
    $where[] = "a.unidad_id = :uid";
    $params[':uid'] = $f_unidad_id;
}
if ($f_q !== '') {
    $where[] = "(a.nombre LIKE :q1 OR a.clave LIKE :q2 OR u.nombre LIKE :q3)";
    $q = '%' . $f_q . '%';
    $params[':q1'] = $q;
    $params[':q2'] = $q;
    $params[':q3'] = $q;
}

$sql = "
  SELECT a.adscripcion_id, a.unidad_id, a.nombre, a.clave, a.estatus, u.nombre AS unidad_nombre
    FROM org_adscripciones a
    JOIN org_unidades u ON u.unidad_id = a.unidad_id
   WHERE " . implode(' AND ', $where) . "
   ORDER BY a.estatus DESC, u.nombre ASC, a.nombre ASC
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// combos
$st2 = $pdo->prepare("SELECT unidad_id, nombre FROM org_unidades WHERE empresa_id = :eid AND estatus = 1 ORDER BY nombre");
$st2->execute([':eid'=>$empresa_id]);
$unidades_combo = $st2->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Administración de Adscripciones (Subáreas)';
$active_menu = 'admin_org_adscripciones';
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
    $headers = ['ID', 'Unidad', 'Subárea', 'Clave', 'Estatus'];

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            $r['adscripcion_id'],
            $r['unidad_nombre'] ?? '',
            $r['nombre'] ?? '',
            $r['clave'] ?? '',
            ((int)$r['estatus'] === 1) ? 'Activo' : 'Inactivo',
        ];
    }

    export_xlsx('adscripciones.xlsx', $headers, $data);
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
          <input type="text" class="form-control" name="q" value="<?php echo h($f_q); ?>" placeholder="Subárea, clave o unidad">
        </div>
        <div class="col-md-4">
          <label>Unidad (Departamento)</label>
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
          <a class="btn btn-light ml-2" href="admin_org_adscripciones.php">Limpiar</a>
          <a class="btn btn-success ml-2" href="admin_org_adscripciones.php?export=1&q=<?php echo urlencode($f_q); ?>&unidad_id=<?php echo (int)$f_unidad_id; ?>&estatus=<?php echo urlencode($f_estatus); ?>">Exportar Excel</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header header-elements-inline">
      <h5 class="card-title">Subáreas</h5>
      <div class="header-elements"><span class="badge badge-info"><?php echo count($rows); ?> registros</span></div>
    </div>

    <div class="card-body">
      <button class="btn btn-success" type="button" data-toggle="modal" data-target="#modal_ads" onclick="openAdsCreate()">
        <i class="icon-plus2 mr-2"></i>Nueva subárea
      </button>
    </div>

    <div class="table-responsive">
      <table class="table" id="tabla_ads">
        <thead>
          <tr>
            <th>ID</th>
            <th>Unidad</th>
            <th>Subárea</th>
            <th>Clave</th>
            <th>Estatus</th>
            <th style="width: 200px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['adscripcion_id']; ?></td>
              <td><?php echo h($r['unidad_nombre']); ?></td>
              <td><?php echo h($r['nombre']); ?></td>
              <td><?php echo h($r['clave'] ?? ''); ?></td>
              <td><?php echo ((int)$r['estatus']===1) ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-secondary">Inactivo</span>'; ?></td>
              <td>
                <div class="d-flex">
                  <button type="button" class="btn btn-outline-primary btn-sm mr-1"
                          data-toggle="modal" data-target="#modal_ads"
                          onclick='openAdsEdit(<?php echo json_encode($r, JSON_UNESCAPED_UNICODE); ?>)'>
                    <i class="icon-pencil7"></i>
                  </button>
                  <form method="post" class="mr-1">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="adscripcion_id" value="<?php echo (int)$r['adscripcion_id']; ?>">
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

<div class="modal fade" id="modal_ads" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal_ads_title">Subárea</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="adscripcion_id" id="adscripcion_id" value="0">

        <div class="row">
          <div class="col-md-6">
            <label>Unidad (Departamento) *</label>
            <select class="form-control" name="unidad_id" id="unidad_id" required>
              <option value="">Seleccione</option>
              <?php foreach ($unidades_combo as $u): ?>
                <option value="<?php echo (int)$u['unidad_id']; ?>"><?php echo h($u['nombre']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label>Subárea *</label>
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
function openAdsCreate(){
  $('#modal_ads_title').text('Nueva subárea');
  $('#adscripcion_id').val('0');
  $('#unidad_id').val('');
  $('#nombre').val('');
  $('#clave').val('');
  $('#estatus').val('1');
}
function openAdsEdit(r){
  $('#modal_ads_title').text('Editar subárea');
  $('#adscripcion_id').val(r.adscripcion_id || 0);
  $('#unidad_id').val(String(r.unidad_id || ''));
  $('#nombre').val(r.nombre || '');
  $('#clave').val(r.clave || '');
  $('#estatus').val(String(r.estatus || 0));
}
$(function(){
  if ($.fn.DataTable) {
    $('#tabla_ads').DataTable({
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
