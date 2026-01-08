<?php
// public/clima_resultados_mi_unidad.php
// SGRH - Clima Laboral - Resultados de Mi Unidad
// Accesible para cualquier usuario - solo ve resultados de su propia unidad si están publicados
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

$flash = null;
$flash_type = 'info';

// Obtener empleado del usuario
$stmt_emp = $pdo->prepare("
    SELECT e.empleado_id, e.unidad_id, u.nombre as unidad_nombre
    FROM usuario_empresas ue
    INNER JOIN empleados e ON e.empleado_id = ue.empleado_id
    LEFT JOIN org_unidades u ON u.unidad_id = e.unidad_id
    WHERE ue.usuario_id = ? AND ue.empresa_id = ? AND ue.estatus = 1
    LIMIT 1
");
$stmt_emp->execute([$usuario_id, $empresa_id]);
$mi_empleado = $stmt_emp->fetch(PDO::FETCH_ASSOC);

if (!$mi_empleado || !$mi_empleado['unidad_id']) {
    $flash = 'No tienes una unidad asignada. Contacta a Recursos Humanos.';
    $flash_type = 'warning';
    $mi_empleado = null;
}

$mi_unidad_id = $mi_empleado ? (int)$mi_empleado['unidad_id'] : 0;

// =======================
// SELECCIÓN DE PERIODO
// =======================
$periodo_id = isset($_GET['periodo_id']) ? (int)$_GET['periodo_id'] : 0;

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

if ($periodo_id <= 0 && !empty($periodos)) {
    $periodo_id = (int)$periodos[0]['periodo_id'];
}

$periodo = null;
if ($periodo_id > 0) {
    $pstmt = $pdo->prepare("
        SELECT p.* 
        FROM clima_periodos p
        INNER JOIN clima_publicacion cp ON cp.periodo_id = p.periodo_id 
            AND cp.empresa_id = p.empresa_id 
            AND cp.unidad_id = ?
        WHERE p.periodo_id = ? 
          AND p.empresa_id = ?
          AND cp.habilitado = 1
        LIMIT 1
    ");
    $pstmt->execute([$mi_unidad_id, $periodo_id, $empresa_id]);
    $periodo = $pstmt->fetch(PDO::FETCH_ASSOC);
}

// =======================
// DIMENSIONES
// =======================
$dimensiones = $pdo->query("SELECT * FROM clima_dimensiones WHERE activo=1 ORDER BY orden")->fetchAll(PDO::FETCH_ASSOC);

// =======================
// RESULTADOS MI UNIDAD
// =======================
$resultados = null;
$promedios_dimensiones = array();

if ($periodo && $mi_unidad_id > 0) {
    // Promedio general de mi unidad
    $sql_mi_unidad = "
        SELECT 
            COUNT(DISTINCT cr.empleado_id) AS total_respondieron,
            AVG(cr.valor) AS promedio_unidad
        FROM clima_respuestas cr
        INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
        WHERE cr.periodo_id = ?
          AND ce.empresa_id = ?
          AND ce.unidad_id = ?
          AND ce.elegible = 1
    ";
    $stmt_u = $pdo->prepare($sql_mi_unidad);
    $stmt_u->execute([$periodo_id, $empresa_id, $mi_unidad_id]);
    $row_u = $stmt_u->fetch(PDO::FETCH_ASSOC);
    
    if ($row_u && (int)$row_u['total_respondieron'] > 0) {
        $prom_1_5 = (float)$row_u['promedio_unidad'];
        $prom_0_100 = $prom_1_5 > 0 ? (($prom_1_5 - 1) / 4) * 100 : 0.0;
        
        $resultados = array(
            'total_respondieron' => (int)$row_u['total_respondieron'],
            'promedio_unidad' => round($prom_0_100, 2)
        );

        // Promedios por dimensión
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
            $stmt_d->execute([$periodo_id, $empresa_id, $mi_unidad_id, $did]);
            $row_d = $stmt_d->fetch(PDO::FETCH_ASSOC);
            $prom_dim_1_5 = $row_d ? (float)$row_d['promedio'] : 0.0;
            $prom_dim_0_100 = $prom_dim_1_5 > 0 ? (($prom_dim_1_5 - 1) / 4) * 100 : 0.0;

            $promedios_dimensiones[] = array(
                'dimension_id' => $did,
                'dimension_nombre' => $dim['nombre'],
                'promedio' => round($prom_dim_0_100, 2)
            );
        }
    }
}

// =======================
// LAYOUT
// =======================
$page_title = 'Clima Laboral - Mis Resultados';
$active_menu = 'clima_resultados_mi_unidad';

$extra_css = [
  'global_assets/css/icons/icomoon/styles.min.css',
];

$extra_js = [
  'global_assets/js/plugins/visualization/echarts/echarts.min.js',
];

require_once __DIR__ . '/../includes/layout/head.php';
require_once __DIR__ . '/../includes/layout/navbar.php';
require_once __DIR__ . '/../includes/layout/sidebar.php';
require_once __DIR__ . '/../includes/layout/content_open.php';
?>

<div class="page-header page-header-light">
  <div class="page-header-content header-elements-md-inline">
    <div class="page-title d-flex">
      <h4><i class="icon-graph mr-2"></i> <span class="font-weight-semibold">Clima Laboral</span> - Mis Resultados</h4>
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

  <?php if ($mi_empleado): ?>

  <!-- Info de mi unidad -->
  <div class="card bg-light">
    <div class="card-body">
      <div class="d-flex align-items-center">
        <i class="icon-office icon-2x text-primary mr-3"></i>
        <div>
          <h5 class="mb-0"><?php echo h($mi_empleado['unidad_nombre']); ?></h5>
          <span class="text-muted">Mi Dirección</span>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($periodos)): ?>
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
            <?php echo h($p['anio']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if ($periodo && $resultados): ?>

  <!-- Resumen de mi unidad -->
  <div class="row">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-body text-center">
          <div class="mb-3">
            <h5 class="font-weight-semibold mb-0">Promedio de Mi Dirección</h5>
            <span class="text-muted">Periodo <?php echo h($periodo['anio']); ?></span>
          </div>
          <div class="svg-center position-relative" id="gauge-mi-unidad" style="height: 150px;"></div>
          <h2 class="font-weight-bold mt-3 mb-0" style="font-size:3rem;">
            <?php echo number_format($resultados['promedio_unidad'], 1); ?>%
          </h2>
          <span class="text-muted">Escala 0-100</span>
          <div class="mt-3">
            <span class="badge badge-light" style="font-size: 1rem;">
              <i class="icon-users mr-1"></i> <?php echo $resultados['total_respondieron']; ?> personas respondieron
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <h6 class="card-title font-weight-semibold">Desglose por Dimensión</h6>
        </div>
        <div class="card-body" style="padding: 0;">
          <div id="chart-dimensiones-mi-unidad" style="height: 300px;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Detalle por dimensión -->
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Resultados Detallados por Dimensión</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <?php foreach ($promedios_dimensiones as $d): 
          $prom_dim = (float)$d['promedio'];
          $color_dim = '#EF5350';
          if ($prom_dim >= 70) $color_dim = '#29B6F6';
          elseif ($prom_dim >= 50) $color_dim = '#66BB6A';
          elseif ($prom_dim >= 30) $color_dim = '#FFA726';
        ?>
        <div class="col-md-4 col-sm-6 mb-3">
          <div class="card" style="border-left: 4px solid <?php echo $color_dim; ?>;">
            <div class="card-body">
              <h6 class="font-weight-semibold mb-2"><?php echo h($d['dimension_nombre']); ?></h6>
              <h3 class="mb-0" style="color: <?php echo $color_dim; ?>;">
                <?php echo number_format($d['promedio'], 1); ?>%
              </h3>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="alert alert-info">
    <i class="icon-info22 mr-2"></i>
    <strong>Nota:</strong> Estos resultados reflejan la percepción del clima laboral de tu Dirección en el periodo seleccionado. 
    Los datos individuales son confidenciales y no se comparten.
  </div>

  <?php elseif ($periodo): ?>
  <div class="alert alert-warning">
    <strong>Sin respuestas:</strong> Tu Dirección aún no tiene respuestas suficientes para mostrar resultados en este periodo.
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="alert alert-info">
    <i class="icon-info22 mr-2"></i>
    <strong>Sin resultados publicados:</strong> Actualmente no hay resultados de clima laboral disponibles para tu Dirección.
    Los resultados se publicarán una vez que se complete el proceso de medición.
  </div>
  <?php endif; ?>

  <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/layout/scripts.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
?>

<script>
$(document).ready(function() {
  
<?php if ($periodo && $resultados): ?>
  // Gauge de mi unidad
  var promedioMiUnidad = <?php echo (float)$resultados['promedio_unidad']; ?>;
  if (document.getElementById('gauge-mi-unidad')) {
    var gaugeMiUnidad = echarts.init(document.getElementById('gauge-mi-unidad'));
    var optionGauge = {
      series: [{
        type: 'gauge',
        startAngle: 180,
        endAngle: 0,
        min: 0,
        max: 100,
        splitNumber: 5,
        axisLine: {
          lineStyle: {
            width: 12,
            color: [
              [0.3, '#EF5350'],
              [0.5, '#FFA726'],
              [0.7, '#66BB6A'],
              [1, '#29B6F6']
            ]
          }
        },
        pointer: { show: false },
        axisTick: { show: false },
        splitLine: { show: false },
        axisLabel: { show: false },
        detail: { show: false },
        data: [{ value: promedioMiUnidad.toFixed(1) }]
      }]
    };
    gaugeMiUnidad.setOption(optionGauge);
  }

  // Gráfico de dimensiones
  if (document.getElementById('chart-dimensiones-mi-unidad')) {
    var chartDim = echarts.init(document.getElementById('chart-dimensiones-mi-unidad'));
    var optionDim = {
      grid: {
        left: '5%',
        right: '5%',
        bottom: '5%',
        top: '5%',
        containLabel: true
      },
      tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        formatter: function(params) {
          return params[0].name + ': ' + params[0].value.toFixed(1) + '%';
        }
      },
      xAxis: {
        type: 'value',
        min: 0,
        max: 100,
        axisLabel: {
          formatter: '{value}%'
        }
      },
      yAxis: {
        type: 'category',
        data: <?php echo json_encode(array_map(function($d) { return $d['dimension_nombre']; }, $promedios_dimensiones), JSON_UNESCAPED_UNICODE); ?>,
        axisLabel: {
          interval: 0,
          fontSize: 10
        }
      },
      series: [{
        type: 'bar',
        data: <?php echo json_encode(array_map(function($d) { return $d['promedio']; }, $promedios_dimensiones)); ?>,
        itemStyle: {
          color: function(params) {
            var val = params.value;
            if (val < 30) return '#EF5350';
            if (val < 50) return '#FFA726';
            if (val < 70) return '#66BB6A';
            return '#29B6F6';
          }
        },
        label: {
          show: true,
          position: 'right',
          formatter: '{c}%',
          fontSize: 10
        }
      }]
    };
    chartDim.setOption(optionDim);
  }
<?php endif; ?>

});
</script>
