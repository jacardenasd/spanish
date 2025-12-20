<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rfc = $_POST['rfc'] ?? '';
    $no_emp = $_POST['no_emp'] ?? '';
    $pass = $_POST['password'] ?? '';

    list($ok, $msg) = login_intento($rfc, $no_emp, $pass);

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
              <h5 class="mb-0">Acceso al sistema</h5>
              <span class="d-block text-muted">RFC (sin homoclave) + No. empleado</span>
            </div>

            <?php if ($error): ?>
              <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="form-group form-group-feedback form-group-feedback-left">
              <input type="text" name="rfc" class="form-control" placeholder="RFC (10)" maxlength="13" required>
              <div class="form-control-feedback"><i class="icon-profile text-muted"></i></div>
            </div>

            <div class="form-group form-group-feedback form-group-feedback-left">
              <input type="text" name="no_emp" class="form-control" placeholder="Número de empleado" required>
              <div class="form-control-feedback"><i class="icon-user text-muted"></i></div>
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

          </div>
        </div>
      </form>

    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout/scripts.php'; ?>
