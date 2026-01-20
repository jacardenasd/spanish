<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/conexion.php';

// Valores por defecto
$active_menu = 'dashboard';
$page_title      = isset($page_title) ? $page_title : 'Dashboard';
$breadcrumb_home = isset($breadcrumb_home) ? $breadcrumb_home : 'Home';
$breadcrumb_lvl1 = isset($breadcrumb_lvl1) ? $breadcrumb_lvl1 : null;

// Obtener nombre de la empresa
$empresa_nombre = isset($_SESSION['empresa_nombre']) ? $_SESSION['empresa_nombre'] : 'Sin empresa';
$empresa_id = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;

// Obtener documentos disponibles
$documentos = [];
if ($empresa_id > 0) {
  $stmt = $pdo->prepare("
    SELECT documento_id, titulo, descripcion, archivo_path, seccion, orden
    FROM documentos
    WHERE empresa_id = :empresa_id AND estatus = 'activo'
    ORDER BY seccion, orden, created_at DESC
  ");
  $stmt->execute([':empresa_id' => $empresa_id]);
  $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Agrupar documentos por sección
$docs_por_seccion = [];
foreach ($documentos as $doc) {
  $sec = $doc['seccion'];
  if (!isset($docs_por_seccion[$sec])) {
    $docs_por_seccion[$sec] = [];
  }
  $docs_por_seccion[$sec][] = $doc;
}

// Si esta pantalla requiere JS extra:
$extra_js = [
  'global_assets/js/demo_pages/dashboard.js'
];

include __DIR__ . '/../includes/layout/head.php';
include __DIR__ . '/../includes/layout/navbar.php';
include __DIR__ . '/../includes/layout/sidebar.php';
include __DIR__ . '/../includes/layout/content_open.php';

?>

<!-- Aquí empieza tu contenido real -->
<div class="page-header page-header-light">
    <div class="page-header-content header-elements-lg-inline">
        <div class="page-title d-flex">
            <h4><span class="font-weight-semibold"><?php echo htmlspecialchars($page_title); ?></span></h4>
        </div>
    </div>
    <div class="breadcrumb-line breadcrumb-line-light header-elements-lg-inline">
        <div class="d-flex">
            <div class="breadcrumb">
                <a href="<?php echo ASSET_BASE; ?>public/dashboard.php" class="breadcrumb-item">
                    <i class="icon-home2 mr-2"></i> <?php echo $breadcrumb_home; ?>
                </a>
                <?php if ($breadcrumb_lvl1): ?>
                <span class="breadcrumb-item active"><?php echo htmlspecialchars($breadcrumb_lvl1); ?></span>
                <?php endif; ?>
                <span class="breadcrumb-item active"><?php echo htmlspecialchars($page_title); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="content">
  
  <!-- Sección de Bienvenida -->
  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-body bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 8px;">
          <h1 class="card-title mb-3" style="font-size: 2.5rem; font-weight: bold;">
            ¡Bienvenido al SGRH! en <?php echo htmlspecialchars($empresa_nombre); ?>
          </h1>
            
          <p class="mt-2" style="font-size: 0.95rem; opacity: 0.9;">
            Sistema de Gestión de Recursos Humanos
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Información del Sistema -->
  <div class="row mt-4">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header bg-light">
          <h5 class="card-title mb-0">
            <i class="icon-info mr-2"></i> ¿Qué es SGRH?
          </h5>
        </div>
        <div class="card-body">
          <p class="mb-3">
            El <strong>Sistema de Gestión de Recursos Humanos (SGRH)</strong> es una plataforma integral diseñada para 
            optimizar la administración de personal en tu organización. Proporciona herramientas modernas y eficientes para 
            gestionar todos los aspectos relacionados con los recursos humanos.
          </p>
          <p>
            Este sistema permite centralizar la información, mejorar la productividad y facilitar la toma de decisiones 
            basada en datos.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Características Principales -->
  <div class="row mt-4">
    <div class="col-md-4">
      <div class="card">
        <div class="card-body text-center">
          <div class="mb-3">
            <i class="icon-users" style="font-size: 2.5rem; color: #667eea;"></i>
          </div>
          <h5 class="card-title">Clima Laboral</h5>
          <p class="text-muted">
            Permite responder la encuesta anual de clima laboral, así como ver los resultados.
          </p>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-body text-center">
          <div class="mb-3">
            <i class="icon-file-text" style="font-size: 2.5rem; color: #764ba2;"></i>
          </div>
          <h5 class="card-title">Evaluación del Desempeño</h5>
          <p class="text-muted">
            Permite realizar la evaluación del desempeño para personal adminsitrativo.
          </p>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-body text-center">
          <div class="mb-3">
            <i class="icon-briefcase" style="font-size: 2.5rem; color: #f093fb;"></i>
          </div>
          <h5 class="card-title">Reportes y Análisis</h5>
          <p class="text-muted">
            Genera reportes detallados y analiza métricas clave del desempeño organizacional.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Manuales y Documentos de Ayuda -->
  <div class="row mt-4">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header bg-light">
          <h5 class="card-title mb-0">
            <i class="icon-book mr-2"></i> Manuales y Documentos de Ayuda
          </h5>
        </div>
        <div class="card-body">
          <p class="text-muted mb-4">
            Accede a los manuales de usuario para cada sección del sistema. Estos documentos te ayudarán a conocer todas las funcionalidades disponibles.
          </p>
          <div class="list-group list-group-flush">
            <?php if (empty($documentos)): ?>
            <div class="list-group-item text-center text-muted py-4">
              <i class="icon-info mr-2"></i>
              No hay documentos disponibles en este momento. Contáctese con el administrador del sistema.
            </div>
            <?php else: ?>
              <?php foreach ($documentos as $doc): ?>
              <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <i class="icon-file-pdf text-danger mr-2"></i>
                  <strong><?php echo htmlspecialchars($doc['titulo']); ?></strong>
                  <?php if ($doc['descripcion']): ?>
                  <p class="text-muted mb-0 small"><?php echo htmlspecialchars($doc['descripcion']); ?></p>
                  <?php endif; ?>
                </div>
                <a href="<?php echo ASSET_BASE; ?>storage/<?php echo htmlspecialchars($doc['archivo_path']); ?>" class="btn btn-sm btn-primary" download target="_blank">
                  <i class="icon-download mr-1"></i> Descargar
                </a>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

    <!-- Información de Soporte -->
  <div class="row mt-4 mb-4">
    <div class="col-md-12">
      <div class="alert alert-info alert-icon">
        <button type="button" class="close" data-dismiss="alert"><span>×</span><span class="sr-only">Close</span></button>
        <i class="icon-info"></i>
        <strong>¿Necesitas ayuda?</strong> Consulta la documentación del sistema o contacta al equipo de soporte técnico 
        para resolver cualquier duda.
      </div>
    </div>
  </div>

</div>

<?php
include __DIR__ . '/../includes/layout/footer.php';
include __DIR__ . '/../includes/layout/content_close.php';
?>


<?php
include __DIR__ . '/../includes/layout/scripts.php';
?>
