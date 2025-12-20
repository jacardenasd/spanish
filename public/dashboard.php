<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';

$page_title = 'Dashboard | SGRH';
$active_menu = 'dashboard';

// Si esta pantalla requiere JS extra:
$extra_js = [
  'global_assets/js/demo_pages/dashboard.js'
];

include __DIR__ . '/../includes/layout/head.php';
include __DIR__ . '/../includes/layout/navbar.php';
include __DIR__ . '/../includes/layout/sidebar.php';
?>

<!-- Aquí empieza tu contenido real -->
<div class="content-wrapper">
  <div class="page-header page-header-light">
    <div class="page-header-content header-elements-lg-inline">
      <div class="page-title d-flex">
        <h4><span class="font-weight-semibold">Dashboard</span></h4>
      </div>
    </div>
  </div>

  <div class="content">
    <!-- contenido -->
  </div>

  <div class="navbar navbar-expand-lg navbar-light">
    <div class="navbar-collapse collapse" id="navbar-footer">
      <span class="navbar-text">
        &copy; <?php echo date('Y'); ?> SGRH
      </span>
    </div>
  </div>
</div>


<?php
include __DIR__ . '/../includes/layout/scripts.php';
