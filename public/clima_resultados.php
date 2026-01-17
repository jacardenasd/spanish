<?php
// public/clima_resultados.php
// SGRH - Clima Laboral - Dashboard de Resultados por Dirección
// Reglas: solo mostrar si clima_publicacion.habilitado=1 para la unidad
// Cálculos: puntaje promedio por dimensión, global, ranking, comparativa
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

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$flash = null;
$flash_type = 'info';

// =======================
// SELECCIÓN DE PERIODO
// =======================
$periodo_id = isset($_GET['periodo_id']) ? (int)$_GET['periodo_id'] : 0;

$periodos_stmt = $pdo->prepare("SELECT periodo_id, anio, estatus, fecha_inicio, fecha_fin FROM clima_periodos WHERE empresa_id=? ORDER BY anio DESC");
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

if (!$periodo) {
    $flash = 'No hay un periodo válido seleccionado para esta empresa.';
    $flash_type = 'warning';
}

// =======================
// DIMENSIONES
// =======================
$dimensiones = $pdo->query("SELECT * FROM clima_dimensiones WHERE activo=1 ORDER BY orden")->fetchAll(PDO::FETCH_ASSOC);

// =======================
// CALCULAR RESULTADOS
// =======================
$resumen_global = null;
$resultados_unidad = [];
$ranking_unidades = [];
$demograficos = array('sexo' => array(), 'edad' => array(), 'antiguedad' => array());

if ($periodo) {
    // Resumen global empresa (convertir a escala 0-100)
    $sql_global = "
        SELECT AVG(cr.valor) AS promedio_global
        FROM clima_respuestas cr
        INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
        WHERE cr.periodo_id = ?
          AND ce.empresa_id = ?
          AND ce.elegible = 1
    ";
    $stmt_g = $pdo->prepare($sql_global);
    $stmt_g->execute([$periodo_id, $empresa_id]);
    $row_g = $stmt_g->fetch(PDO::FETCH_ASSOC);
    $prom_1_5 = $row_g ? (float)$row_g['promedio_global'] : 0.0;
    // Convertir escala 1-5 a 0-100: ((valor - 1) / 4) * 100
    $prom_0_100 = $prom_1_5 > 0 ? (($prom_1_5 - 1) / 4) * 100 : 0.0;
    $resumen_global = array(
        'promedio_global' => round($prom_0_100, 2),
        'promedio_1_5' => round($prom_1_5, 2)
    );

    // Resultados por Dirección (solo publicadas)
    $sql_unidades = "
        SELECT 
            u.unidad_id,
            u.nombre AS unidad_nombre,
            COUNT(DISTINCT cr.empleado_id) AS total_respondieron,
            AVG(cr.valor) AS promedio_unidad,
            cp.habilitado AS publicado
        FROM org_unidades u
        INNER JOIN clima_elegibles ce ON ce.unidad_id = u.unidad_id AND ce.periodo_id = ?
        LEFT JOIN clima_respuestas cr ON cr.periodo_id = ce.periodo_id AND cr.empleado_id = ce.empleado_id
        LEFT JOIN clima_publicacion cp ON cp.periodo_id = ce.periodo_id 
                                        AND cp.empresa_id = ce.empresa_id 
                                        AND cp.unidad_id = ce.unidad_id
        WHERE ce.empresa_id = ?
          AND ce.elegible = 1
          AND (cp.habilitado = 1 OR cp.habilitado IS NULL)
        GROUP BY u.unidad_id, u.nombre, cp.habilitado
        HAVING total_respondieron > 0
        ORDER BY promedio_unidad DESC
    ";
    $stmt_u = $pdo->prepare($sql_unidades);
    $stmt_u->execute([$periodo_id, $empresa_id]);
    $ranking_unidades = $stmt_u->fetchAll(PDO::FETCH_ASSOC);

    // Promedios por dimensión para cada unidad publicada (escala 0-100)
    foreach ($ranking_unidades as $idx => $unidad) {
        $uid = (int)$unidad['unidad_id'];
        $promedios_dim = array();

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
            $stmt_d->execute([$periodo_id, $empresa_id, $uid, $did]);
            $row_d = $stmt_d->fetch(PDO::FETCH_ASSOC);
            $prom_dim_1_5 = $row_d ? (float)$row_d['promedio'] : 0.0;
            $prom_dim_0_100 = $prom_dim_1_5 > 0 ? (($prom_dim_1_5 - 1) / 4) * 100 : 0.0;

            $promedios_dim[] = array(
                'dimension_id' => $did,
                'dimension_nombre' => $dim['nombre'],
                'promedio' => round($prom_dim_0_100, 2),
                'promedio_1_5' => round($prom_dim_1_5, 2)
            );
        }

        // Convertir promedio unidad a escala 0-100
        $prom_u_1_5 = (float)$unidad['promedio_unidad'];
        $prom_u_0_100 = $prom_u_1_5 > 0 ? (($prom_u_1_5 - 1) / 4) * 100 : 0.0;

        $ranking_unidades[$idx]['dimensiones'] = $promedios_dim;
        $ranking_unidades[$idx]['promedio_unidad'] = round($prom_u_0_100, 2);
        $ranking_unidades[$idx]['promedio_unidad_1_5'] = round($prom_u_1_5, 2);
    }

    // ===========================
    // DATOS DEMOGRÁFICOS
    // ===========================
    // Reinicializar array de demográficos
    $demograficos = array('sexo' => array(), 'edad' => array(), 'antiguedad' => array());

    // Por Sexo
    $sql_sexo = "
        SELECT 
            COALESCE(ed.sexo, 'No especificado') as categoria,
            AVG(cr.valor) AS promedio
        FROM clima_respuestas cr
        INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
        INNER JOIN empleados e ON e.empleado_id = ce.empleado_id
        LEFT JOIN empleados_demograficos ed ON ed.empleado_id = e.empleado_id
        WHERE cr.periodo_id = ?
          AND ce.empresa_id = ?
          AND ce.elegible = 1
        GROUP BY categoria
        HAVING COUNT(DISTINCT cr.empleado_id) >= 5
    ";
    $stmt_sexo = $pdo->prepare($sql_sexo);
    $stmt_sexo->execute([$periodo_id, $empresa_id]);
    $rows_sexo = $stmt_sexo->fetchAll(PDO::FETCH_ASSOC);
    $demograficos['sexo'] = array();
    foreach ($rows_sexo as $row) {
        $prom_1_5 = (float)$row['promedio'];
        $prom_0_100 = $prom_1_5 > 0 ? (($prom_1_5 - 1) / 4) * 100 : 0.0;
        $sexo_label = $row['categoria'];
        if ($sexo_label === 'M') $sexo_label = 'Masculino';
        elseif ($sexo_label === 'F') $sexo_label = 'Femenino';
        $demograficos['sexo'][] = array(
            'categoria' => $sexo_label,
            'promedio' => round($prom_0_100, 2)
        );
    }

    // Por Rango de Edad
    $sql_edad = "
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, ed.fecha_nacimiento, CURDATE()) < 25 THEN '< 25 años'
                WHEN TIMESTAMPDIFF(YEAR, ed.fecha_nacimiento, CURDATE()) BETWEEN 25 AND 34 THEN '25-34 años'
                WHEN TIMESTAMPDIFF(YEAR, ed.fecha_nacimiento, CURDATE()) BETWEEN 35 AND 44 THEN '35-44 años'
                WHEN TIMESTAMPDIFF(YEAR, ed.fecha_nacimiento, CURDATE()) BETWEEN 45 AND 54 THEN '45-54 años'
                WHEN TIMESTAMPDIFF(YEAR, ed.fecha_nacimiento, CURDATE()) >= 55 THEN '55+ años'
                ELSE 'No especificado'
            END as categoria,
            AVG(cr.valor) AS promedio
        FROM clima_respuestas cr
        INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
        INNER JOIN empleados e ON e.empleado_id = ce.empleado_id
        LEFT JOIN empleados_demograficos ed ON ed.empleado_id = e.empleado_id
        WHERE cr.periodo_id = ?
          AND ce.empresa_id = ?
          AND ce.elegible = 1
        GROUP BY categoria
        HAVING COUNT(DISTINCT cr.empleado_id) >= 5
        ORDER BY categoria
    ";
    $stmt_edad = $pdo->prepare($sql_edad);
    $stmt_edad->execute([$periodo_id, $empresa_id]);
    $rows_edad = $stmt_edad->fetchAll(PDO::FETCH_ASSOC);
    $demograficos['edad'] = array();
    foreach ($rows_edad as $row) {
        $prom_1_5 = (float)$row['promedio'];
        $prom_0_100 = $prom_1_5 > 0 ? (($prom_1_5 - 1) / 4) * 100 : 0.0;
        $demograficos['edad'][] = array(
            'categoria' => $row['categoria'],
            'promedio' => round($prom_0_100, 2)
        );
    }

    // Por Antigüedad
    $sql_antiguedad = "
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, e.fecha_ingreso, CURDATE()) < 1 THEN '< 1 año'
                WHEN TIMESTAMPDIFF(YEAR, e.fecha_ingreso, CURDATE()) BETWEEN 1 AND 3 THEN '1-3 años'
                WHEN TIMESTAMPDIFF(YEAR, e.fecha_ingreso, CURDATE()) BETWEEN 4 AND 6 THEN '4-6 años'
                WHEN TIMESTAMPDIFF(YEAR, e.fecha_ingreso, CURDATE()) BETWEEN 7 AND 10 THEN '7-10 años'
                WHEN TIMESTAMPDIFF(YEAR, e.fecha_ingreso, CURDATE()) > 10 THEN '10+ años'
                ELSE 'No especificado'
            END as categoria,
            AVG(cr.valor) AS promedio
        FROM clima_respuestas cr
        INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
        INNER JOIN empleados e ON e.empleado_id = ce.empleado_id
        WHERE cr.periodo_id = ?
          AND ce.empresa_id = ?
          AND ce.elegible = 1
          AND e.fecha_ingreso IS NOT NULL
        GROUP BY categoria
        HAVING COUNT(DISTINCT cr.empleado_id) >= 5
        ORDER BY categoria
    ";
    $stmt_antiguedad = $pdo->prepare($sql_antiguedad);
    $stmt_antiguedad->execute([$periodo_id, $empresa_id]);
    $rows_antiguedad = $stmt_antiguedad->fetchAll(PDO::FETCH_ASSOC);
    $demograficos['antiguedad'] = array();
    foreach ($rows_antiguedad as $row) {
        $prom_1_5 = (float)$row['promedio'];
        $prom_0_100 = $prom_1_5 > 0 ? (($prom_1_5 - 1) / 4) * 100 : 0.0;
        $demograficos['antiguedad'][] = array(
            'categoria' => $row['categoria'],
            'promedio' => round($prom_0_100, 2)
        );
    }
}

// =======================
// LAYOUT
// =======================
$page_title = 'Clima Laboral - Resultados';
$active_menu = 'clima_resultados';

$extra_css = [
  'global_assets/css/icons/icomoon/styles.min.css',
  'global_assets/css/plugins/tables/datatables/datatables.min.css',
];

$extra_js = [
  'global_assets/js/plugins/tables/datatables/datatables.min.js',
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
      <h4><i class="icon-graph mr-2"></i> <span class="font-weight-semibold">Clima Laboral</span> - Resultados</h4>
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

  <?php if ($periodo && $resumen_global): ?>

  <!-- Resumen global -->
  <div class="row">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-body text-center">
          <div class="mb-3">
            <h5 class="font-weight-semibold mb-0">Promedio Global de la Empresa</h5>
            <span class="text-muted">Periodo <?php echo h($periodo['anio']); ?></span>
          </div>
          <div class="svg-center position-relative" id="gauge-global" style="height: 150px;"></div>
          <h2 class="font-weight-bold mt-3 mb-0" style="font-size:3rem;">
            <?php echo number_format($resumen_global['promedio_global'], 1); ?>%
          </h2>
          <span class="text-muted">Escala 0-100</span>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <h6 class="card-title font-weight-semibold">Promedio por Dimensión</h6>
        </div>
        <div class="card-body" style="padding: 0;">
          <div id="chart-dimensiones-global" style="height: 250px;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Ranking por Dirección -->
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Resultados por Dirección</h5>
      <span class="text-muted">Solo se muestran direcciones con resultados publicados. Haz clic en una fila para ver detalles por dimensión.</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover" id="tableResultados">
        <thead>
          <tr>
            <th style="width: 50px;">#</th>
            <th>Dirección</th>
            <th class="text-center" style="width: 120px;">Respondieron</th>
            <th class="text-center" style="width: 150px;">Promedio General</th>
            <th class="text-center" style="width: 120px;">Estatus</th>
            <th class="text-center" style="width: 80px;">Detalles</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $posicion = 1;
          foreach ($ranking_unidades as $u):
            $prom_u = (float)$u['promedio_unidad'];
            $badge_color = 'success';
            if ($prom_u < 30) $badge_color = 'danger';
            elseif ($prom_u < 50) $badge_color = 'warning';
            elseif ($prom_u < 70) $badge_color = 'success';
            else $badge_color = 'primary';
          ?>
          <tr>
            <td><strong><?php echo $posicion; ?></strong></td>
            <td>
              <strong><?php echo h($u['unidad_nombre']); ?></strong>
              <div class="dimensiones-detail" id="detail-<?php echo (int)$u['unidad_id']; ?>" style="display:none; margin-top: 10px;">
                <div class="row">
                  <?php foreach ($u['dimensiones'] as $d_data): 
                    $prom_dim = (float)$d_data['promedio'];
                    $color_dim = '#EF5350';
                    if ($prom_dim >= 70) $color_dim = '#29B6F6';
                    elseif ($prom_dim >= 50) $color_dim = '#66BB6A';
                    elseif ($prom_dim >= 30) $color_dim = '#FFA726';
                  ?>
                  <div class="col-md-3 col-sm-6 mb-2">
                    <div style="padding: 8px; border-left: 3px solid <?php echo $color_dim; ?>; background: #f8f9fa;">
                      <small class="text-muted d-block" style="font-size: 0.75rem;"><?php echo h($d_data['dimension_nombre']); ?></small>
                      <strong style="font-size: 1.1rem;"><?php echo number_format($d_data['promedio'], 1); ?>%</strong>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </td>
            <td class="text-center"><?php echo (int)$u['total_respondieron']; ?></td>
            <td class="text-center">
              <span class="badge badge-<?php echo $badge_color; ?>" style="font-size:1.2rem; padding:0.6rem 1.2rem;">
                <?php echo number_format($prom_u, 1); ?>%
              </span>
            </td>
            <td class="text-center">
              <?php if ((int)$u['publicado'] === 1): ?>
              <span class="badge badge-info">Publicado</span>
              <?php else: ?>
              <span class="badge badge-secondary">No publicado</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleDetail(<?php echo (int)$u['unidad_id']; ?>)">
                <i class="icon-eye" id="icon-<?php echo (int)$u['unidad_id']; ?>"></i>
              </button>
            </td>
          </tr>
          <?php
            $posicion++;
          endforeach;
          ?>
          <?php if (empty($ranking_unidades)): ?>
          <tr><td colspan="6" class="text-center text-muted">No hay resultados disponibles o publicados para este periodo</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Gráfico comparativo -->
  <?php if (!empty($ranking_unidades)): ?>
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Comparativo por Dimensión y Dirección</h5>
    </div>
    <div class="card-body">
      <div class="chart-container">
        <div class="chart" id="chart-dimensiones" style="height: 450px;"></div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Análisis Demográfico -->
  <?php if (!empty($demograficos) && (count($demograficos['sexo']) > 0 || count($demograficos['edad']) > 0 || count($demograficos['antiguedad']) > 0)): ?>
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Análisis Demográfico</h5>
      <span class="text-muted">Solo se muestran grupos con 5 o más participantes para proteger confidencialidad</span>
    </div>
    <div class="card-body">
      <div class="row">
        <?php if (count($demograficos['sexo']) > 0): ?>
        <div class="col-lg-4">
          <h6 class="font-weight-semibold text-center">Por Sexo</h6>
          <div id="chart-sexo" style="height: 300px;"></div>
        </div>
        <?php endif; ?>
        
        <?php if (count($demograficos['edad']) > 0): ?>
        <div class="col-lg-4">
          <h6 class="font-weight-semibold text-center">Por Rango de Edad</h6>
          <div id="chart-edad" style="height: 300px;"></div>
        </div>
        <?php endif; ?>
        
        <?php if (count($demograficos['antiguedad']) > 0): ?>
        <div class="col-lg-4">
          <h6 class="font-weight-semibold text-center">Por Antigüedad</h6>
          <div id="chart-antiguedad" style="height: 300px;"></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="alert alert-warning">
    <strong>Sin datos:</strong> No hay información disponible para el periodo seleccionado o no se han publicado resultados.
  </div>
  <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/layout/scripts.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
?>

<script>
// Función para toggle de detalles por dimensión
function toggleDetail(unidadId) {
  var detail = document.getElementById('detail-' + unidadId);
  var icon = document.getElementById('icon-' + unidadId);
  
  if (detail.style.display === 'none') {
    detail.style.display = 'block';
    icon.className = 'icon-eye-blocked';
  } else {
    detail.style.display = 'none';
    icon.className = 'icon-eye';
  }
}

$(document).ready(function() {
  
  // DataTable
  if ($('#tableResultados tbody tr').length > 1) {
    $('#tableResultados').DataTable({
      language: { url: '<?php echo ASSET_BASE; ?>global_assets/js/plugins/tables/datatables/Spanish.json' },
      pageLength: 25,
      order: [[3, 'desc']],
      columnDefs: [
        { orderable: false, targets: [5] }
      ]
    });
  }

// Gauge global (escala 0-100)
<?php if ($periodo && $resumen_global): ?>
var promedioGlobal = <?php echo (float)$resumen_global['promedio_global']; ?>;
if (document.getElementById('gauge-global')) {
  var gaugeGlobal = echarts.init(document.getElementById('gauge-global'));
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
      data: [{ value: promedioGlobal.toFixed(1) }]
    }]
  };
  gaugeGlobal.setOption(optionGauge);
}

// Gráfico de dimensiones global (promedio por dimensión)
<?php
$promedios_dim_global = array();
foreach ($dimensiones as $dim) {
    $did = (int)$dim['dimension_id'];
    $sql_dim_global = "
        SELECT AVG(cr.valor) AS promedio
        FROM clima_respuestas cr
        INNER JOIN clima_reactivos crt ON crt.reactivo_id = cr.reactivo_id
        INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
        WHERE cr.periodo_id = ?
          AND ce.empresa_id = ?
          AND ce.elegible = 1
          AND crt.dimension_id = ?
    ";
    $stmt_dg = $pdo->prepare($sql_dim_global);
    $stmt_dg->execute([$periodo_id, $empresa_id, $did]);
    $row_dg = $stmt_dg->fetch(PDO::FETCH_ASSOC);
    $prom_1_5 = $row_dg ? (float)$row_dg['promedio'] : 0.0;
    $prom_0_100 = $prom_1_5 > 0 ? (($prom_1_5 - 1) / 4) * 100 : 0.0;
    $promedios_dim_global[] = array(
        'nombre' => $dim['nombre'],
        'promedio' => round($prom_0_100, 2)
    );
}
?>
if (document.getElementById('chart-dimensiones-global')) {
  var chartDimGlobal = echarts.init(document.getElementById('chart-dimensiones-global'));
  var optionDimGlobal = {
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
      data: <?php echo json_encode(array_map(function($d) { return $d['nombre']; }, $promedios_dim_global), JSON_UNESCAPED_UNICODE); ?>,
      axisLabel: {
        interval: 0,
        fontSize: 11
      }
    },
    series: [{
      type: 'bar',
      data: <?php echo json_encode(array_map(function($d) { return $d['promedio']; }, $promedios_dim_global)); ?>,
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
        fontSize: 11
      }
    }]
  };
  chartDimGlobal.setOption(optionDimGlobal);
}
<?php endif; ?>

// Gráfico comparativo por dimensión (escala 0-100)
<?php if (!empty($ranking_unidades)): ?>
if (document.getElementById('chart-dimensiones')) {
  var unidades = <?php echo json_encode(array_map(function($u) { return $u['unidad_nombre']; }, $ranking_unidades), JSON_UNESCAPED_UNICODE); ?>;
  var dimensionesNombres = <?php echo json_encode(array_map(function($d) { return $d['nombre']; }, $dimensiones), JSON_UNESCAPED_UNICODE); ?>;

  var series = [];
  <?php foreach ($dimensiones as $idx_dim => $dim): ?>
  var data_<?php echo $idx_dim; ?> = [
    <?php foreach ($ranking_unidades as $u): ?>
      <?php
      $prom_found = 0.0;
      foreach ($u['dimensiones'] as $dd) {
          if ((int)$dd['dimension_id'] === (int)$dim['dimension_id']) {
              $prom_found = (float)$dd['promedio'];
              break;
          }
      }
      echo $prom_found . ',';
      ?>
    <?php endforeach; ?>
  ];
  series.push({
    name: dimensionesNombres[<?php echo $idx_dim; ?>],
    type: 'bar',
    data: data_<?php echo $idx_dim; ?>
  });
  <?php endforeach; ?>

  var chartDimensiones = echarts.init(document.getElementById('chart-dimensiones'));
  var optionBar = {
    grid: {
      left: '3%',
      right: '4%',
      bottom: '20%',
      containLabel: true
    },
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'shadow' },
      formatter: function(params) {
        var result = params[0].axisValue + '<br/>';
        params.forEach(function(item) {
          result += item.marker + ' ' + item.seriesName + ': ' + item.value.toFixed(1) + '%<br/>';
        });
        return result;
      }
    },
    legend: {
      data: dimensionesNombres,
      bottom: 0,
      type: 'scroll'
    },
    xAxis: {
      type: 'category',
      data: unidades,
      axisLabel: {
        rotate: 45,
        interval: 0,
        fontSize: 10
      }
    },
    yAxis: {
      type: 'value',
      min: 0,
      max: 100,
      axisLabel: {
        formatter: '{value}%'
      }
    },
    series: series
  };
  chartDimensiones.setOption(optionBar);
}
<?php endif; ?>

// Gráficos demográficos
<?php if (!empty($demograficos)): ?>

<?php if (count($demograficos['sexo']) > 0): ?>
if (document.getElementById('chart-sexo')) {
  var chartSexo = echarts.init(document.getElementById('chart-sexo'));
  var optionSexo = {
    tooltip: {
      trigger: 'item',
      formatter: '{b}: {c}%'
    },
    series: [{
      type: 'pie',
      radius: '60%',
      data: <?php echo json_encode(array_map(function($d) { 
        return array('name' => $d['categoria'], 'value' => $d['promedio']); 
      }, $demograficos['sexo']), JSON_UNESCAPED_UNICODE); ?>,
      label: {
        formatter: '{b}\n{c}%'
      },
      itemStyle: {
        color: function(params) {
          var colors = ['#5470C6', '#EE6666', '#91CC75'];
          return colors[params.dataIndex % colors.length];
        }
      }
    }]
  };
  chartSexo.setOption(optionSexo);
}
<?php endif; ?>

<?php if (count($demograficos['edad']) > 0): ?>
if (document.getElementById('chart-edad')) {
  var chartEdad = echarts.init(document.getElementById('chart-edad'));
  var optionEdad = {
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'shadow' },
      formatter: '{b}: {c}%'
    },
    grid: {
      left: '10%',
      right: '10%',
      bottom: '15%',
      top: '5%',
      containLabel: true
    },
    xAxis: {
      type: 'category',
      data: <?php echo json_encode(array_map(function($d) { return $d['categoria']; }, $demograficos['edad']), JSON_UNESCAPED_UNICODE); ?>,
      axisLabel: {
        rotate: 30,
        fontSize: 10
      }
    },
    yAxis: {
      type: 'value',
      min: 0,
      max: 100,
      axisLabel: {
        formatter: '{value}%'
      }
    },
    series: [{
      type: 'bar',
      data: <?php echo json_encode(array_map(function($d) { return $d['promedio']; }, $demograficos['edad'])); ?>,
      itemStyle: {
        color: '#5470C6'
      },
      label: {
        show: true,
        position: 'top',
        formatter: '{c}%'
      }
    }]
  };
  chartEdad.setOption(optionEdad);
}
<?php endif; ?>

<?php if (count($demograficos['antiguedad']) > 0): ?>
if (document.getElementById('chart-antiguedad')) {
  var chartAntiguedad = echarts.init(document.getElementById('chart-antiguedad'));
  var optionAntiguedad = {
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'shadow' },
      formatter: '{b}: {c}%'
    },
    grid: {
      left: '10%',
      right: '10%',
      bottom: '15%',
      top: '5%',
      containLabel: true
    },
    xAxis: {
      type: 'category',
      data: <?php echo json_encode(array_map(function($d) { return $d['categoria']; }, $demograficos['antiguedad']), JSON_UNESCAPED_UNICODE); ?>,
      axisLabel: {
        rotate: 30,
        fontSize: 10
      }
    },
    yAxis: {
      type: 'value',
      min: 0,
      max: 100,
      axisLabel: {
        formatter: '{value}%'
      }
    },
    series: [{
      type: 'bar',
      data: <?php echo json_encode(array_map(function($d) { return $d['promedio']; }, $demograficos['antiguedad'])); ?>,
      itemStyle: {
        color: '#91CC75'
      },
      label: {
        show: true,
        position: 'top',
        formatter: '{c}%'
      }
    }]
  };
  chartAntiguedad.setOption(optionAntiguedad);
}
<?php endif; ?>

<?php endif; ?>

}); // Fin de $(document).ready()
</script>

<?php
require_once __DIR__ . '/../includes/layout/scripts.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
?>
