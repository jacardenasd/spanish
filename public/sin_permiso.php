<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_login();

$page_title = 'Sin permiso | SGRH';
include __DIR__ . '/../includes/layout/head.php';
include __DIR__ . '/../includes/layout/navbar.php';
include __DIR__ . '/../includes/layout/sidebar.php';
?>

<div class="page-content">
  <div class="content-wrapper">
    <div class="content">
      <div class="alert alert-warning">
        No cuentas con permisos para acceder a esta sección.
      </div>
      <a href="<?php echo ASSET_BASE; ?>public/index.php" class="btn btn-primary">Regresar</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout/scripts.php'; ?>
