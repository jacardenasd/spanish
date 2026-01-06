<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rfc  = $_POST['rfc'] ?? '';
    $pass = $_POST['password'] ?? '';

    // Login solo RFC + password
    list($ok, $msg) = login_intento($rfc, $pass);

    if ($ok) {
        // Forzar cambio si aplica
        if (!empty($_SESSION['debe_cambiar_pass'])) {
            header('Location: cambiar_password.php');
            exit;
        }
        // Si no seleccionó empresa (tiene varias)
        if (empty($_SESSION['empresa_id'])) {
            header('Location: seleccionar_empresa.php');
            exit;
        }
        header('Location: dashboard.php');
        exit;
    } else {
        $error = $msg;
    }
}

$page_title = 'Login | SGRH';
include __DIR__ . '/../includes/layout/head.php';
?>

<div class="page-content">
  <div class="content-wrapper">
    <div class="content d-flex justify-content-center align-items-center">

      <form class="login-form" method="post" autocomplete="off">
        <div class="card mb-0">
          <div class="card-body">
            <div class="text-center mb-3">
              <i class="icon-reading icon-2x text-secondary border-secondary border-3 rounded-pill p-3 mb-3 mt-1"></i>
              <h5 class="mb-0">Acceso al sistema</h5>
              <span class="d-block text-muted">RFC (sin homoclave) + contraseña</span>
            </div>

            <?php if (!empty($error)): ?>
              <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="form-group form-group-feedback form-group-feedback-left">
            <input type="text" name="rfc" id="rfc" class="form-control" placeholder="RFC (10)" maxlength="10" style="text-transform: uppercase;" autocomplete="off" required>
            <div class="form-control-feedback">
              <i class="icon-profile text-muted"></i>
            </div>

              <div class="form-control-feedback"><i class="icon-profile text-muted"></i></div>
            </div>

            <div class="form-group form-group-feedback form-group-feedback-left">
              <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
              <div class="form-control-feedback"><i class="icon-lock2 text-muted"></i></div>
            </div>

            <div class="form-group">
              <button type="submit" class="btn btn-primary btn-block">Entrar</button>
            </div>

            <div class="text-center">
              <small class="text-muted">Contraseña inicial: tu número de empleado.</small>
            </div>

            <div class="text-center mt-3">
              <a href="recuperar_contrasena.php">¿Olvidaste tu contraseña?</a>
            </div>

            <div class="text-center mt-3">
              <a href="terminos.php">Términos del Servicio.</a>
            </div>

          </div>
        </div>
      </form>

    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout/scripts.php'; ?>
