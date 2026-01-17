<?php
// public/clima_dimensiones.php
// SGRH - Clima Laboral - Administración de Dimensiones y Reactivos
// Reglas: dimensiones globales (no por empresa), reactivos asociados a dimensión
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$flash = null;
$flash_type = 'info';

// =======================
// PROCESAR ACCIONES
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf_token'], isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '')) {
            throw new RuntimeException('Solicitud inválida (CSRF).');
        }

        $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

        // =====================
        // DIMENSIONES
        // =====================
        if ($action === 'save_dimension') {
            $dimension_id = isset($_POST['dimension_id']) ? (int)$_POST['dimension_id'] : 0;
            $nombre = isset($_POST['nombre']) ? trim((string)$_POST['nombre']) : '';
            $orden = isset($_POST['orden']) ? (int)$_POST['orden'] : 1;
            $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 1;

            if ($nombre === '') throw new RuntimeException('El nombre de la dimensión es obligatorio.');

            // Validar unicidad
            if ($dimension_id > 0) {
                $chk = $pdo->prepare("SELECT dimension_id FROM clima_dimensiones WHERE nombre=? AND dimension_id<>? LIMIT 1");
                $chk->execute([$nombre, $dimension_id]);
                if ($chk->fetch()) throw new RuntimeException('Ya existe una dimensión con ese nombre.');
            } else {
                $chk = $pdo->prepare("SELECT dimension_id FROM clima_dimensiones WHERE nombre=? LIMIT 1");
                $chk->execute([$nombre]);
                if ($chk->fetch()) throw new RuntimeException('Ya existe una dimensión con ese nombre.');
            }

            if ($dimension_id > 0) {
                $upd = $pdo->prepare("UPDATE clima_dimensiones SET nombre=?, orden=?, activo=? WHERE dimension_id=?");
                $upd->execute([$nombre, $orden, $activo, $dimension_id]);
                $flash = 'Dimensión actualizada correctamente.';
            } else {
                $ins = $pdo->prepare("INSERT INTO clima_dimensiones (nombre, orden, activo) VALUES (?, ?, ?)");
                $ins->execute([$nombre, $orden, $activo]);
                $flash = 'Dimensión creada correctamente.';
            }
            $flash_type = 'success';

        } elseif ($action === 'delete_dimension') {
            $dimension_id = isset($_POST['dimension_id']) ? (int)$_POST['dimension_id'] : 0;
            if ($dimension_id <= 0) throw new RuntimeException('Dimensión inválida.');

            // Verificar si hay reactivos asociados
            $chkR = $pdo->prepare("SELECT COUNT(*) FROM clima_reactivos WHERE dimension_id=?");
            $chkR->execute([$dimension_id]);
            if ((int)$chkR->fetchColumn() > 0) {
                throw new RuntimeException('No se puede eliminar: la dimensión tiene reactivos asociados.');
            }

            $del = $pdo->prepare("DELETE FROM clima_dimensiones WHERE dimension_id=?");
            $del->execute([$dimension_id]);
            $flash = 'Dimensión eliminada.';
            $flash_type = 'success';

        // =====================
        // REACTIVOS
        // =====================
        } elseif ($action === 'save_reactivo') {
            $reactivo_id = isset($_POST['reactivo_id']) ? (int)$_POST['reactivo_id'] : 0;
            $dimension_id = isset($_POST['dimension_id']) ? (int)$_POST['dimension_id'] : 0;
            $texto = isset($_POST['texto']) ? trim((string)$_POST['texto']) : '';
            $orden = isset($_POST['orden']) ? (int)$_POST['orden'] : 1;
            $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 1;

            if ($dimension_id <= 0) throw new RuntimeException('Debe seleccionar una dimensión.');
            if ($texto === '') throw new RuntimeException('El texto del reactivo es obligatorio.');

            if ($reactivo_id > 0) {
                $upd = $pdo->prepare("UPDATE clima_reactivos SET dimension_id=?, texto=?, orden=?, activo=? WHERE reactivo_id=?");
                $upd->execute([$dimension_id, $texto, $orden, $activo, $reactivo_id]);
                $flash = 'Reactivo actualizado correctamente.';
            } else {
                $ins = $pdo->prepare("INSERT INTO clima_reactivos (dimension_id, texto, orden, activo) VALUES (?, ?, ?, ?)");
                $ins->execute([$dimension_id, $texto, $orden, $activo]);
                $flash = 'Reactivo creado correctamente.';
            }
            $flash_type = 'success';

        } elseif ($action === 'delete_reactivo') {
            $reactivo_id = isset($_POST['reactivo_id']) ? (int)$_POST['reactivo_id'] : 0;
            if ($reactivo_id <= 0) throw new RuntimeException('Reactivo inválido.');

            // Verificar si hay respuestas
            $chkResp = $pdo->prepare("SELECT COUNT(*) FROM clima_respuestas WHERE reactivo_id=?");
            $chkResp->execute([$reactivo_id]);
            if ((int)$chkResp->fetchColumn() > 0) {
                throw new RuntimeException('No se puede eliminar: el reactivo ya tiene respuestas registradas.');
            }

            $del = $pdo->prepare("DELETE FROM clima_reactivos WHERE reactivo_id=?");
            $del->execute([$reactivo_id]);
            $flash = 'Reactivo eliminado.';
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
$dimensiones = $pdo->query("SELECT * FROM clima_dimensiones ORDER BY orden, nombre")->fetchAll(PDO::FETCH_ASSOC);

// Reactivos (con nombre de dimensión)
$reactivos = $pdo->query("
    SELECT r.*, d.nombre AS dimension_nombre
    FROM clima_reactivos r
    INNER JOIN clima_dimensiones d ON d.dimension_id = r.dimension_id
    ORDER BY r.dimension_id, r.orden
")->fetchAll(PDO::FETCH_ASSOC);

// =======================
// LAYOUT
// =======================
$page_title = 'Clima Laboral - Dimensiones y Reactivos';
$active_menu = 'clima_dimensiones';

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
      <h4><i class="icon-lan2 mr-2"></i> <span class="font-weight-semibold">Clima Laboral</span> - Dimensiones y Reactivos</h4>
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

  <!-- DIMENSIONES -->
  <div class="card">
    <div class="card-header header-elements-inline">
      <h5 class="card-title">Dimensiones</h5>
      <div class="header-elements">
        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalDimension" onclick="abrirDimensionNueva()">
          <i class="icon-plus2 mr-1"></i> Nueva dimensión
        </button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th style="width:60px;">ID</th>
            <th>Nombre</th>
            <th style="width:80px;">Orden</th>
            <th style="width:100px;">Estatus</th>
            <th style="width:120px;" class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dimensiones as $d): ?>
          <tr>
            <td><?php echo (int)$d['dimension_id']; ?></td>
            <td><?php echo h($d['nombre']); ?></td>
            <td><?php echo (int)$d['orden']; ?></td>
            <td>
              <?php if ((int)$d['activo'] === 1): ?>
                <span class="badge badge-success">Activo</span>
              <?php else: ?>
                <span class="badge badge-secondary">Inactivo</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <button type="button" class="btn btn-sm btn-icon btn-light" onclick="editarDimension(<?php echo (int)$d['dimension_id']; ?>)" title="Editar">
                <i class="icon-pencil"></i>
              </button>
              <button type="button" class="btn btn-sm btn-icon btn-light" onclick="eliminarDimension(<?php echo (int)$d['dimension_id']; ?>)" title="Eliminar">
                <i class="icon-trash"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($dimensiones)): ?>
          <tr><td colspan="5" class="text-center text-muted">No hay dimensiones registradas</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- REACTIVOS -->
  <div class="card">
    <div class="card-header header-elements-inline">
      <h5 class="card-title">Reactivos</h5>
      <div class="header-elements">
        <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalReactivo" onclick="abrirReactivoNuevo()">
          <i class="icon-plus2 mr-1"></i> Nuevo reactivo
        </button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover" id="tableReactivos">
        <thead>
          <tr>
            <th style="width:60px;">ID</th>
            <th style="width:200px;">Dimensión</th>
            <th>Texto</th>
            <th style="width:80px;">Orden</th>
            <th style="width:100px;">Estatus</th>
            <th style="width:120px;" class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reactivos as $r): ?>
          <tr>
            <td><?php echo (int)$r['reactivo_id']; ?></td>
            <td><?php echo h($r['dimension_nombre']); ?></td>
            <td><?php echo h($r['texto']); ?></td>
            <td><?php echo (int)$r['orden']; ?></td>
            <td>
              <?php if ((int)$r['activo'] === 1): ?>
                <span class="badge badge-success">Activo</span>
              <?php else: ?>
                <span class="badge badge-secondary">Inactivo</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <button type="button" class="btn btn-sm btn-icon btn-light" onclick="editarReactivo(<?php echo (int)$r['reactivo_id']; ?>)" title="Editar">
                <i class="icon-pencil"></i>
              </button>
              <button type="button" class="btn btn-sm btn-icon btn-light" onclick="eliminarReactivo(<?php echo (int)$r['reactivo_id']; ?>)" title="Eliminar">
                <i class="icon-trash"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($reactivos)): ?>
          <tr><td colspan="6" class="text-center text-muted">No hay reactivos registrados</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- MODAL DIMENSION -->
<div id="modalDimension" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloModalDimension">Nueva dimensión</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="action" value="save_dimension">
          <input type="hidden" name="dimension_id" id="dimension_id" value="0">

          <div class="form-group">
            <label>Nombre <span class="text-danger">*</span></label>
            <input type="text" name="nombre" id="dimension_nombre" class="form-control" required maxlength="120">
          </div>

          <div class="form-group">
            <label>Orden</label>
            <input type="number" name="orden" id="dimension_orden" class="form-control" value="1" min="1">
          </div>

          <div class="form-group">
            <label>Estatus</label>
            <select name="activo" id="dimension_activo" class="form-control">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
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

<!-- MODAL REACTIVO -->
<div id="modalReactivo" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloModalReactivo">Nuevo reactivo</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="action" value="save_reactivo">
          <input type="hidden" name="reactivo_id" id="reactivo_id" value="0">

          <div class="form-group">
            <label>Dimensión <span class="text-danger">*</span></label>
            <select name="dimension_id" id="reactivo_dimension_id" class="form-control" required>
              <option value="">-- Seleccione --</option>
              <?php foreach ($dimensiones as $dim): ?>
              <option value="<?php echo (int)$dim['dimension_id']; ?>">
                <?php echo h($dim['nombre']); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Texto del reactivo <span class="text-danger">*</span></label>
            <textarea name="texto" id="reactivo_texto" class="form-control" rows="3" required maxlength="400"></textarea>
            <small class="form-text text-muted">Máximo 400 caracteres.</small>
          </div>

          <div class="form-group">
            <label>Orden</label>
            <input type="number" name="orden" id="reactivo_orden" class="form-control" value="1" min="1">
          </div>

          <div class="form-group">
            <label>Estatus</label>
            <select name="activo" id="reactivo_activo" class="form-control">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
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
var dimensionesData = <?php echo json_encode($dimensiones, JSON_UNESCAPED_UNICODE); ?>;
var reactivosData = <?php echo json_encode($reactivos, JSON_UNESCAPED_UNICODE); ?>;

function abrirDimensionNueva() {
  document.getElementById('tituloModalDimension').textContent = 'Nueva dimensión';
  document.getElementById('dimension_id').value = '0';
  document.getElementById('dimension_nombre').value = '';
  document.getElementById('dimension_orden').value = '1';
  document.getElementById('dimension_activo').value = '1';
}

function editarDimension(id) {
  var dim = dimensionesData.find(function(d) { return d.dimension_id == id; });
  if (!dim) return;
  document.getElementById('tituloModalDimension').textContent = 'Editar dimensión';
  document.getElementById('dimension_id').value = dim.dimension_id;
  document.getElementById('dimension_nombre').value = dim.nombre;
  document.getElementById('dimension_orden').value = dim.orden;
  document.getElementById('dimension_activo').value = dim.activo;
  $('#modalDimension').modal('show');
}

function eliminarDimension(id) {
  if (!confirm('¿Eliminar esta dimensión? Solo si no tiene reactivos asociados.')) return;
  var form = document.createElement('form');
  form.method = 'POST';
  form.innerHTML = '<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">' +
                   '<input type="hidden" name="action" value="delete_dimension">' +
                   '<input type="hidden" name="dimension_id" value="' + id + '">';
  document.body.appendChild(form);
  form.submit();
}

function abrirReactivoNuevo() {
  document.getElementById('tituloModalReactivo').textContent = 'Nuevo reactivo';
  document.getElementById('reactivo_id').value = '0';
  document.getElementById('reactivo_dimension_id').value = '';
  document.getElementById('reactivo_texto').value = '';
  document.getElementById('reactivo_orden').value = '1';
  document.getElementById('reactivo_activo').value = '1';
}

function editarReactivo(id) {
  var r = reactivosData.find(function(x) { return x.reactivo_id == id; });
  if (!r) return;
  document.getElementById('tituloModalReactivo').textContent = 'Editar reactivo';
  document.getElementById('reactivo_id').value = r.reactivo_id;
  document.getElementById('reactivo_dimension_id').value = r.dimension_id;
  document.getElementById('reactivo_texto').value = r.texto;
  document.getElementById('reactivo_orden').value = r.orden;
  document.getElementById('reactivo_activo').value = r.activo;
  $('#modalReactivo').modal('show');
}

function eliminarReactivo(id) {
  if (!confirm('¿Eliminar este reactivo? Solo si no tiene respuestas registradas.')) return;
  var form = document.createElement('form');
  form.method = 'POST';
  form.innerHTML = '<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">' +
                   '<input type="hidden" name="action" value="delete_reactivo">' +
                   '<input type="hidden" name="reactivo_id" value="' + id + '">';
  document.body.appendChild(form);
  form.submit();
}

$(document).ready(function() {
  if ($('#tableReactivos tbody tr').length > 1) {
    $('#tableReactivos').DataTable({
      language: { url: '<?php echo ASSET_BASE; ?>global_assets/js/plugins/tables/datatables/Spanish.json' },
      pageLength: 25,
      order: [[1, 'asc'], [3, 'asc']]
    });
  }
});
</script>

<?php
require_once __DIR__ . '/../includes/layout/scripts.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
?>
