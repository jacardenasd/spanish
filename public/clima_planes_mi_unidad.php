<?php
// public/clima_planes_mi_unidad.php
// SGRH - Clima Laboral - Planes de Acción de Mi Unidad
// Accesible para líderes de unidad - crean y gestionan planes para su unidad
// Compatible PHP 5.7 - NO usar operador ??

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/conexion.php';

require_login();
require_empresa();
require_password_change_redirect();

if (session_status() === PHP_SESSION_NONE) session_start();

$empresa_id = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;
$usuario_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;

if ($empresa_id <= 0 || $usuario_id <= 0) {
    http_response_code(400);
    die('Sesión inválida.');
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Verificar si el usuario es líder de alguna unidad
// (comprobando si otros empleados tienen su no_emp como jefe_no_emp)
$stmt_lider = $pdo->prepare("
    SELECT DISTINCT e.unidad_id, u.nombre as unidad_nombre, e.no_emp
    FROM usuario_empresas ue
    INNER JOIN empleados e ON e.empleado_id = ue.empleado_id
    LEFT JOIN org_unidades u ON u.unidad_id = e.unidad_id
    WHERE ue.usuario_id = ? 
      AND ue.empresa_id = ?
      AND ue.estatus = 1
    LIMIT 1
");
$stmt_lider->execute([$usuario_id, $empresa_id]);
$mi_empleado_data = $stmt_lider->fetch(PDO::FETCH_ASSOC);

// Verificar si es líder por tener subordinados o si tiene permiso especial
$tiene_permiso_especial = can('clima.planes_unidad') ? 1 : 0;
$mi_liderazgo = null;

if ($mi_empleado_data) {
    if ($tiene_permiso_especial) {
        // Tiene permiso especial, puede gestionar planes
        $mi_liderazgo = $mi_empleado_data;
    } else {
        // Verificar si tiene subordinados (su no_emp está como jefe_no_emp de otros)
        $stmt_sub = $pdo->prepare("
            SELECT COUNT(*) as subordinados
            FROM empleados
            WHERE empresa_id = ? 
              AND jefe_no_emp = ?
              AND estatus = 'activo'
        ");
        $stmt_sub->execute([$empresa_id, $mi_empleado_data['no_emp']]);
        $tiene_subordinados = (int)$stmt_sub->fetchColumn();
        
        if ($tiene_subordinados > 0) {
            $mi_liderazgo = $mi_empleado_data;
        }
    }
}

if (!$mi_liderazgo) {
    http_response_code(403);
    die('<div class="alert alert-warning">No tienes permisos para gestionar planes de acción. Esta función está disponible solo para líderes de unidad.</div>');
}

$mi_unidad_id = (int)$mi_liderazgo['unidad_id'];
$mi_unidad_nombre = $mi_liderazgo['unidad_nombre'];

$flash = null;
$flash_type = 'info';

// =======================
// CRUD DE PLANES
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    
    if ($accion === 'crear') {
        $periodo_id = isset($_POST['periodo_id']) ? (int)$_POST['periodo_id'] : 0;
        $dimension_id = isset($_POST['dimension_id']) ? (int)$_POST['dimension_id'] : 0;
        $problema = isset($_POST['problema']) ? trim($_POST['problema']) : '';
        $accion_mejora = isset($_POST['accion_mejora']) ? trim($_POST['accion_mejora']) : '';
        $responsable = isset($_POST['responsable']) ? trim($_POST['responsable']) : '';
        $fecha_compromiso = isset($_POST['fecha_compromiso']) ? trim($_POST['fecha_compromiso']) : '';
        $indicador = isset($_POST['indicador']) ? trim($_POST['indicador']) : '';

        if ($periodo_id > 0 && $dimension_id > 0 && !empty($problema) && !empty($accion_mejora) && !empty($responsable) && !empty($fecha_compromiso)) {
            $ins = $pdo->prepare("
                INSERT INTO clima_planes 
                (empresa_id, periodo_id, unidad_id, dimension_id, problema_identificado, accion, responsable, fecha_compromiso, indicador, estatus, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)
            ");
            $ins->execute([
                $empresa_id,
                $periodo_id,
                $mi_unidad_id,
                $dimension_id,
                $problema,
                $accion_mejora,
                $responsable,
                $fecha_compromiso,
                $indicador ? $indicador : null,
                $usuario_id
            ]);
            $flash = 'Plan de acción creado exitosamente.';
            $flash_type = 'success';
        } else {
            $flash = 'Por favor completa los campos obligatorios.';
            $flash_type = 'danger';
        }
    } 
    elseif ($accion === 'actualizar') {
        $plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
        $problema = isset($_POST['problema']) ? trim($_POST['problema']) : '';
        $accion_mejora = isset($_POST['accion_mejora']) ? trim($_POST['accion_mejora']) : '';
        $responsable = isset($_POST['responsable']) ? trim($_POST['responsable']) : '';
        $fecha_compromiso = isset($_POST['fecha_compromiso']) ? trim($_POST['fecha_compromiso']) : '';
        $indicador = isset($_POST['indicador']) ? trim($_POST['indicador']) : '';
        $estatus = isset($_POST['estatus']) ? trim($_POST['estatus']) : 'pendiente';

        if ($plan_id > 0) {
            // Verificar que el plan pertenece a mi unidad
            $stmt_check = $pdo->prepare("SELECT plan_id FROM clima_planes WHERE plan_id = ? AND empresa_id = ? AND unidad_id = ?");
            $stmt_check->execute([$plan_id, $empresa_id, $mi_unidad_id]);
            if ($stmt_check->fetch()) {
                $upd = $pdo->prepare("
                    UPDATE clima_planes 
                    SET problema_identificado = ?, 
                        accion = ?, 
                        responsable = ?, 
                        fecha_compromiso = ?, 
                        indicador = ?,
                        estatus = ?
                    WHERE plan_id = ? AND empresa_id = ? AND unidad_id = ?
                ");
                $upd->execute([
                    $problema,
                    $accion_mejora,
                    $responsable,
                    $fecha_compromiso,
                    $indicador ? $indicador : null,
                    $estatus,
                    $plan_id,
                    $empresa_id,
                    $mi_unidad_id
                ]);
                $flash = 'Plan de acción actualizado exitosamente.';
                $flash_type = 'success';
            } else {
                $flash = 'No tienes permiso para editar este plan.';
                $flash_type = 'danger';
            }
        }
    }
    elseif ($accion === 'eliminar') {
        $plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
        if ($plan_id > 0) {
            $stmt_check = $pdo->prepare("SELECT plan_id FROM clima_planes WHERE plan_id = ? AND empresa_id = ? AND unidad_id = ?");
            $stmt_check->execute([$plan_id, $empresa_id, $mi_unidad_id]);
            if ($stmt_check->fetch()) {
                $del = $pdo->prepare("DELETE FROM clima_planes WHERE plan_id = ? AND empresa_id = ? AND unidad_id = ?");
                $del->execute([$plan_id, $empresa_id, $mi_unidad_id]);
                $flash = 'Plan de acción eliminado.';
                $flash_type = 'info';
            } else {
                $flash = 'No tienes permiso para eliminar este plan.';
                $flash_type = 'danger';
            }
        }
    }
}

// =======================
// DATOS
// =======================
// Periodos publicados para mi unidad
$periodos_stmt = $pdo->prepare("
    SELECT DISTINCT p.periodo_id, p.anio, p.estatus, p.fecha_inicio, p.fecha_fin
    FROM clima_periodos p
    INNER JOIN clima_publicacion cp ON cp.periodo_id = p.periodo_id 
        AND cp.empresa_id = p.empresa_id 
        AND cp.unidad_id = ?
    WHERE p.empresa_id = ?
      AND cp.habilitado = 1
    ORDER BY p.anio DESC
");
$periodos_stmt->execute([$mi_unidad_id, $empresa_id]);
$periodos = $periodos_stmt->fetchAll(PDO::FETCH_ASSOC);

$periodo_id_filtro = isset($_GET['periodo_id']) ? (int)$_GET['periodo_id'] : 0;
if ($periodo_id_filtro <= 0 && !empty($periodos)) {
    $periodo_id_filtro = (int)$periodos[0]['periodo_id'];
}

// Dimensiones
$dimensiones = $pdo->query("SELECT * FROM clima_dimensiones WHERE activo=1 ORDER BY orden")->fetchAll(PDO::FETCH_ASSOC);

// Obtener promedios de mi unidad por dimensión para el periodo seleccionado
$promedios_dimensiones = array();
if ($periodo_id_filtro > 0) {
    foreach ($dimensiones as $dim) {
        $did = (int)$dim['dimension_id'];
        $sql_dim = "
            SELECT AVG(cr.valor) AS promedio
            FROM clima_respuestas cr
            INNER JOIN clima_reactivos crt ON crt.reactivo_id = cr.reactivo_id
            INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
            WHERE cr.periodo_id = ?
              AND ce.empresa_id = ?
              AND ce.unidad_id = ?
              AND ce.elegible = 1
              AND crt.dimension_id = ?
        ";
        $stmt_d = $pdo->prepare($sql_dim);
        $stmt_d->execute([$periodo_id_filtro, $empresa_id, $mi_unidad_id, $did]);
        $row_d = $stmt_d->fetch(PDO::FETCH_ASSOC);
        $prom_dim_1_5 = $row_d ? (float)$row_d['promedio'] : 0.0;
        $prom_dim_0_100 = $prom_dim_1_5 > 0 ? (($prom_dim_1_5 - 1) / 4) * 100 : 0.0;

        $promedios_dimensiones[$did] = round($prom_dim_0_100, 2);
    }
}

// Planes de mi unidad
$planes_stmt = $pdo->prepare("
    SELECT 
        p.plan_id,
        p.periodo_id,
        p.dimension_id,
        p.problema_identificado,
        p.accion,
        p.responsable,
        p.fecha_compromiso,
        p.indicador,
        p.estatus,
        p.created_at,
        per.anio,
        dim.nombre as dimension_nombre
    FROM clima_planes p
    LEFT JOIN clima_periodos per ON per.periodo_id = p.periodo_id
    LEFT JOIN clima_dimensiones dim ON dim.dimension_id = p.dimension_id
    WHERE p.empresa_id = ? AND p.unidad_id = ?
    " . ($periodo_id_filtro > 0 ? " AND p.periodo_id = ?" : "") . "
    ORDER BY p.created_at DESC
");
if ($periodo_id_filtro > 0) {
    $planes_stmt->execute([$empresa_id, $mi_unidad_id, $periodo_id_filtro]);
} else {
    $planes_stmt->execute([$empresa_id, $mi_unidad_id]);
}
$planes = $planes_stmt->fetchAll(PDO::FETCH_ASSOC);

// =======================
// LAYOUT
// =======================
$page_title = 'Clima Laboral - Planes de Acción';
$active_menu = 'clima_planes_mi_unidad';

$extra_css = [
  'global_assets/css/icons/icomoon/styles.min.css',
];

$extra_js = [
  'global_assets/js/plugins/tables/datatables/datatables.min.js',
  'global_assets/js/plugins/forms/selects/select2.min.js',
];

require_once __DIR__ . '/../includes/layout/head.php';
require_once __DIR__ . '/../includes/layout/navbar.php';
require_once __DIR__ . '/../includes/layout/sidebar.php';
require_once __DIR__ . '/../includes/layout/content_open.php';
?>

<div class="page-header page-header-light">
  <div class="page-header-content header-elements-md-inline">
    <div class="page-title d-flex">
      <h4><i class="icon-clipboard3 mr-2"></i> <span class="font-weight-semibold">Clima Laboral</span> - Planes de Acción</h4>
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

  <!-- Info de mi unidad -->
  <div class="card bg-light">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
          <i class="icon-office icon-2x text-primary mr-3"></i>
          <div>
            <h5 class="mb-0"><?php echo h($mi_unidad_nombre); ?></h5>
            <span class="text-muted">Mi Dirección</span>
          </div>
        </div>
        <?php if (!empty($periodos)): ?>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalCrearPlan">
          <i class="icon-plus2 mr-2"></i> Crear Plan de Acción
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!empty($periodos)): ?>

  <!-- Filtro de periodo -->
  <div class="card">
    <div class="card-body">
      <form method="get" class="form-inline">
        <label class="mr-2">Periodo:</label>
        <select name="periodo_id" class="form-control mr-2" onchange="this.form.submit()">
          <option value="0">Todos los periodos</option>
          <?php foreach ($periodos as $p): ?>
          <option value="<?php echo (int)$p['periodo_id']; ?>" <?php echo ((int)$p['periodo_id'] === $periodo_id_filtro) ? 'selected' : ''; ?>>
            <?php echo h($p['anio']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if ($periodo_id_filtro > 0 && !empty($promedios_dimensiones)): ?>
  <!-- Promedios por dimensión -->
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Dimensiones a Trabajar - Periodo <?php
        foreach ($periodos as $p) {
          if ((int)$p['periodo_id'] === $periodo_id_filtro) {
            echo h($p['anio']);
            break;
          }
        }
      ?></h5>
      <span class="text-muted">Las dimensiones con menor puntaje son áreas de oportunidad para tus planes de acción.</span>
    </div>
    <div class="card-body">
      <div class="row">
        <?php 
        // Ordenar dimensiones por promedio ascendente para destacar las más bajas
        $dims_ordenadas = array();
        foreach ($dimensiones as $d) {
          $did = (int)$d['dimension_id'];
          if (isset($promedios_dimensiones[$did])) {
            $dims_ordenadas[] = array(
              'dimension_id' => $did,
              'nombre' => $d['nombre'],
              'promedio' => $promedios_dimensiones[$did]
            );
          }
        }
        usort($dims_ordenadas, function($a, $b) {
          return $a['promedio'] - $b['promedio'];
        });

        foreach ($dims_ordenadas as $d):
          $prom = (float)$d['promedio'];
          $color = '#EF5350';
          if ($prom >= 70) $color = '#29B6F6';
          elseif ($prom >= 50) $color = '#66BB6A';
          elseif ($prom >= 30) $color = '#FFA726';
        ?>
        <div class="col-md-3 col-sm-6 mb-3">
          <div class="card" style="border-left: 4px solid <?php echo $color; ?>;">
            <div class="card-body p-2">
              <h6 class="font-weight-semibold mb-1" style="font-size: 0.9rem;"><?php echo h($d['nombre']); ?></h6>
              <h4 class="mb-0" style="color: <?php echo $color; ?>;">
                <?php echo number_format($d['promedio'], 1); ?>%
              </h4>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tabla de planes -->
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Mis Planes de Acción</h5>
    </div>
    <div class="table-responsive">
      <table class="table table-hover datatable-basic">
        <thead>
          <tr>
            <th>Periodo</th>
            <th>Dimensión</th>
            <th>Problema</th>
            <th>Acción de Mejora</th>
            <th>Responsable</th>
            <th>Fecha Compromiso</th>
            <th>Estatus</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($planes)): ?>
            <?php foreach ($planes as $plan): 
              $estatus_clase = 'secondary';
              $estatus_texto = 'Pendiente';
              if ($plan['estatus'] === 'en_proceso') {
                $estatus_clase = 'warning';
                $estatus_texto = 'En Proceso';
              } elseif ($plan['estatus'] === 'cumplido') {
                $estatus_clase = 'success';
                $estatus_texto = 'Cumplido';
              }
            ?>
            <tr>
              <td><?php echo h($plan['anio']); ?></td>
              <td><?php echo h($plan['dimension_nombre']); ?></td>
              <td><?php echo h($plan['problema_identificado']); ?></td>
              <td><?php echo h($plan['accion']); ?></td>
              <td><?php echo h($plan['responsable']); ?></td>
              <td><?php echo $plan['fecha_compromiso'] ? date('d/m/Y', strtotime($plan['fecha_compromiso'])) : '-'; ?></td>
              <td><span class="badge badge-<?php echo $estatus_clase; ?>"><?php echo $estatus_texto; ?></span></td>
              <td class="text-center">
                <div class="btn-group">
                  <button type="button" class="btn btn-sm btn-light" onclick="editarPlan(<?php echo (int)$plan['plan_id']; ?>)" title="Editar">
                    <i class="icon-pencil7"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-light" onclick="eliminarPlan(<?php echo (int)$plan['plan_id']; ?>)" title="Eliminar">
                    <i class="icon-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" class="text-center text-muted">No hay planes de acción registrados.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php else: ?>
  <div class="alert alert-info">
    <i class="icon-info22 mr-2"></i>
    <strong>Sin resultados publicados:</strong> Actualmente no hay resultados de clima laboral disponibles para crear planes de acción.
  </div>
  <?php endif; ?>

</div>

<!-- Modal Crear Plan -->
<div class="modal fade" id="modalCrearPlan" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="modal-header">
          <h5 class="modal-title">Crear Plan de Acción</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Periodo <span class="text-danger">*</span></label>
            <select name="periodo_id" class="form-control" required>
              <?php foreach ($periodos as $p): ?>
              <option value="<?php echo (int)$p['periodo_id']; ?>" <?php echo ((int)$p['periodo_id'] === $periodo_id_filtro) ? 'selected' : ''; ?>>
                <?php echo h($p['anio']); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Dimensión <span class="text-danger">*</span></label>
            <select name="dimension_id" class="form-control" required>
              <option value="">Selecciona...</option>
              <?php foreach ($dimensiones as $d): ?>
              <option value="<?php echo (int)$d['dimension_id']; ?>">
                <?php echo h($d['nombre']); ?>
                <?php if ($periodo_id_filtro > 0 && isset($promedios_dimensiones[(int)$d['dimension_id']])): ?>
                  (<?php echo number_format($promedios_dimensiones[(int)$d['dimension_id']], 1); ?>%)
                <?php endif; ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Problema Identificado <span class="text-danger">*</span></label>
            <textarea name="problema" class="form-control" rows="3" required 
                      placeholder="Describe el problema o área de oportunidad detectada..."></textarea>
          </div>
          <div class="form-group">
            <label>Acción de Mejora <span class="text-danger">*</span></label>
            <textarea name="accion_mejora" class="form-control" rows="3" required 
                      placeholder="¿Qué acciones concretas se van a tomar?"></textarea>
          </div>
          <div class="form-group">
            <label>Responsable</label>
            <input type="text" name="responsable" class="form-control" 
                   placeholder="Nombre del responsable de ejecutar la acción">
          </div>
          <div class="form-group">
            <label>Fecha Compromiso</label>
            <input type="date" name="fecha_compromiso" class="form-control">
          </div>
          <div class="form-group">
            <label>Indicador de Éxito</label>
            <input type="text" name="indicador" class="form-control" 
                   placeholder="¿Cómo medirás si la acción fue exitosa?">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-link" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Crear Plan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar Plan -->
<div class="modal fade" id="modalEditarPlan" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="formEditarPlan">
        <input type="hidden" name="accion" value="actualizar">
        <input type="hidden" name="plan_id" id="edit_plan_id">
        <div class="modal-header">
          <h5 class="modal-title">Editar Plan de Acción</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Problema Identificado <span class="text-danger">*</span></label>
            <textarea name="problema" id="edit_problema" class="form-control" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <label>Acción de Mejora <span class="text-danger">*</span></label>
            <textarea name="accion_mejora" id="edit_accion_mejora" class="form-control" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <label>Responsable</label>
            <input type="text" name="responsable" id="edit_responsable" class="form-control">
          </div>
          <div class="form-group">
            <label>Fecha Compromiso</label>
            <input type="date" name="fecha_compromiso" id="edit_fecha_compromiso" class="form-control">
          </div>
          <div class="form-group">
            <label>Indicador de Éxito</label>
            <input type="text" name="indicador" id="edit_indicador" class="form-control">
          </div>
          <div class="form-group">
            <label>Estatus <span class="text-danger">*</span></label>
            <select name="estatus" id="edit_estatus" class="form-control" required>
              <option value="pendiente">Pendiente</option>
              <option value="en_proceso">En Proceso</option>
              <option value="cumplido">Cumplido</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-link" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Actualizar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Form hidden para eliminar -->
<form method="post" id="formEliminarPlan" style="display:none;">
  <input type="hidden" name="accion" value="eliminar">
  <input type="hidden" name="plan_id" id="del_plan_id">
</form>

<?php
require_once __DIR__ . '/../includes/layout/scripts.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
?>

<script>
var planesData = <?php echo json_encode($planes, JSON_UNESCAPED_UNICODE); ?>;

$(document).ready(function() {
  $('.datatable-basic').DataTable({
    language: {
      url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
    },
    order: [[0, 'desc']]
  });

  $('.select2').select2();
});

function editarPlan(planId) {
  var plan = planesData.find(function(p) { return p.plan_id == planId; });
  if (!plan) return;

  $('#edit_plan_id').val(plan.plan_id);
  $('#edit_problema').val(plan.problema_identificado);
  $('#edit_accion_mejora').val(plan.accion);
  $('#edit_responsable').val(plan.responsable);
  $('#edit_fecha_compromiso').val(plan.fecha_compromiso);
  $('#edit_indicador').val(plan.indicador);
  $('#edit_estatus').val(plan.estatus);

  $('#modalEditarPlan').modal('show');
}

function eliminarPlan(planId) {
  if (confirm('¿Estás seguro de eliminar este plan de acción?')) {
    $('#del_plan_id').val(planId);
    $('#formEliminarPlan').submit();
  }
}
</script>
