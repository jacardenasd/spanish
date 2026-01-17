<?php
$nombre_usuario = isset($_SESSION['nombre_usuario']) && $_SESSION['nombre_usuario'] !== '' ? $_SESSION['nombre_usuario'] : 'Usuario';

$empresa_alias  = isset($_SESSION['empresa_alias']) ? $_SESSION['empresa_alias'] : '';
$empresa_nombre_sess = isset($_SESSION['empresa_nombre']) ? $_SESSION['empresa_nombre'] : 'Sin empresa';
$empresa_nombre = $empresa_alias ? $empresa_alias : $empresa_nombre_sess;

// Logo dinámico por empresa
// Busca coincidencias con archivos en global_assets/images/logos
function _norm_key($s) {
  $s = mb_strtolower($s, 'UTF-8');
  // quita espacios, guiones y caracteres no alfanuméricos
  $s = preg_replace('/[^a-z0-9]+/u', '', $s);
  return $s ?: '';
}

$logo_rel = 'global_assets/images/logo_light.png';
$logo_icon_rel = 'global_assets/images/logo_icon_light.png';

try {
  $logos_dir = realpath(__DIR__ . '/../../global_assets/images/logos');
  if ($logos_dir && is_dir($logos_dir)) {
    $files = glob($logos_dir . '/*.{png,jpg,jpeg,svg}', GLOB_BRACE);
    $map = [];
    foreach ($files as $f) {
      $bn = pathinfo($f, PATHINFO_BASENAME);
      $key = _norm_key(pathinfo($bn, PATHINFO_FILENAME));
      if ($key) {
        $map[$key] = $bn; // guarda el nombre exacto del archivo
      }
    }

    $empresa_key = _norm_key($empresa_alias ?: $empresa_nombre_sess);
    $matched = null;

    if ($empresa_key && isset($map[$empresa_key])) {
      $matched = $map[$empresa_key];
    } else if ($empresa_key) {
      // intento de coincidencia parcial (contains)
      foreach ($map as $k => $bn) {
        if ($empresa_key !== '' && strpos($k, $empresa_key) !== false) {
          $matched = $bn;
          break;
        }
      }
      // inverso: la clave del logo contenida en el nombre de empresa
      if (!$matched) {
        foreach ($map as $k => $bn) {
          if ($k !== '' && strpos($empresa_key, $k) !== false) {
            $matched = $bn;
            break;
          }
        }
      }
    }

    if ($matched) {
      $logo_rel = 'global_assets/images/logos/' . $matched;

      // Buscar variante de ícono específica: *_icon.* o que contenga 'icon' y el nombre base
      $base_norm = _norm_key(pathinfo($matched, PATHINFO_FILENAME));
      $icon_found = null;
      foreach ($files as $f) {
        $bn2 = pathinfo($f, PATHINFO_BASENAME);
        $fn2 = pathinfo($bn2, PATHINFO_FILENAME);
        $norm2 = _norm_key($fn2);
        // Coincidencia exacta base+icon (soporta fh_icon, fh-icon, fhicon)
        if ($norm2 === $base_norm . 'icon') {
          $icon_found = $bn2;
          break;
        }
        // Coincidencia parcial: contiene 'icon' y el base
        if (strpos($fn2, 'icon') !== false) {
          // cualquiera que tenga el base dentro del norm
          if ($base_norm !== '' && strpos($norm2, $base_norm) !== false) {
            $icon_found = $bn2;
            break;
          }
        }
      }

      if ($icon_found) {
        $logo_icon_rel = 'global_assets/images/logos/' . $icon_found;
      } else {
        // si no hay ícono específico, usa el logo principal como ícono
        $logo_icon_rel = $logo_rel;
      }
    }
  }
} catch (Throwable $e) {
  // silencioso: usa el logo por defecto si hay error
}

$empresas = isset($_SESSION['empresas']) ? $_SESSION['empresas'] : [];
$multiempresa = (count($empresas) > 1);

// empleado_id para la empresa activa (lo seteas en seleccionar_empresa.php)
$empleado_id = isset($_SESSION['empleado_id']) ? (int)$_SESSION['empleado_id'] : 0;

// URL de foto (con fallback a placeholder)
$foto_url = ($empleado_id > 0)
    ? (ASSET_BASE . 'public/ver_foto_empleado.php?empleado_id=' . $empleado_id)
    : (ASSET_BASE . 'global_assets/images/placeholders/placeholder.jpg');
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
      <img src="<?php echo ASSET_BASE . $logo_rel; ?>" class="d-none d-sm-block" alt="">
      <img src="<?php echo ASSET_BASE . $logo_icon_rel; ?>" class="d-sm-none" alt="">
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
          <img src="<?php echo htmlspecialchars($foto_url, ENT_QUOTES, 'UTF-8'); ?>" class="rounded-pill mr-lg-2" height="34" width="34" style="object-fit:cover;" alt="">
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
