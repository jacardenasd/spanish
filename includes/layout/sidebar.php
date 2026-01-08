<?php
require_once __DIR__ . '/../permisos.php';

// includes/layout/sidebar.php
if (!isset($active_menu)) { $active_menu = ''; }

function is_active($key, $active_menu) {
  return ($key === $active_menu) ? 'active' : '';
}

// Sin ?? (compatibilidad)
$nombre_usuario = (isset($_SESSION['nombre_usuario']) && $_SESSION['nombre_usuario'] !== '') ? $_SESSION['nombre_usuario'] : 'Usuario';
$rol_nombre = (isset($_SESSION['rol_nombre']) && $_SESSION['rol_nombre'] !== '') ? $_SESSION['rol_nombre'] : '';

// Foto: storage -> se sirve por ver_foto_empleado.php
$empleado_id = isset($_SESSION['empleado_id']) ? (int)$_SESSION['empleado_id'] : 0;

$foto_url = ($empleado_id > 0)
  ? (ASSET_BASE . 'public/ver_foto_empleado.php?empleado_id=' . $empleado_id)
  : (ASSET_BASE . 'global_assets/images/placeholders/placeholder.jpg');

$admin_active = in_array($active_menu, [
  'admin_usuarios',
  'import_nomina',
  'admin_org_unidades',
  'admin_org_adscripciones',
  'admin_org_puestos',
  'admin_org_centros_trabajo',
  'clima_dimensiones',
  'clima_periodos',
  'clima_generar_elegibles',
  'clima_participacion',
  'clima_resultados',
  'clima_planes'
], true);

$clima_user_active = in_array($active_menu, [
  'clima',
  'clima_resultados_mi_unidad',
  'clima_planes_mi_unidad'
], true);

$clima_admin_active = in_array($active_menu, [
  'clima_dimensiones',
  'clima_periodos',
  'clima_generar_elegibles',
  'clima_participacion',
  'clima_resultados',
  'clima_planes'
], true);
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
            <img src="<?php echo htmlspecialchars($foto_url, ENT_QUOTES, 'UTF-8'); ?>" class="rounded-circle" width="38" height="38" style="object-fit:cover;" alt="">            </a>
            <div class="media-body">
          <div class="font-weight-semibold"><?php echo htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="font-size-sm line-height-sm opacity-50">
              <?php echo htmlspecialchars($rol_nombre, ENT_QUOTES, 'UTF-8'); ?>
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

          <li class="nav-item nav-item-submenu <?php echo $clima_user_active ? 'nav-item-expanded nav-item-open' : ''; ?>">
            <a href="#" class="nav-link <?php echo $clima_user_active ? 'active' : ''; ?>">
              <i class="icon-pulse2"></i> <span>Clima Laboral</span>
            </a>
            <ul class="nav nav-group-sub" data-submenu-title="Clima Laboral">
              <li class="nav-item">
                <a href="<?php echo ASSET_BASE; ?>public/clima.php"
                  class="nav-link <?php echo is_active('clima', $active_menu); ?>">
                  <i class="icon-pencil7"></i> Responder Cuestionario
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo ASSET_BASE; ?>public/clima_resultados_mi_unidad.php"
                  class="nav-link <?php echo is_active('clima_resultados_mi_unidad', $active_menu); ?>">
                  <i class="icon-stats-dots"></i> Ver Resultados
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo ASSET_BASE; ?>public/clima_planes_mi_unidad.php"
                  class="nav-link <?php echo is_active('clima_planes_mi_unidad', $active_menu); ?>">
                  <i class="icon-clipboard3"></i> Planes de Acción
                </a>
              </li>
            </ul>
          </li>

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

          <?php if (can('usuarios.admin') || can('organizacion.admin')): ?>
          <li class="nav-item nav-item-submenu <?php echo $admin_active ? 'nav-item-expanded nav-item-open' : ''; ?>">
            <a href="#" class="nav-link <?php echo $admin_active ? 'active' : ''; ?>">
              <i class="icon-cog3"></i> <span>Administración</span>
            </a>

            <ul class="nav nav-group-sub" data-submenu-title="Administración">

              <?php if (can('usuarios.admin')): ?>
              <li class="nav-item">
                <a href="<?php echo ASSET_BASE; ?>public/admin_usuarios.php"
                  class="nav-link <?php echo is_active('admin_usuarios', $active_menu); ?>">
                  Usuarios
                </a>
              </li>

              <li class="nav-item">
                <a href="<?php echo ASSET_BASE; ?>public/importar_nomina.php"
                  class="nav-link <?php echo is_active('import_nomina', $active_menu); ?>">
                  Importar nómina
                </a>
              </li>
              <?php endif; ?>

              <?php if (can('organizacion.admin')): ?>
              <li class="nav-item">
                <a href="<?php echo ASSET_BASE; ?>public/admin_org_unidades.php"
                  class="nav-link <?php echo is_active('admin_org_unidades', $active_menu); ?>">
                  Áras
                </a>
              </li>

              <li class="nav-item">
                <a href="<?php echo ASSET_BASE; ?>public/admin_org_adscripciones.php"
                  class="nav-link <?php echo is_active('admin_org_adscripciones', $active_menu); ?>">
                  Adscripciones
                </a>
              </li>

              <li class="nav-item">
                <a href="<?php echo ASSET_BASE; ?>public/admin_org_puestos.php"
                  class="nav-link <?php echo is_active('admin_org_puestos', $active_menu); ?>">
                  Puestos
                </a>
              </li>

              <li class="nav-item">
                <a href="<?php echo ASSET_BASE; ?>public/admin_org_centros_trabajo.php"
                  class="nav-link <?php echo is_active('admin_org_centros_trabajo', $active_menu); ?>">
                  Ubicaciones
                </a>
              </li>

              <li class="nav-item-divider"></li>

              <li class="nav-item nav-item-submenu <?php echo $clima_admin_active ? 'nav-item-expanded nav-item-open' : ''; ?>">
                <a href="#" class="nav-link <?php echo $clima_admin_active ? 'active' : ''; ?>">
                  <i class="icon-pulse2"></i> Clima Laboral
                </a>
                <ul class="nav nav-group-sub" data-submenu-title="Clima Laboral">
                  <li class="nav-item">
                    <a href="<?php echo ASSET_BASE; ?>public/clima_dimensiones.php"
                      class="nav-link <?php echo is_active('clima_dimensiones', $active_menu); ?>">
                      Dimensiones y Reactivos
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="<?php echo ASSET_BASE; ?>public/clima_periodos.php"
                      class="nav-link <?php echo is_active('clima_periodos', $active_menu); ?>">
                      Periodos
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="<?php echo ASSET_BASE; ?>public/clima_generar_elegibles.php"
                      class="nav-link <?php echo is_active('clima_generar_elegibles', $active_menu); ?>">
                      Generar Elegibles
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="<?php echo ASSET_BASE; ?>public/clima_participacion.php"
                      class="nav-link <?php echo is_active('clima_participacion', $active_menu); ?>">
                      Participación
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="<?php echo ASSET_BASE; ?>public/clima_resultados.php"
                      class="nav-link <?php echo is_active('clima_resultados', $active_menu); ?>">
                      Resultados
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="<?php echo ASSET_BASE; ?>public/clima_planes.php"
                      class="nav-link <?php echo is_active('clima_planes', $active_menu); ?>">
                      Planes de Acción
                    </a>
                  </li>
                </ul>
              </li>

              <?php endif; ?>

            </ul>
          </li>
          <?php endif; ?>

        </ul>
      </div>
      <!-- /main navigation -->

    </div>
  </div>
  <!-- /main sidebar -->
