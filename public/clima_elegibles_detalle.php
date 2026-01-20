<?php
// public/clima_elegibles_detalle.php
// Detalle de elegibles por unidad en un período

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

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$empresa_id = (int)($_SESSION['empresa_id'] ?? 0);
$periodo_id = (int)($_GET['periodo_id'] ?? 0);
$unidad_id = (int)($_GET['unidad_id'] ?? 0);

if ($empresa_id <= 0 || $periodo_id <= 0 || $unidad_id <= 0) {
    http_response_code(400);
    die('Parámetros inválidos.');
}

// Período
$pstmt = $pdo->prepare("SELECT * FROM clima_periodos WHERE periodo_id=? AND empresa_id=? LIMIT 1");
$pstmt->execute([$periodo_id, $empresa_id]);
$periodo = $pstmt->fetch(PDO::FETCH_ASSOC);

if (!$periodo) {
    http_response_code(404);
    die('Período no encontrado.');
}

// Unidad
$ustmt = $pdo->prepare("SELECT * FROM org_unidades WHERE unidad_id=? LIMIT 1");
$ustmt->execute([$unidad_id]);
$unidad = $ustmt->fetch(PDO::FETCH_ASSOC);

if (!$unidad) {
    http_response_code(404);
    die('Unidad no encontrada.');
}

// Elegibles con estado de respuesta
$sql = "
    SELECT
        e.empleado_id,
        emp.no_emp,
        emp.rfc_base,
        CONCAT_WS(' ', emp.nombre, emp.apellido_paterno, emp.apellido_materno) AS nombre_completo,
        e.elegible,
        CASE WHEN r.empleado_id IS NOT NULL THEN 'Respondió' ELSE 'Pendiente' END AS estado_respuesta,
        MAX(r.fecha_respuesta) AS fecha_respuesta,
        COUNT(DISTINCT r.reactivo_id) AS total_respuestas
    FROM clima_elegibles e
    INNER JOIN empleados emp ON emp.empleado_id = e.empleado_id
    LEFT JOIN clima_respuestas r 
        ON r.periodo_id = e.periodo_id 
       AND r.empleado_id = e.empleado_id
    WHERE e.periodo_id = ?
      AND e.empresa_id = ?
      AND e.unidad_id = ?
      AND e.elegible = 1
    GROUP BY e.empleado_id, emp.no_emp, emp.rfc_base, emp.nombre, emp.apellido_paterno, emp.apellido_materno, r.empleado_id
    ORDER BY emp.no_emp ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$periodo_id, $empresa_id, $unidad_id]);
$elegibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar respondidos y pendientes
$respondidos = 0;
$pendientes = 0;
foreach ($elegibles as $e) {
    if ($e['estado_respuesta'] === 'Respondió') {
        $respondidos++;
    } else {
        $pendientes++;
    }
}

$total = count($elegibles);
$pct_resp = ($total > 0) ? round(($respondidos * 100) / $total, 2) : 0.0;

// Layout
$page_title  = 'Clima - Elegibles por Unidad';
$active_menu = 'clima_participacion';

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
      <h4><i class="icon-list mr-2"></i> <span class="font-weight-semibold">Clima Laboral</span> - Elegibles por Unidad</h4>
    </div>
  </div>
</div>

<div class="content">

  <!-- Breadcrumb y info -->
  <div class="card">
    <div class="card-body">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h5>
            <i class="icon-sitemap mr-2"></i>
            <?php echo h($unidad['nombre']); ?>
          </h5>
          <p class="text-muted mb-0">
            Período: <strong><?php echo h($periodo['anio']); ?></strong> 
            (<?php echo $periodo['fecha_inicio'] ? date('d/m/Y', strtotime($periodo['fecha_inicio'])) : 'N/A'; ?>
             - 
            <?php echo $periodo['fecha_fin'] ? date('d/m/Y', strtotime($periodo['fecha_fin'])) : 'N/A'; ?>)
          </p>
        </div>
        <div class="col-md-4 text-right">
          <a href="clima_participacion.php?periodo_id=<?php echo (int)$periodo_id; ?>" class="btn btn-light">
            <i class="icon-arrow-left52 mr-1"></i> Regresar
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Resumen -->
  <div class="row">
    <div class="col-md-3">
      <div class="card bg-light">
        <div class="card-body text-center">
          <h3 class="font-weight-bold"><?php echo $total; ?></h3>
          <p class="text-muted mb-0">Total Elegibles</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-light border-left-3 border-left-success">
        <div class="card-body text-center">
          <h3 class="font-weight-bold text-success"><?php echo $respondidos; ?></h3>
          <p class="text-muted mb-0">Respondieron</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-light border-left-3 border-left-warning">
        <div class="card-body text-center">
          <h3 class="font-weight-bold text-warning"><?php echo $pendientes; ?></h3>
          <p class="text-muted mb-0">Pendientes</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-light border-left-3 border-left-info">
        <div class="card-body text-center">
          <h3 class="font-weight-bold text-info"><?php echo h($pct_resp); ?>%</h3>
          <p class="text-muted mb-0">Participación</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabla de elegibles -->
  <div class="card">
    <div class="card-header header-elements-inline">
      <h5 class="card-title">Detalle de Elegibles</h5>
    </div>

    <table class="table datatable-basic" id="tbl_elegibles">
      <thead>
        <tr>
          <th>No. Emp</th>
          <th>RFC</th>
          <th>Nombre Completo</th>
          <th>Estado</th>
          <th>Respuestas</th>
          <th>Fecha Respuesta</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($elegibles as $e): ?>
          <tr class="<?php echo ($e['estado_respuesta'] === 'Respondió') ? '' : 'table-warning'; ?>">
            <td><?php echo h($e['no_emp']); ?></td>
            <td><?php echo h($e['rfc_base']); ?></td>
            <td><?php echo h($e['nombre_completo']); ?></td>
            <td>
              <?php if ($e['estado_respuesta'] === 'Respondió'): ?>
                <span class="badge badge-success">
                  <i class="icon-checkmark mr-1"></i> Respondió
                </span>
              <?php else: ?>
                <span class="badge badge-warning">
                  <i class="icon-clock mr-1"></i> Pendiente
                </span>
              <?php endif; ?>
            </td>
            <td>
              <?php echo ($e['total_respuestas'] > 0) ? (int)$e['total_respuestas'] : '—'; ?>
            </td>
            <td>
              <?php echo ($e['fecha_respuesta']) ? date('d/m/Y H:i', strtotime($e['fecha_respuesta'])) : '—'; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<?php
require_once __DIR__ . '/../includes/layout/footer.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
require_once __DIR__ . '/../includes/layout/scripts.php';
?>

<script>
(function() {
  if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $('#tbl_elegibles').DataTable({
      autoWidth: false,
      lengthChange: false,
      pageLength: 10,
      order: [[0,'asc']]
    });
  }
})();
</script>
