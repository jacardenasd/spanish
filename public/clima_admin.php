<?php
// public/clima_admin.php
// SGRH - Clima Laboral - Panel de Administración
// Hub de navegación para administración del módulo de clima laboral
// Compatible PHP 5.7 - NO usar operador ??

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/conexion.php';

require_login();
require_empresa();
require_password_change_redirect();
require_demograficos_redirect();

if (!can('organizacion.admin') && !can('clima.admin')) {
    http_response_code(403);
    die('<div class="alert alert-danger">No tienes permisos para acceder a esta sección.</div>');
}

if (session_status() === PHP_SESSION_NONE) session_start();

$empresa_id = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;

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

    // Promedio general (último periodo publicado) - convertir a escala 0-100
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
    $prom_1_5 = $stmt3->fetchColumn();
    if ($prom_1_5) {
        $prom_0_100 = (((float)$prom_1_5 - 1) / 4) * 100;
        $stats['promedio_general'] = round($prom_0_100, 1);
    } else {
        $stats['promedio_general'] = 0.0;
    }

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
// LAYOUT
// =======================
$page_title = 'Clima Laboral - Administración';
$active_menu = 'clima_admin';

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
      <h4><i class="icon-pulse2 mr-2"></i> <span class="font-weight-semibold">Clima Laboral</span> - Administración</h4>
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
          <h3 class="font-weight-bold mb-0"><?php echo number_format($stats['promedio_general'], 1); ?>%</h3>
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

  <!-- Módulos administrativos -->
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Módulos de Administración</h5>
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

  <!-- Ayuda -->
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
      <div class="alert alert-info border-0 mb-0">
        <i class="icon-info22 mr-2"></i>
        <strong>Nota:</strong> Los resultados se muestran en escala 0-100% en lugar de 1-5 para mejor interpretación.
      </div>
    </div>
  </div>

</div>

<?php
require_once __DIR__ . '/../includes/layout/scripts.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
?>
