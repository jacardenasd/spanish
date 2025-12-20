<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';

require_login();

$empresas = $_SESSION['empresas'] ?? [];
$error = '';

if (count($empresas) === 0) {
    $error = 'No tienes empresas asignadas. Contacta al administrador.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && count($empresas) > 0) {
    $empresa_id = (int)($_POST['empresa_id'] ?? 0);

    $found = null;
    foreach ($empresas as $e) {
        if ((int)$e['empresa_id'] === $empresa_id) {
            $found = $e;
            break;
        }
    }

    if (!$found) {
        $error = 'Empresa no válida.';
    } else {
        $_SESSION['empresa_id'] = (int)$found['empresa_id'];
        $_SESSION['empresa_nombre'] = $found['nombre'];
        $_SESSION['empresa_alias'] = $found['alias'];
        $_SESSION['es_admin_empresa'] = (int)$found['es_admin'];

        cargar_permisos_sesion((int)$_SESSION['usuario_id']);

        header('Location: index.php');
        exit;
    }
}

if (count($empresas) === 1 && empty($_SESSION['empresa_id'])) {
    $_SESSION['empresa_id'] = (int)$empresas[0]['empresa_id'];
    $_SESSION['empresa_nombre'] = $empresas[0]['nombre'];
    $_SESSION['empresa_alias'] = $empresas[0]['alias'];
    $_SESSION['es_admin_empresa'] = (int)$empresas[0]['es_admin'];

    cargar_permisos_sesion((int)$_SESSION['usuario_id']);

    header('Location: index.php');
    exit;
}

$page_title = 'Seleccionar empresa | SGRH';
include __DIR__ . '/../includes/layout/head.php';
?>

<div class="page-content">
  <div class="content-wrapper">
    <div class="content d-flex justify-content-center align-items-center">

      <form class="login-form" method="post">
        <div class="card mb-0">
          <div class="card-body">
            <div class="text-center mb-3">
              <h5 class="mb-0">Selecciona empresa</h5>
              <span class="d-block text-muted">Razón social para operar en el sistema</span>
            </div>

            <?php if ($error): ?>
              <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="form-group">
              <select name="empresa_id" class="form-control" required>
                <option value="">-- Selecciona --</option>
                <?php foreach ($empresas as $e): ?>
                  <option value="<?php echo (int)$e['empresa_id']; ?>">
                    <?php echo htmlspecialchars($e['alias'] ?: $e['nombre']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Continuar</button>

          </div>
        </div>
      </form>

    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout/scripts.php'; ?>
