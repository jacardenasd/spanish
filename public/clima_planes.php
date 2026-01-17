<?php
// public/clima_planes.php
// SGRH - Clima Laboral - Planes de Acción
// Reglas: planes por periodo+empresa+unidad+dimensión
// Estados: pendiente, en_proceso, cumplido
// Compatible PHP 5.7 - NO usar operador ??

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/conexion.php';

require_login();
require_empresa();
require_password_change_redirect();
require_demograficos_redirect();
if (function_exists('require_perm_any')) {
  require_perm_any(['organizacion.admin', 'clima.admin']);
} else {
  if (!can('organizacion.admin') && !can('clima.admin')) {
    header('Location: sin_permiso.php');
    exit;
  }
}

if (session_status() === PHP_SESSION_NONE) session_start();

$empresa_id = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;
$usuario_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;

if ($empresa_id <= 0) {
    http_response_code(400);
    die('Empresa inválida en sesión.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$flash = null;
$flash_type = 'info';

// =======================
// SELECCIÓN DE PERIODO
// =======================
$periodo_id = isset($_GET['periodo_id']) ? (int)$_GET['periodo_id'] : 0;

$periodos_stmt = $pdo->prepare("SELECT periodo_id, anio, estatus FROM clima_periodos WHERE empresa_id=? ORDER BY anio DESC");
$periodos_stmt->execute([$empresa_id]);
$periodos = $periodos_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($periodo_id <= 0 && !empty($periodos)) {
    $periodo_id = (int)$periodos[0]['periodo_id'];
}

$periodo = null;
if ($periodo_id > 0) {
    $pstmt = $pdo->prepare("SELECT * FROM clima_periodos WHERE periodo_id=? AND empresa_id=? LIMIT 1");
    $pstmt->execute([$periodo_id, $empresa_id]);
    $periodo = $pstmt->fetch(PDO::FETCH_ASSOC);
}

// =======================
// PROCESAR ACCIONES
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf_token'], isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '')) {
            throw new RuntimeException('Solicitud inválida (CSRF).');
        }

        $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

        if ($action === 'save_plan') {
            $plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
            $pid = isset($_POST['periodo_id']) ? (int)$_POST['periodo_id'] : 0;
            $unidad_id = isset($_POST['unidad_id']) ? (int)$_POST['unidad_id'] : 0;
            $dimension_id = isset($_POST['dimension_id']) ? (int)$_POST['dimension_id'] : 0;
            $problema = isset($_POST['problema_identificado']) ? trim((string)$_POST['problema_identificado']) : '';
            $accion = isset($_POST['accion']) ? trim((string)$_POST['accion']) : '';
            $responsable = isset($_POST['responsable']) ? trim((string)$_POST['responsable']) : '';
            $fecha_compromiso = isset($_POST['fecha_compromiso']) ? trim((string)$_POST['fecha_compromiso']) : '';
            $indicador = isset($_POST['indicador']) ? trim((string)$_POST['indicador']) : '';
            $estatus = isset($_POST['estatus']) ? trim((string)$_POST['estatus']) : 'pendiente';

            if ($pid <= 0) throw new RuntimeException('Periodo inválido.');
            if ($unidad_id <= 0) throw new RuntimeException('Debe seleccionar una Dirección.');
            if ($dimension_id <= 0) throw new RuntimeException('Debe seleccionar una dimensión.');
            if ($problema === '') throw new RuntimeException('El problema identificado es obligatorio.');
            if ($accion === '') throw new RuntimeException('La acción es obligatoria.');
            if ($responsable === '') throw new RuntimeException('El responsable es obligatorio.');
            if ($fecha_compromiso === '') throw new RuntimeException('La fecha de compromiso es obligatoria.');
            if (!in_array($estatus, array('pendiente', 'en_proceso', 'cumplido'), true)) {
                throw new RuntimeException('Estatus inválido.');
            }

            if ($plan_id > 0) {
                $upd = $pdo->prepare("
                    UPDATE clima_planes
                    SET unidad_id=?, dimension_id=?, problema_identificado=?, accion=?, responsable=?, 
                        fecha_compromiso=?, indicador=?, estatus=?, updated_at=NOW()
                    WHERE plan_id=? AND periodo_id=? AND empresa_id=?
                ");
                $upd->execute([$unidad_id, $dimension_id, $problema, $accion, $responsable, $fecha_compromiso, $indicador, $estatus, $plan_id, $pid, $empresa_id]);
                $flash = 'Plan de acción actualizado correctamente.';
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO clima_planes 
                        (periodo_id, empresa_id, unidad_id, dimension_id, problema_identificado, accion, responsable, fecha_compromiso, indicador, estatus, created_by, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $ins->execute([$pid, $empresa_id, $unidad_id, $dimension_id, $problema, $accion, $responsable, $fecha_compromiso, $indicador, $estatus, $usuario_id]);
                $flash = 'Plan de acción creado correctamente.';
            }
            $flash_type = 'success';

        } elseif ($action === 'delete_plan') {
            $plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
            if ($plan_id <= 0) throw new RuntimeException('Plan inválido.');

            $del = $pdo->prepare("DELETE FROM clima_planes WHERE plan_id=? AND empresa_id=?");
            $del->execute([$plan_id, $empresa_id]);
            $flash = 'Plan de acción eliminado.';
            $flash_type = 'success';

        } else {
            $flash = 'Acción no reconocida.';
            $flash_type = 'warning';
        }

    } catch (Exception $e) {
        $flash = $e->getMessage();
        $flash_type = 'danger';
    }
}

// =======================
// CARGAR DATOS
// =======================
// Dimensiones
$dimensiones = $pdo->query("SELECT * FROM clima_dimensiones WHERE activo=1 ORDER BY orden")->fetchAll(PDO::FETCH_ASSOC);

// Unidades de la empresa
$unidades_stmt = $pdo->prepare("SELECT unidad_id, nombre FROM org_unidades WHERE empresa_id=? AND estatus=1 ORDER BY nombre");
$unidades_stmt->execute([$empresa_id]);
$unidades = $unidades_stmt->fetchAll(PDO::FETCH_ASSOC);

// Planes del periodo actual
$planes = array();
if ($periodo) {
    $planes_stmt = $pdo->prepare("
        SELECT 
            cp.*,
            u.nombre AS unidad_nombre,
            d.nombre AS dimension_nombre
        FROM clima_planes cp
        INNER JOIN org_unidades u ON u.unidad_id = cp.unidad_id
        INNER JOIN clima_dimensiones d ON d.dimension_id = cp.dimension_id
        WHERE cp.periodo_id=? AND cp.empresa_id=?
        ORDER BY cp.estatus, cp.fecha_compromiso
    ");
    $planes_stmt->execute([$periodo_id, $empresa_id]);
    $planes = $planes_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Estadísticas
$stats = array('pendiente' => 0, 'en_proceso' => 0, 'cumplido' => 0, 'total' => 0);
foreach ($planes as $p) {
    $stats['total']++;
    $est = (string)$p['estatus'];
    if (isset($stats[$est])) {
        $stats[$est]++;
    }
}

// =======================
// LAYOUT
// =======================
$page_title = 'Clima Laboral - Planes de Acción';
$active_menu = 'clima_planes';

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
?>

<div class="page-header page-header-light">
  <div class="page-header-content header-elements-md-inline">
    <div class="page-title d-flex">
      <h4><i class="icon-file-text2 mr-2"></i> <span class="font-weight-semibold">Clima Laboral</span> - Planes de Acción</h4>
    </div>
    <div class="header-elements d-none d-md-flex">
      <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalPlan" onclick="abrirPlanNuevo()">
        <i class="icon-plus2 mr-1"></i> Nuevo plan
      </button>
    </div>
  </div>
</div>

<div class="content">

  <?php if ($flash): ?>
    <div class="alert alert-<?php echo h($flash_type); ?> alert-dismissible">
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      <?php echo h($flash); ?>
    </div>
  <?php endif; ?>

  <!-- Selector de periodo -->
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Filtrar por periodo</h5>
    </div>
    <div class="card-body">
      <form method="get" class="form-inline">
        <label class="mr-2">Periodo:</label>
        <select name="periodo_id" class="form-control mr-2" onchange="this.form.submit()">
          <?php foreach ($periodos as $p): ?>
          <option value="<?php echo (int)$p['periodo_id']; ?>" <?php echo ((int)$p['periodo_id'] === $periodo_id) ? 'selected' : ''; ?>>
            <?php echo h($p['anio']); ?> (<?php echo h($p['estatus']); ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if ($periodo): ?>

  <!-- Estadísticas -->
  <div class="row">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h3 class="font-weight-bold mb-0"><?php echo (int)$stats['total']; ?></h3>
          <span class="text-muted">Total planes</span>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center bg-warning-400 text-white">
        <div class="card-body">
          <h3 class="font-weight-bold mb-0"><?php echo (int)$stats['pendiente']; ?></h3>
          <span>Pendientes</span>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center bg-info-400 text-white">
        <div class="card-body">
          <h3 class="font-weight-bold mb-0"><?php echo (int)$stats['en_proceso']; ?></h3>
          <span>En proceso</span>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center bg-success-400 text-white">
        <div class="card-body">
          <h3 class="font-weight-bold mb-0"><?php echo (int)$stats['cumplido']; ?></h3>
          <span>Cumplidos</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Listado de planes -->
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Planes de acción registrados</h5>
    </div>
    <div class="table-responsive">
      <table class="table table-hover" id="tablePlanes">
        <thead>
          <tr>
            <th style="width:60px;">ID</th>
            <th>Dirección</th>
            <th>Dimensión</th>
            <th>Problema</th>
            <th>Acción</th>
            <th>Responsable</th>
            <th>Fecha compromiso</th>
            <th>Estatus</th>
            <th style="width:120px;" class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($planes as $pl): 
            $badge_estatus = 'secondary';
            $texto_estatus = 'Pendiente';
            if ($pl['estatus'] === 'en_proceso') {
              $badge_estatus = 'info';
              $texto_estatus = 'En proceso';
            } elseif ($pl['estatus'] === 'cumplido') {
              $badge_estatus = 'success';
              $texto_estatus = 'Cumplido';
            } elseif ($pl['estatus'] === 'pendiente') {
              $badge_estatus = 'warning';
            }
          ?>
          <tr>
            <td><?php echo (int)$pl['plan_id']; ?></td>
            <td><?php echo h($pl['unidad_nombre']); ?></td>
            <td><?php echo h($pl['dimension_nombre']); ?></td>
            <td><?php echo h($pl['problema_identificado']); ?></td>
            <td><?php echo h($pl['accion']); ?></td>
            <td><?php echo h($pl['responsable']); ?></td>
            <td><?php echo h($pl['fecha_compromiso']); ?></td>
            <td><span class="badge badge-<?php echo $badge_estatus; ?>"><?php echo h($texto_estatus); ?></span></td>
            <td class="text-center">
              <button type="button" class="btn btn-sm btn-icon btn-light" onclick="editarPlan(<?php echo (int)$pl['plan_id']; ?>)" title="Editar">
                <i class="icon-pencil"></i>
              </button>
              <button type="button" class="btn btn-sm btn-icon btn-light" onclick="eliminarPlan(<?php echo (int)$pl['plan_id']; ?>)" title="Eliminar">
                <i class="icon-trash"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($planes)): ?>
          <tr><td colspan="9" class="text-center text-muted">No hay planes registrados para este periodo</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php else: ?>
  <div class="alert alert-warning">No hay periodo seleccionado.</div>
  <?php endif; ?>

</div>

<!-- MODAL PLAN -->
<div id="modalPlan" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloModalPlan">Nuevo plan de acción</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="action" value="save_plan">
          <input type="hidden" name="plan_id" id="plan_id" value="0">
          <input type="hidden" name="periodo_id" value="<?php echo $periodo_id; ?>">

          <div class="form-group">
            <label>Dirección <span class="text-danger">*</span></label>
            <select name="unidad_id" id="plan_unidad_id" class="form-control" required>
              <option value="">-- Seleccione --</option>
              <?php foreach ($unidades as $un): ?>
              <option value="<?php echo (int)$un['unidad_id']; ?>"><?php echo h($un['nombre']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Dimensión <span class="text-danger">*</span></label>
            <select name="dimension_id" id="plan_dimension_id" class="form-control" required>
              <option value="">-- Seleccione --</option>
              <?php foreach ($dimensiones as $dim): ?>
              <option value="<?php echo (int)$dim['dimension_id']; ?>"><?php echo h($dim['nombre']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Problema identificado <span class="text-danger">*</span></label>
            <textarea name="problema_identificado" id="plan_problema" class="form-control" rows="2" required maxlength="300"></textarea>
          </div>

          <div class="form-group">
            <label>Acción <span class="text-danger">*</span></label>
            <textarea name="accion" id="plan_accion" class="form-control" rows="3" required maxlength="400"></textarea>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Responsable <span class="text-danger">*</span></label>
                <input type="text" name="responsable" id="plan_responsable" class="form-control" required maxlength="120">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Fecha de compromiso <span class="text-danger">*</span></label>
                <input type="date" name="fecha_compromiso" id="plan_fecha_compromiso" class="form-control" required>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Indicador de cumplimiento</label>
            <input type="text" name="indicador" id="plan_indicador" class="form-control" maxlength="200">
            <small class="form-text text-muted">Opcional: cómo se medirá el éxito de la acción.</small>
          </div>

          <div class="form-group">
            <label>Estatus</label>
            <select name="estatus" id="plan_estatus" class="form-control">
              <option value="pendiente">Pendiente</option>
              <option value="en_proceso">En proceso</option>
              <option value="cumplido">Cumplido</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-link" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
var planesData = <?php echo json_encode($planes, JSON_UNESCAPED_UNICODE); ?>;

function abrirPlanNuevo() {
  document.getElementById('tituloModalPlan').textContent = 'Nuevo plan de acción';
  document.getElementById('plan_id').value = '0';
  document.getElementById('plan_unidad_id').value = '';
  document.getElementById('plan_dimension_id').value = '';
  document.getElementById('plan_problema').value = '';
  document.getElementById('plan_accion').value = '';
  document.getElementById('plan_responsable').value = '';
  document.getElementById('plan_fecha_compromiso').value = '';
  document.getElementById('plan_indicador').value = '';
  document.getElementById('plan_estatus').value = 'pendiente';
}

function editarPlan(id) {
  var pl = planesData.find(function(p) { return p.plan_id == id; });
  if (!pl) return;
  document.getElementById('tituloModalPlan').textContent = 'Editar plan de acción';
  document.getElementById('plan_id').value = pl.plan_id;
  document.getElementById('plan_unidad_id').value = pl.unidad_id;
  document.getElementById('plan_dimension_id').value = pl.dimension_id;
  document.getElementById('plan_problema').value = pl.problema_identificado;
  document.getElementById('plan_accion').value = pl.accion;
  document.getElementById('plan_responsable').value = pl.responsable;
  document.getElementById('plan_fecha_compromiso').value = pl.fecha_compromiso;
  document.getElementById('plan_indicador').value = pl.indicador || '';
  document.getElementById('plan_estatus').value = pl.estatus;
  $('#modalPlan').modal('show');
}

function eliminarPlan(id) {
  if (!confirm('¿Eliminar este plan de acción?')) return;
  var form = document.createElement('form');
  form.method = 'POST';
  form.innerHTML = '<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">' +
                   '<input type="hidden" name="action" value="delete_plan">' +
                   '<input type="hidden" name="plan_id" value="' + id + '">';
  document.body.appendChild(form);
  form.submit();
}

$(document).ready(function() {
  if ($('#tablePlanes tbody tr').length > 1) {
    $('#tablePlanes').DataTable({
      language: { url: '<?php echo ASSET_BASE; ?>global_assets/js/plugins/tables/datatables/Spanish.json' },
      pageLength: 25,
      order: [[7, 'asc'], [6, 'asc']]
    });
  }
});
</script>

<?php
require_once __DIR__ . '/../includes/layout/scripts.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
?>
