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
require_demograficos_redirect();

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
// DATOS GENERALES DE LA ENCUESTA
// =======================
$datos_encuesta = array(
    'universo_aplicable' => 0,
    'encuestas_respondidas' => 0,
    'porcentaje_participacion' => 0,
    'fecha_inicio' => null,
    'fecha_fin' => null
);

if ($periodo) {
    // Total de elegibles en la empresa
    $stmt_uni = $pdo->prepare("
        SELECT COUNT(DISTINCT empleado_id) as total
        FROM clima_elegibles
        WHERE periodo_id = ? AND empresa_id = ? AND elegible = 1
    ");
    $stmt_uni->execute([$periodo_id, $empresa_id]);
    $row_uni = $stmt_uni->fetch(PDO::FETCH_ASSOC);
    $datos_encuesta['universo_aplicable'] = (int)$row_uni['total'];
    
    // Total de respuestas únicas
    $stmt_resp = $pdo->prepare("
        SELECT COUNT(DISTINCT cr.empleado_id) as total
        FROM clima_respuestas cr
        INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
        WHERE cr.periodo_id = ? AND ce.empresa_id = ? AND ce.elegible = 1
    ");
    $stmt_resp->execute([$periodo_id, $empresa_id]);
    $row_resp = $stmt_resp->fetch(PDO::FETCH_ASSOC);
    $datos_encuesta['encuestas_respondidas'] = (int)$row_resp['total'];
    
    // Calcular porcentaje
    if ($datos_encuesta['universo_aplicable'] > 0) {
        $datos_encuesta['porcentaje_participacion'] = round(
            ($datos_encuesta['encuestas_respondidas'] / $datos_encuesta['universo_aplicable']) * 100, 
            1
        );
    }
    
    // Fechas del período
    $datos_encuesta['fecha_inicio'] = $periodo['fecha_inicio'];
    $datos_encuesta['fecha_fin'] = $periodo['fecha_fin'];
}

// =======================
// RESULTADOS POR EMPRESA Y UNIDAD
// =======================
$resultados_empresa = null;
$resultados_unidad = null;
$promedios_dimensiones_empresa = array();
$promedios_dimensiones_unidad = array();

if ($periodo) {
    // =======================
    // RESULTADOS POR EMPRESA
    // =======================
    $sql_empresa = "
        SELECT 
            COUNT(DISTINCT cr.empleado_id) AS total_respondieron,
            AVG(cr.valor) AS promedio_empresa
        FROM clima_respuestas cr
        INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
        WHERE cr.periodo_id = ?
          AND ce.empresa_id = ?
          AND ce.elegible = 1
    ";
    $stmt_e = $pdo->prepare($sql_empresa);
    $stmt_e->execute([$periodo_id, $empresa_id]);
    $row_e = $stmt_e->fetch(PDO::FETCH_ASSOC);
    
    if ($row_e && (int)$row_e['total_respondieron'] > 0) {
        $prom_1_5 = (float)$row_e['promedio_empresa'];
        $prom_0_100 = $prom_1_5 > 0 ? (($prom_1_5 - 1) / 4) * 100 : 0.0;
        
        $resultados_empresa = array(
            'total_respondieron' => (int)$row_e['total_respondieron'],
            'promedio_empresa' => round($prom_0_100, 2)
        );

        // Promedios por dimensión - Empresa
        foreach ($dimensiones as $dim) {
            $did = (int)$dim['dimension_id'];

            $sql_dim_empresa = "
                SELECT AVG(cr.valor) AS promedio
                FROM clima_respuestas cr
                INNER JOIN clima_reactivos crt ON crt.reactivo_id = cr.reactivo_id
                INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
                WHERE cr.periodo_id = ?
                  AND ce.empresa_id = ?
                  AND ce.elegible = 1
                  AND crt.dimension_id = ?
            ";
            $stmt_d_e = $pdo->prepare($sql_dim_empresa);
            $stmt_d_e->execute([$periodo_id, $empresa_id, $did]);
            $row_d_e = $stmt_d_e->fetch(PDO::FETCH_ASSOC);
            $prom_dim_1_5 = $row_d_e ? (float)$row_d_e['promedio'] : 0.0;
            $prom_dim_0_100 = $prom_dim_1_5 > 0 ? (($prom_dim_1_5 - 1) / 4) * 100 : 0.0;

            $promedios_dimensiones_empresa[] = array(
                'dimension_id' => $did,
                'dimension_nombre' => $dim['nombre'],
                'promedio' => round($prom_dim_0_100, 2)
            );
        }
    }

    // =======================
    // RESULTADOS POR UNIDAD (MI UNIDAD)
    // =======================
    if ($mi_unidad_id > 0) {
        $sql_unidad = "
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
        $stmt_u = $pdo->prepare($sql_unidad);
        $stmt_u->execute([$periodo_id, $empresa_id, $mi_unidad_id]);
        $row_u = $stmt_u->fetch(PDO::FETCH_ASSOC);
        
        if ($row_u && (int)$row_u['total_respondieron'] > 0) {
            $prom_1_5 = (float)$row_u['promedio_unidad'];
            $prom_0_100 = $prom_1_5 > 0 ? (($prom_1_5 - 1) / 4) * 100 : 0.0;
            
            $resultados_unidad = array(
                'total_respondieron' => (int)$row_u['total_respondieron'],
                'promedio_unidad' => round($prom_0_100, 2)
            );

            // Promedios por dimensión - Unidad
            foreach ($dimensiones as $dim) {
                $did = (int)$dim['dimension_id'];

                $sql_dim_unidad = "
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
                $stmt_d_u = $pdo->prepare($sql_dim_unidad);
                $stmt_d_u->execute([$periodo_id, $empresa_id, $mi_unidad_id, $did]);
                $row_d_u = $stmt_d_u->fetch(PDO::FETCH_ASSOC);
                $prom_dim_1_5 = $row_d_u ? (float)$row_d_u['promedio'] : 0.0;
                $prom_dim_0_100 = $prom_dim_1_5 > 0 ? (($prom_dim_1_5 - 1) / 4) * 100 : 0.0;

                $promedios_dimensiones_unidad[] = array(
                    'dimension_id' => $did,
                    'dimension_nombre' => $dim['nombre'],
                    'promedio' => round($prom_dim_0_100, 2)
                );
            }
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

  <!-- Información introductoria y metodología -->
  <div class="alert alert-light border-left-3 border-left-primary mb-3">
    <div class="d-flex">
      <i class="icon-heart icon-2x text-primary mr-3"></i>
      <div>
        <h5 class="font-weight-bold mb-3">¡Bienvenido! Tu opinión es muy importante para nosotros</h5>
        
        <p class="mb-3">
          <strong>Nuestro Modelo de Clima Organizacional:</strong> FH ha diseñado un modelo para el estudio de su Clima Organizacional, 
          basado en los modelos de Empresas Internacionales Great Place to Work y Top Companies, fundamentado en el desarrollo e interacción 
          de cuatro principales relaciones en el lugar de trabajo.
        </p>

        <h6 class="font-weight-semibold mb-2">Interpretación de Resultados</h6>
        <p class="mb-2">
          <strong style="color: #29B6F6;">✓ Resultado Sobresaliente (≥ 85%):</strong> 
          Se presenta cuando la línea actual sobrepasa el ideal. El clima percibido es óptimo y estos resultados se consideran 
          <strong>fortalezas del clima del área.</strong>
        </p>
        <p class="mb-2">
          <strong style="color: #66BB6A;">✓ Resultado Satisfactorio (75-84%):</strong> 
          Se identifica cuando la línea actual está por debajo de 85 y por arriba de 75 puntos. Si la brecha tiende a disminuir, 
          el clima percibido es <strong>positivo</strong>. De lo contrario, genera evidencia de pérdida de motivación y exige 
          <strong>mayor atención.</strong>
        </p>
        <p class="mb-0">
          <strong style="color: #EF5350;">⚠ Resultado Deficiente (< 65%):</strong> 
          Se presenta cuando la línea actual cae por debajo de 65 puntos, indicando una <strong>pérdida de potencial y rendimiento.</strong> 
          El clima percibido es <strong>negativo</strong> y <strong>requiere atención inmediata.</strong>
        </p>
      </div>
    </div>
  </div>

  <?php if (!empty($periodos)): ?>
  <!-- Selector de periodo -->
  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="form-inline">
        <label class="mr-2 font-weight-semibold">Seleccionar período:</label>
        <select name="periodo_id" class="form-control" style="width: 150px;" onchange="this.form.submit()">
          <?php foreach ($periodos as $p): ?>
          <option value="<?php echo (int)$p['periodo_id']; ?>" <?php echo ((int)$p['periodo_id'] === $periodo_id) ? 'selected' : ''; ?>>
            <?php echo h($p['anio']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if ($periodo): ?>
  <!-- Datos de la Encuesta -->
  <div class="row mb-3">
    <div class="col-md-3">
      <div class="card bg-light border-left-3 border-left-primary">
        <div class="card-body text-center p-3">
          <small class="text-muted d-block text-uppercase">Período de Evaluación</small>
          <h5 class="font-weight-bold mt-2 mb-0">
            <?php echo h($periodo['anio']); ?>
          </h5>
          <small class="text-muted d-block mt-1">
            <?php echo $periodo['fecha_inicio'] ? date('d/m/Y', strtotime($periodo['fecha_inicio'])) : 'N/A'; ?>
            -
            <?php echo $periodo['fecha_fin'] ? date('d/m/Y', strtotime($periodo['fecha_fin'])) : 'N/A'; ?>
          </small>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card bg-light border-left-3 border-left-info">
        <div class="card-body text-center p-3">
          <small class="text-muted d-block text-uppercase">Universo Aplicable</small>
          <h3 class="font-weight-bold mt-2 mb-0" style="color: #29B6F6;">
            <?php echo $datos_encuesta['universo_aplicable']; ?>
          </h3>
          <small class="text-muted d-block">empleados elegibles</small>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card bg-light border-left-3 border-left-success">
        <div class="card-body text-center p-3">
          <small class="text-muted d-block text-uppercase">Encuestas Respondidas</small>
          <h3 class="font-weight-bold mt-2 mb-0" style="color: #66BB6A;">
            <?php echo $datos_encuesta['encuestas_respondidas']; ?>
          </h3>
          <small class="text-muted d-block">respuestas recibidas</small>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card bg-light border-left-3" style="border-left-color: <?php 
        $pct = $datos_encuesta['porcentaje_participacion'];
        echo ($pct >= 90) ? '#29B6F6' : (($pct >= 70) ? '#66BB6A' : (($pct >= 50) ? '#FFA726' : '#EF5350'));
      ?> !important;">
        <div class="card-body text-center p-3">
          <small class="text-muted d-block text-uppercase">% Participación</small>
          <h3 class="font-weight-bold mt-2 mb-0" style="color: <?php 
            $pct = $datos_encuesta['porcentaje_participacion'];
            echo ($pct >= 90) ? '#29B6F6' : (($pct >= 70) ? '#66BB6A' : (($pct >= 50) ? '#FFA726' : '#EF5350'));
          ?>;">
            <?php echo number_format($datos_encuesta['porcentaje_participacion'], 1); ?>%
          </h3>
          <small class="text-muted d-block">
            <?php 
              $pct = $datos_encuesta['porcentaje_participacion'];
              if ($pct >= 90) echo '✓ Excelente';
              elseif ($pct >= 70) echo '✓ Bueno';
              elseif ($pct >= 50) echo '⚠ Regular';
              else echo '✗ Bajo';
            ?>
          </small>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($periodo && $resultados_empresa): ?>

  <!-- ======================== RESULTADOS EMPRESA VS ÁREA ======================== -->
  <div style="margin-bottom: 30px;">
    <h5 class="mb-3 font-weight-semibold">
      <i class="icon-chart"></i> Comparativa: Empresa vs Mi Área
    </h5>

    <!-- Resumen comparativo -->
    <div class="row">
      <div class="col-lg-6">
        <div class="card border-left-3 border-left-info">
          <div class="card-body text-center pb-2">
            <small class="text-muted d-block mb-2">PROMEDIO DE LA EMPRESA</small>
            <h2 class="font-weight-bold mb-0" style="font-size: 2.5rem; color: #29B6F6;">
              <?php echo number_format($resultados_empresa['promedio_empresa'], 1); ?>%
            </h2>
            <small class="text-muted d-block">
              <i class="icon-users"></i> <?php echo $resultados_empresa['total_respondieron']; ?> respondentes
            </small>
            <div id="gauge-empresa" style="height: 120px; margin-top: 10px;"></div>
          </div>
        </div>
      </div>

      <?php if ($resultados_unidad): ?>
      <div class="col-lg-6">
        <div class="card border-left-3 border-left-success">
          <div class="card-body text-center pb-2">
            <small class="text-muted d-block mb-2">MI ÁREA: <?php echo h(strtoupper($mi_empleado['unidad_nombre'])); ?></small>
            <h2 class="font-weight-bold mb-0" style="font-size: 2.5rem; color: #66BB6A;">
              <?php echo number_format($resultados_unidad['promedio_unidad'], 1); ?>%
            </h2>
            <small class="text-muted d-block">
              <i class="icon-users"></i> <?php echo $resultados_unidad['total_respondieron']; ?> respondentes
            </small>
            <div id="gauge-unidad" style="height: 120px; margin-top: 10px;"></div>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="col-lg-6">
        <div class="card bg-light">
          <div class="card-body text-center">
            <small class="text-muted d-block mb-2">MI ÁREA</small>
            <p class="text-muted mb-0">Datos insuficientes</p>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Gráfico comparativo por dimensiones -->
    <div class="card mt-3">
      <div class="card-body" style="padding: 15px;">
        <h6 class="font-weight-semibold mb-3">Resultados por Dimensión</h6>
        <div id="chart-comparativa" style="height: 280px;"></div>
      </div>
    </div>

    <!-- Detalle tabla -->
    <div class="card mt-3">
      <div class="card-body" style="padding: 15px;">
        <div class="table-responsive">
          <table class="table table-sm table-borderless mb-0">
            <thead style="background-color: #f8f9fa;">
              <tr>
                <th style="width: 40%;">Dimensión</th>
                <th class="text-center" style="width: 20%;">Empresa</th>
                <th class="text-center" style="width: 20%;">Mi Área</th>
                <th class="text-center" style="width: 20%;">Diferencia</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $dim_empresa_map = array();
              foreach ($promedios_dimensiones_empresa as $d) {
                $dim_empresa_map[$d['dimension_id']] = $d;
              }
              
              $dim_area_map = array();
              foreach ($promedios_dimensiones_unidad as $d) {
                $dim_area_map[$d['dimension_id']] = $d;
              }

              // Mostrar todas las dimensiones
              $all_dims = array_unique(array_merge(array_keys($dim_empresa_map), array_keys($dim_area_map)));
              sort($all_dims);

              foreach ($all_dims as $dim_id):
                $empresa_data = isset($dim_empresa_map[$dim_id]) ? $dim_empresa_map[$dim_id] : null;
                $area_data = isset($dim_area_map[$dim_id]) ? $dim_area_map[$dim_id] : null;
                
                $emp_val = $empresa_data ? $empresa_data['promedio'] : 0;
                $area_val = $area_data ? $area_data['promedio'] : 0;
                $diff = $area_val - $emp_val;
                
                $dim_nombre = $empresa_data ? $empresa_data['dimension_nombre'] : ($area_data ? $area_data['dimension_nombre'] : 'N/A');
              ?>
              <tr>
                <td class="font-weight-500"><?php echo h($dim_nombre); ?></td>
                <td class="text-center">
                  <span class="badge" style="background-color: <?php echo $emp_val >= 70 ? '#29B6F6' : ($emp_val >= 50 ? '#66BB6A' : ($emp_val >= 30 ? '#FFA726' : '#EF5350')); ?>; color: white;">
                    <?php echo number_format($emp_val, 1); ?>%
                  </span>
                </td>
                <td class="text-center">
                  <?php if ($area_val > 0): ?>
                  <span class="badge" style="background-color: <?php echo $area_val >= 70 ? '#29B6F6' : ($area_val >= 50 ? '#66BB6A' : ($area_val >= 30 ? '#FFA726' : '#EF5350')); ?>; color: white;">
                    <?php echo number_format($area_val, 1); ?>%
                  </span>
                  <?php else: ?>
                  <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if ($area_val > 0): ?>
                    <?php if ($diff > 0): ?>
                      <span class="text-success font-weight-600">+<?php echo number_format($diff, 1); ?>%</span>
                    <?php elseif ($diff < 0): ?>
                      <span class="text-danger font-weight-600"><?php echo number_format($diff, 1); ?>%</span>
                    <?php else: ?>
                      <span class="text-muted">=</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <?php elseif ($periodo): ?>
  <div class="alert alert-warning">
    <strong>Sin respuestas:</strong> La empresa aún no tiene respuestas suficientes para mostrar resultados en este periodo.
  </div>

  <?php elseif ($periodo): ?>
  <div class="alert alert-warning">
    <strong>Sin respuestas:</strong> Tu Dirección aún no tiene respuestas suficientes para mostrar resultados en este periodo.
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="alert alert-info">
    <i class="icon-info22 mr-2"></i>
    <strong>Resultados no disponibles:</strong> Los resultados de clima laboral de tu Dirección aún no han sido publicados por Recursos Humanos.
    <div class="mt-2 text-muted" style="font-size: 0.9rem;">
      Los resultados solo se muestran cuando:
      <ul class="mb-0 mt-1">
        <li>El periodo de medición ha concluido</li>
        <li>La participación de tu Dirección alcanzó el umbral mínimo (90%)</li>
        <li>El administrador habilitó la visualización de resultados</li>
      </ul>
    </div>
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
  
<?php if ($periodo && $resultados_empresa): ?>

  // ======================== GAUGE EMPRESA ========================
  var promedioEmpresa = <?php echo (float)$resultados_empresa['promedio_empresa']; ?>;
  if (document.getElementById('gauge-empresa')) {
    var gaugeEmpresa = echarts.init(document.getElementById('gauge-empresa'));
    var optionGaugeEmpresa = {
      series: [{
        type: 'gauge',
        startAngle: 200,
        endAngle: -20,
        min: 0,
        max: 100,
        splitNumber: 4,
        axisLine: {
          lineStyle: {
            width: 10,
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
        data: [{ value: promedioEmpresa.toFixed(1) }]
      }]
    };
    gaugeEmpresa.setOption(optionGaugeEmpresa);
  }

<?php endif; ?>

<?php if ($periodo && $resultados_unidad): ?>

  // ======================== GAUGE UNIDAD ========================
  var promedioUnidad = <?php echo (float)$resultados_unidad['promedio_unidad']; ?>;
  if (document.getElementById('gauge-unidad')) {
    var gaugeUnidad = echarts.init(document.getElementById('gauge-unidad'));
    var optionGaugeUnidad = {
      series: [{
        type: 'gauge',
        startAngle: 200,
        endAngle: -20,
        min: 0,
        max: 100,
        splitNumber: 4,
        axisLine: {
          lineStyle: {
            width: 10,
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
        data: [{ value: promedioUnidad.toFixed(1) }]
      }]
    };
    gaugeUnidad.setOption(optionGaugeUnidad);
  }

<?php endif; ?>

<?php if ($periodo && $resultados_empresa): ?>

  // ======================== GRÁFICO COMPARATIVO ========================
  if (document.getElementById('chart-comparativa')) {
    var chartComparativa = echarts.init(document.getElementById('chart-comparativa'));
    
    // Construir datos para el gráfico
    var datosEmpresa = [];
    var datosArea = [];
    var labels = [];
    
    <?php 
    $dim_empresa_map = array();
    foreach ($promedios_dimensiones_empresa as $d) {
      $dim_empresa_map[$d['dimension_id']] = $d;
    }
    
    $dim_area_map = array();
    foreach ($promedios_dimensiones_unidad as $d) {
      $dim_area_map[$d['dimension_id']] = $d;
    }

    $all_dims = array_unique(array_merge(array_keys($dim_empresa_map), array_keys($dim_area_map)));
    sort($all_dims);
    ?>
    
    <?php foreach ($all_dims as $dim_id):
      $empresa_data = isset($dim_empresa_map[$dim_id]) ? $dim_empresa_map[$dim_id] : null;
      $area_data = isset($dim_area_map[$dim_id]) ? $dim_area_map[$dim_id] : null;
    ?>
    labels.push('<?php echo addslashes($empresa_data ? $empresa_data['dimension_nombre'] : ($area_data ? $area_data['dimension_nombre'] : '')); ?>');
    datosEmpresa.push(<?php echo $empresa_data ? $empresa_data['promedio'] : 0; ?>);
    datosArea.push(<?php echo $area_data ? $area_data['promedio'] : 0; ?>);
    <?php endforeach; ?>
    
    var optionComparativa = {
      grid: {
        left: '5%',
        right: '5%',
        bottom: '10%',
        top: '5%',
        containLabel: true
      },
      legend: {
        data: ['Empresa', 'Mi Área'],
        bottom: 0,
        textStyle: { fontSize: 11 }
      },
      tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        formatter: function(params) {
          var str = params[0].name + '<br/>';
          for (var i = 0; i < params.length; i++) {
            str += params[i].seriesName + ': ' + params[i].value.toFixed(1) + '%<br/>';
          }
          return str;
        }
      },
      xAxis: {
        type: 'category',
        data: labels,
        axisLabel: {
          interval: 0,
          fontSize: 9,
          rotate: 45
        }
      },
      yAxis: {
        type: 'value',
        min: 0,
        max: 100,
        axisLabel: {
          formatter: '{value}%',
          fontSize: 9
        }
      },
      series: [
        {
          name: 'Empresa',
          type: 'bar',
          data: datosEmpresa,
          itemStyle: { color: '#29B6F6' },
          barWidth: '40%',
          label: {
            show: false
          }
        },
        {
          name: 'Mi Área',
          type: 'bar',
          data: datosArea,
          itemStyle: { color: '#66BB6A' },
          label: {
            show: false
          }
        }
      ]
    };
    chartComparativa.setOption(optionComparativa);
  }

<?php endif; ?>

});
</script>
