<?php
// public/clima.php
// SGRH - Clima Laboral - Menú Principal
// Hub de navegación para todo el módulo de clima laboral
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
$es_admin = can('organizacion.admin');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// =======================
// ESTADÍSTICAS RÁPIDAS
// =======================
$stats = array(
    'periodos_activos' => 0,
    'total_respuestas' => 0,
    'promedio_general' => 0.0,
    'planes_pendientes' => 0
);

if ($empresa_id > 0) {
    // Periodos publicados o en borrador
    $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM clima_periodos WHERE empresa_id=? AND estatus IN ('borrador','publicado')");
    $stmt1->execute([$empresa_id]);
    $stats['periodos_activos'] = (int)$stmt1->fetchColumn();

    // Total respuestas en periodos activos
    $stmt2 = $pdo->prepare("
        SELECT COUNT(*)
        FROM clima_respuestas cr
        INNER JOIN clima_periodos cp ON cp.periodo_id = cr.periodo_id
        INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
        WHERE ce.empresa_id = ?
          AND cp.estatus IN ('borrador','publicado')
          AND ce.elegible = 1
    ");
    $stmt2->execute([$empresa_id]);
    $stats['total_respuestas'] = (int)$stmt2->fetchColumn();

    // Promedio general (último periodo publicado)
    $stmt3 = $pdo->prepare("
        SELECT AVG(cr.valor)
        FROM clima_respuestas cr
        INNER JOIN clima_periodos cp ON cp.periodo_id = cr.periodo_id
        INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
        WHERE ce.empresa_id = ?
          AND cp.estatus = 'publicado'
          AND ce.elegible = 1
        ORDER BY cp.anio DESC
        LIMIT 1
    ");
    $stmt3->execute([$empresa_id]);
    $prom = $stmt3->fetchColumn();
    $stats['promedio_general'] = $prom ? round((float)$prom, 2) : 0.0;

    // Planes pendientes
    $stmt4 = $pdo->prepare("SELECT COUNT(*) FROM clima_planes WHERE empresa_id=? AND estatus IN ('pendiente','en_proceso')");
    $stmt4->execute([$empresa_id]);
    $stats['planes_pendientes'] = (int)$stmt4->fetchColumn();
}

// =======================
// PERIODO ACTIVO
// =======================
$periodo_activo = null;
if ($empresa_id > 0) {
    $stmt_p = $pdo->prepare("
        SELECT periodo_id, anio, fecha_inicio, fecha_fin, estatus
        FROM clima_periodos
        WHERE empresa_id=? AND estatus IN ('borrador','publicado')
        ORDER BY anio DESC
        LIMIT 1
    ");
    $stmt_p->execute([$empresa_id]);
    $periodo_activo = $stmt_p->fetch(PDO::FETCH_ASSOC);
}

// =======================
// ELEGIBILIDAD USUARIO
// =======================
$soy_elegible = false;
$ya_respondio = false;
$periodo_contestar = null;
if ($empresa_id > 0 && $usuario_id > 0 && $periodo_activo) {
    // Resolver empleado_id
    $stmt_emp = $pdo->prepare("SELECT empleado_id FROM usuario_empresas WHERE usuario_id=? AND empresa_id=? AND estatus=1 LIMIT 1");
    $stmt_emp->execute([$usuario_id, $empresa_id]);
    $empleado_id = (int)$stmt_emp->fetchColumn();

    if ($empleado_id > 0) {
        $stmt_el = $pdo->prepare("SELECT elegible FROM clima_elegibles WHERE periodo_id=? AND empleado_id=? AND empresa_id=? LIMIT 1");
        $stmt_el->execute([(int)$periodo_activo['periodo_id'], $empleado_id, $empresa_id]);
        $row_el = $stmt_el->fetch(PDO::FETCH_ASSOC);
        if ($row_el && (int)$row_el['elegible'] === 1) {
            $soy_elegible = true;
            $periodo_contestar = $periodo_activo;

            // Verificar si ya respondió
            $stmt_resp = $pdo->prepare("SELECT COUNT(*) FROM clima_respuestas WHERE periodo_id=? AND empleado_id=?");
            $stmt_resp->execute([(int)$periodo_activo['periodo_id'], $empleado_id]);
            $ya_respondio = ((int)$stmt_resp->fetchColumn() > 0);
        }
    }
}

// =======================
// LAYOUT
// =======================
$page_title = 'Clima Laboral';
$active_menu = 'clima';

$extra_css = [
  'global_assets/css/icons/icomoon/styles.min.css',
];

require_once __DIR__ . '/../includes/layout/head.php';
require_once __DIR__ . '/../includes/layout/navbar.php';
require_once __DIR__ . '/../includes/layout/sidebar.php';
require_once __DIR__ . '/../includes/layout/content_open.php';
?>

<div class="page-header page-header-light">
  <div class="page-header-content header-elements-md-inline">
    <div class="page-title d-flex">
      <h4><i class="icon-pulse2 mr-2"></i> <span class="font-weight-semibold">Clima Laboral</span></h4>
      <a href="#" class="header-elements-toggle text-default d-md-none"><i class="icon-more"></i></a>
    </div>
  </div>
</div>

<div class="content">

  <!-- Estadísticas rápidas -->
  <div class="row">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <i class="icon-calendar3 icon-3x text-primary mb-3"></i>
          <h3 class="font-weight-bold mb-0"><?php echo (int)$stats['periodos_activos']; ?></h3>
          <span class="text-muted">Periodos activos</span>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <i class="icon-comment-discussion icon-3x text-success mb-3"></i>
          <h3 class="font-weight-bold mb-0"><?php echo number_format($stats['total_respuestas']); ?></h3>
          <span class="text-muted">Respuestas totales</span>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <i class="icon-graph icon-3x text-info mb-3"></i>
          <h3 class="font-weight-bold mb-0"><?php echo number_format($stats['promedio_general'], 2); ?></h3>
          <span class="text-muted">Promedio general</span>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <i class="icon-file-text2 icon-3x text-warning mb-3"></i>
          <h3 class="font-weight-bold mb-0"><?php echo (int)$stats['planes_pendientes']; ?></h3>
          <span class="text-muted">Planes pendientes</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Periodo activo -->
  <?php if ($periodo_activo): ?>
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Periodo activo</h5>
    </div>
    <div class="card-body">
      <div class="media">
        <div class="mr-3">
          <i class="icon-calendar5 icon-3x text-success"></i>
        </div>
        <div class="media-body">
          <h5 class="font-weight-semibold mb-1">Clima <?php echo h($periodo_activo['anio']); ?></h5>
          <p class="mb-0">
            <strong>Periodo:</strong> <?php echo h($periodo_activo['fecha_inicio']); ?> al <?php echo h($periodo_activo['fecha_fin']); ?><br>
            <strong>Estatus:</strong>
            <?php if ($periodo_activo['estatus'] === 'publicado'): ?>
              <span class="badge badge-success">Publicado</span>
            <?php else: ?>
              <span class="badge badge-secondary">Borrador</span>
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Encuesta para empleado -->
  <?php if ($soy_elegible && $periodo_contestar && !$ya_respondio): ?>
  <div class="card bg-primary text-white">
    <div class="card-body">
      <div class="media">
        <div class="mr-3">
          <i class="icon-checkmark-circle icon-3x"></i>
        </div>
        <div class="media-body">
          <h5 class="font-weight-semibold mb-1">¡Eres elegible para contestar la encuesta!</h5>
          <p class="mb-3">Tu opinión es importante para mejorar el ambiente laboral.</p>
          <a href="clima_contestar.php" class="btn btn-light">
            <i class="icon-play3 mr-2"></i> Contestar encuesta
          </a>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($soy_elegible && $periodo_contestar && $ya_respondio): ?>
  <div class="card bg-success text-white">
    <div class="card-body">
      <div class="media">
        <div class="mr-3">
          <i class="icon-check icon-3x"></i>
        </div>
        <div class="media-body">
          <h5 class="font-weight-semibold mb-1">Ya has respondido la encuesta</h5>
          <p class="mb-0">¡Gracias por participar! Tu opinión nos ayuda a mejorar el clima laboral de la organización.</p>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Módulos administrativos (solo admin) -->
  <?php if ($es_admin): ?>
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Administración</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <!-- Periodos -->
        <div class="col-md-4 mb-3">
          <div class="card bg-light">
            <div class="card-body text-center">
              <i class="icon-calendar3 icon-3x text-primary mb-3"></i>
              <h6 class="font-weight-semibold mb-2">Periodos</h6>
              <p class="text-muted mb-3">Crear y gestionar periodos de evaluación</p>
              <a href="clima_periodos.php" class="btn btn-primary btn-sm btn-block">Acceder</a>
            </div>
          </div>
        </div>

        <!-- Generar elegibles -->
        <div class="col-md-4 mb-3">
          <div class="card bg-light">
            <div class="card-body text-center">
              <i class="icon-users4 icon-3x text-success mb-3"></i>
              <h6 class="font-weight-semibold mb-2">Generar elegibles</h6>
              <p class="text-muted mb-3">Definir empleados que participarán</p>
              <a href="clima_generar_elegibles.php" class="btn btn-success btn-sm btn-block">Acceder</a>
            </div>
          </div>
        </div>

        <!-- Participación -->
        <div class="col-md-4 mb-3">
          <div class="card bg-light">
            <div class="card-body text-center">
              <i class="icon-stats-bars2 icon-3x text-info mb-3"></i>
              <h6 class="font-weight-semibold mb-2">Participación</h6>
              <p class="text-muted mb-3">Monitorear participación y publicar</p>
              <a href="clima_participacion.php" class="btn btn-info btn-sm btn-block">Acceder</a>
            </div>
          </div>
        </div>

        <!-- Dimensiones y reactivos -->
        <div class="col-md-4 mb-3">
          <div class="card bg-light">
            <div class="card-body text-center">
              <i class="icon-lan2 icon-3x text-purple mb-3"></i>
              <h6 class="font-weight-semibold mb-2">Dimensiones y reactivos</h6>
              <p class="text-muted mb-3">Configurar encuesta (dimensiones/preguntas)</p>
              <a href="clima_dimensiones.php" class="btn btn-purple btn-sm btn-block">Acceder</a>
            </div>
          </div>
        </div>

        <!-- Resultados -->
        <div class="col-md-4 mb-3">
          <div class="card bg-light">
            <div class="card-body text-center">
              <i class="icon-graph icon-3x text-teal mb-3"></i>
              <h6 class="font-weight-semibold mb-2">Resultados</h6>
              <p class="text-muted mb-3">Dashboard ejecutivo por Dirección</p>
              <a href="clima_resultados.php" class="btn btn-teal btn-sm btn-block">Acceder</a>
            </div>
          </div>
        </div>

        <!-- Planes de acción -->
        <div class="col-md-4 mb-3">
          <div class="card bg-light">
            <div class="card-body text-center">
              <i class="icon-file-text2 icon-3x text-warning mb-3"></i>
              <h6 class="font-weight-semibold mb-2">Planes de acción</h6>
              <p class="text-muted mb-3">Gestionar planes de mejora continua</p>
              <a href="clima_planes.php" class="btn btn-warning btn-sm btn-block">Acceder</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Ayuda -->
  <?php if ($es_admin): ?>
  <div class="card">
    <div class="card-header bg-info text-white">
      <h5 class="card-title">Guía rápida del flujo administrativo</h5>
    </div>
    <div class="card-body">
      <h6 class="font-weight-semibold">Flujo del módulo:</h6>
      <ol class="mb-3">
        <li><strong>Crear periodo</strong>: Define año, fechas y fecha de corte de elegibilidad</li>
        <li><strong>Configurar encuesta</strong>: Administra dimensiones y reactivos (preguntas)</li>
        <li><strong>Generar elegibles</strong>: Define qué empleados participarán según fecha de corte</li>
        <li><strong>Publicar</strong>: Cambia estatus a "Publicado" para que empleados puedan contestar</li>
        <li><strong>Monitorear participación</strong>: Revisa avance por Dirección</li>
        <li><strong>Publicar resultados</strong>: Habilita visibilidad cuando participación >= 90%</li>
        <li><strong>Analizar resultados</strong>: Dashboard ejecutivo con promedios y ranking</li>
        <li><strong>Crear planes de acción</strong>: Define acciones de mejora por dimensión/Dirección</li>
      </ol>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$es_admin && !$soy_elegible): ?>
  <div class="card">
    <div class="card-header bg-light">
      <h5 class="card-title">Información</h5>
    </div>
    <div class="card-body">
      <div class="alert alert-light border-left-3 border-left-info mb-0">
        <i class="icon-info22 mr-2"></i>
        <strong>Nota:</strong> No eres elegible para contestar la encuesta actual. Contacta al área de Recursos Humanos si consideras que deberías participar.
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/layout/scripts.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
?>
