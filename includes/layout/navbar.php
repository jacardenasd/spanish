<?php
$nombre_usuario = $_SESSION['nombre_usuario'] ?? 'Usuario';
$empresa_nombre = $_SESSION['empresa_alias'] ?: ($_SESSION['empresa_nombre'] ?? 'Sin empresa');
$empresas = $_SESSION['empresas'] ?? [];
$multiempresa = (count($empresas) > 1);
?>
<!-- Main navbar -->
<div class="navbar navbar-expand-lg navbar-dark navbar-static">
  <div class="d-flex flex-1 d-lg-none">
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar-mobile">
      <i class="icon-paragraph-justify3"></i>
    </button>
    <button class="navbar-toggler sidebar-mobile-main-toggle" type="button">
      <i class="icon-transmission"></i>
    </button>
  </div>

  <div class="navbar-brand text-center text-lg-left">
    <a href="<?php echo ASSET_BASE; ?>public/index.php" class="d-inline-block">
      <img src="<?php echo ASSET_BASE; ?>global_assets/images/logo_light.png" class="d-none d-sm-block" alt="">
      <img src="<?php echo ASSET_BASE; ?>global_assets/images/logo_icon_light.png" class="d-sm-none" alt="">
    </a>
  </div>

  <div class="collapse navbar-collapse order-2 order-lg-1" id="navbar-mobile">

    <!-- Empresa seleccionada -->
    <ul class="navbar-nav">
      <li class="nav-item d-flex align-items-center">
        <span class="navbar-text font-weight-semibold">
          <i class="icon-office mr-2"></i><?php echo htmlspecialchars($empresa_nombre); ?>
          <?php if (!empty($_SESSION['es_admin_empresa'])): ?>
        <span class="badge badge-warning ml-2">Admin</span>
          <?php endif; ?>
        </span>
      </li>

      <?php if ($multiempresa): ?>
      <li class="nav-item nav-item-dropdown-lg dropdown">
        <a href="#" class="navbar-nav-link dropdown-toggle" data-toggle="dropdown">
          Cambiar empresa
        </a>
        <div class="dropdown-menu">
          <?php foreach ($empresas as $e): ?>
            <form method="post" action="<?php echo ASSET_BASE; ?>public/cambiar_empresa.php" style="margin:0;">
              <input type="hidden" name="empresa_id" value="<?php echo (int)$e['empresa_id']; ?>">
              <button type="submit" class="dropdown-item">
                <?php echo htmlspecialchars($e['alias'] ?: $e['nombre']); ?>
                <?php if (!empty($_SESSION['empresa_id']) && (int)$_SESSION['empresa_id'] === (int)$e['empresa_id']): ?>
                  <span class="badge badge-success ml-2">Actual</span>
                <?php endif; ?>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      </li>
      <?php endif; ?>
    </ul>

    <ul class="navbar-nav ml-lg-auto">
      <li class="nav-item nav-item-dropdown-lg dropdown dropdown-user h-100">
        <a href="#" class="navbar-nav-link navbar-nav-link-toggler dropdown-toggle d-inline-flex align-items-center h-100" data-toggle="dropdown">
          <img src="<?php echo ASSET_BASE; ?>global_assets/images/placeholders/placeholder.jpg" class="rounded-pill mr-lg-2" height="34" alt="">
          <span class="d-none d-lg-inline-block"><?php echo htmlspecialchars($nombre_usuario); ?></span>
        </a>

        <div class="dropdown-menu dropdown-menu-right">
          <a href="<?php echo ASSET_BASE; ?>public/mi_perfil.php" class="dropdown-item">
            <i class="icon-user-plus"></i> Mi perfil
          </a>
          <a href="<?php echo ASSET_BASE; ?>public/cambiar_password.php" class="dropdown-item">
            <i class="icon-cog5"></i> Cambiar contraseña
          </a>
          <div class="dropdown-divider"></div>
          <a href="<?php echo ASSET_BASE; ?>public/logout.php" class="dropdown-item">
            <i class="icon-switch2"></i> Salir
          </a>
        </div>
      </li>
    </ul>

  </div>
</div>
<!-- /main navbar -->
