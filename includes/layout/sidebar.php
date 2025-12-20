<?php
require_once __DIR__ . '/../permisos.php';

// includes/layout/sidebar.php
if (!isset($active_menu)) { $active_menu = ''; }

function is_active($key, $active_menu) {
  return ($key === $active_menu) ? 'active' : '';
}
?>
<!-- Page content -->
<div class="page-content">

  <!-- Main sidebar -->
  <div class="sidebar sidebar-dark sidebar-main sidebar-expand-lg">
    <div class="sidebar-content">

      <!-- User menu (opcional) -->
      <div class="sidebar-section sidebar-user my-1">
        <div class="sidebar-section-body">
          <div class="media">
            <a href="#" class="mr-3">
              <img src="<?php echo ASSET_BASE; ?>global_assets/images/placeholders/placeholder.jpg" class="rounded-circle" alt="">
            </a>
            <div class="media-body">
              <div class="font-weight-semibold"><?php echo htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario'); ?></div>
              <div class="font-size-sm line-height-sm opacity-50">
                <?php echo htmlspecialchars($_SESSION['rol_nombre'] ?? ''); ?>
              </div>
            </div>
            <div class="ml-3 align-self-center">
              <button type="button" class="btn btn-outline-light-100 text-white border-transparent btn-icon rounded-pill btn-sm sidebar-control sidebar-main-resize d-none d-lg-inline-flex">
                <i class="icon-transmission"></i>
              </button>
              <button type="button" class="btn btn-outline-light-100 text-white border-transparent btn-icon rounded-pill btn-sm sidebar-mobile-main-toggle d-lg-none">
                <i class="icon-cross2"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
      <!-- /user menu -->

      <!-- Main navigation -->
      <div class="sidebar-section">
        <ul class="nav nav-sidebar" data-nav-type="accordion">

          <?php if (can('dashboard.ver')): ?>
          <li class="nav-item">
            <a href="<?php echo ASSET_BASE; ?>public/dashboard.php" class="nav-link <?php echo is_active('dashboard', $active_menu); ?>">
              <i class="icon-home4"></i><span>Dashboard</span>
            </a>
          </li>
          <?php endif; ?>

          <?php if (can('vacaciones.ver') || can('vacaciones.solicitar')): ?>
          <li class="nav-item">
            <a href="<?php echo ASSET_BASE; ?>public/vacaciones.php" class="nav-link <?php echo is_active('vacaciones', $active_menu); ?>">
              <i class="icon-calendar3"></i><span>Vacaciones</span>
            </a>
          </li>
          <?php endif; ?>

          <?php if (can('documentos.ver')): ?>
          <li class="nav-item">
            <a href="<?php echo ASSET_BASE; ?>public/documentos_rh.php" class="nav-link <?php echo is_active('documentos', $active_menu); ?>">
              <i class="icon-file-text2"></i><span>Normatividad RH</span>
            </a>
          </li>
          <?php endif; ?>

          <?php if (can('clima.ver') || can('clima.responder')): ?>
          <li class="nav-item">
            <a href="<?php echo ASSET_BASE; ?>public/clima.php" class="nav-link <?php echo is_active('clima', $active_menu); ?>">
              <i class="icon-pulse2"></i><span>Clima laboral</span>
            </a>
          </li>
          <?php endif; ?>

          <?php if (can('capacitacion.ver')): ?>
          <li class="nav-item">
            <a href="<?php echo ASSET_BASE; ?>public/capacitacion.php" class="nav-link <?php echo is_active('capacitacion', $active_menu); ?>">
              <i class="icon-graduation2"></i><span>Capacitación</span>
            </a>
          </li>
          <?php endif; ?>

          <?php if (can('incidencias.ver')): ?>
          <li class="nav-item">
            <a href="<?php echo ASSET_BASE; ?>public/incidencias.php" class="nav-link <?php echo is_active('incidencias', $active_menu); ?>">
              <i class="icon-alarm"></i><span>Incidencias</span>
            </a>
          </li>
          <?php endif; ?>

          <?php if (can('usuarios.admin')): ?>
          <li class="nav-item nav-item-submenu">
            <a href="#" class="nav-link"><i class="icon-cog3"></i> <span>Administración</span></a>
            <ul class="nav nav-group-sub" data-submenu-title="Administración">
              <li class="nav-item">
                <a href="<?php echo ASSET_BASE; ?>public/admin_usuarios.php" class="nav-link <?php echo is_active('admin_usuarios', $active_menu); ?>">
                  Usuarios
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo ASSET_BASE; ?>public/importar_nomina.php" class="nav-link <?php echo is_active('import_nomina', $active_menu); ?>">
                  Importar nómina
                </a>
              </li>
            </ul>
          </li>
          <?php endif; ?>

        </ul>
      </div>
      <!-- /main navigation -->

    </div>
  </div>
  <!-- /main sidebar -->
