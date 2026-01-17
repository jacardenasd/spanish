<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/password_reset.php';
require_once __DIR__ . '/../includes/mailer.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rfc = $_POST['rfc'] ?? '';
    $no_emp = $_POST['no_emp'] ?? '';

    $u = pr_find_user_by_rfc_noemp($rfc, $no_emp);

    // Respuesta genérica (anti-enumeración)
    $mensaje = 'Si la información es correcta, recibirás un correo con instrucciones para restablecer tu contraseña.';

    if ($u && $u['estatus'] === 'activo' && !empty($u['correo'])) {
        $token = pr_create_token((int)$u['usuario_id'], $_SERVER['REMOTE_ADDR'] ?? null);

        $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $link = $base . dirname($_SERVER['REQUEST_URI']) . '/resetear.php?token=' . urlencode($token);

        $html = "
          <p>Se solicitó un restablecimiento de contraseña para tu cuenta SGRH.</p>
          <p>Da clic en el siguiente enlace (vigente por 60 minutos):</p>
          <p><a href=\"{$link}\">Restablecer contraseña</a></p>
          <p>Si no solicitaste esto, ignora este mensaje.</p>
        ";

        $okMail = enviar_correo($u['correo'], 'Restablecer contraseña | SGRH', $html);

        
        //if (APP_ENV === 'dev') {
        //    $mensaje .= '<br><small class="text-muted">DEV: correo guardado en /storage/mails (' . ($okMail ? 'OK' : 'FALLÓ') . ')</small>';
        //}
        
        // Si falla el envío, registrar en log pero mostrar mensaje genérico
        if (!$okMail) {
            @error_log('[SGRH] Error al enviar correo de recuperación para: ' . $rfc);
        }


      }
}

$page_title = 'Recuperar contraseña | SGRH';
include __DIR__ . '/../includes/layout/head.php';
?>

<div class="page-content">
  <div class="content-wrapper">
    <div class="content d-flex justify-content-center align-items-center">

      <form class="login-form" method="post" autocomplete="off">
        <div class="card mb-0">
          <div class="card-body">
            <div class="text-center mb-3">
              <h5 class="mb-0">Recuperar contraseña</h5>
              <span class="d-block text-muted">Ingresa RFC (sin homoclave) y No. empleado</span>
            </div>

            <?php if ($error): ?>
              <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($mensaje): ?>
              <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <div class="form-group">
              <input type="text" name="rfc" class="form-control" placeholder="RFC (10)" maxlength="10" style="text-transform: uppercase;" autocomplete="off" required>
            </div>

            <div class="form-group">
              <input type="text" name="no_emp" class="form-control" placeholder="No. empleado" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Enviar enlace</button>

            <div class="text-center mt-3">
              <a href="login.php">Volver al login</a>
            </div>

          </div>
        </div>
      </form>

    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout/scripts.php'; ?>
