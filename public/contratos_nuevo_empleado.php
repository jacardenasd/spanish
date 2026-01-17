<?php
// public/contratos_nuevo_empleado.php
// Formulario para agregar un empleado nuevo al sistema

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';

require_login();
require_empresa();

// Validar permisos: contratos.crear O usuarios.admin
if (function_exists('require_perm_any')) {
    require_perm_any(['contratos.crear', 'usuarios.admin']);
} else {
    if (!can('contratos.crear') && !can('usuarios.admin')) {
        header('Location: sin_permiso.php');
        exit;
    }
}

$empresa_id = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;
$usuario_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Variables de estado
$error = '';
$exito = '';
$empleado_activo = null;
$accion = isset($_GET['accion']) ? (string)$_GET['accion'] : '';

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rfc = isset($_POST['rfc']) ? strtoupper(trim($_POST['rfc'])) : '';
    $curp = isset($_POST['curp']) ? strtoupper(trim($_POST['curp'])) : '';
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $apellido_paterno = isset($_POST['apellido_paterno']) ? trim($_POST['apellido_paterno']) : '';
    $apellido_materno = isset($_POST['apellido_materno']) ? trim($_POST['apellido_materno']) : '';
    
    // Validaciones
    if (empty($rfc)) {
        $error = 'El RFC es requerido';
    } elseif (empty($nombre)) {
        $error = 'El nombre es requerido';
    } elseif (strlen($rfc) < 10 || strlen($rfc) > 13) {
        $error = 'El RFC debe tener entre 10 y 13 caracteres';
    } else {
        // Verificar si el RFC existe en empleados activos
        $sqlCheck = "SELECT e.empleado_id, ed.nombre, ed.apellido_paterno, ed.apellido_materno, ed.rfc 
                     FROM empleados e
                     LEFT JOIN empleados_demograficos ed ON ed.empleado_id = e.empleado_id
                     WHERE e.empresa_id = :emp AND e.es_activo = 1 AND ed.rfc = :rfc
                     LIMIT 1";
        $stCheck = $pdo->prepare($sqlCheck);
        $stCheck->execute(array(':emp' => $empresa_id, ':rfc' => $rfc));
        $empleadoActivo = $stCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($empleadoActivo) {
            // El RFC ya existe en empleados activos - redirigir para recuperar
            header('Location: contratos_nuevo_empleado.php?accion=recuperar&empleado_id=' . $empleadoActivo['empleado_id']);
            exit;
        }
        
        // Verificar que el RFC no exista ya en nuevo ingreso
        $sqlCheckNuevo = "SELECT nuevo_ingreso_id FROM empleados_nuevo_ingreso 
                          WHERE empresa_id = :emp AND rfc = :rfc AND estatus != 'rechazado'
                          LIMIT 1";
        $stCheckNuevo = $pdo->prepare($sqlCheckNuevo);
        $stCheckNuevo->execute(array(':emp' => $empresa_id, ':rfc' => $rfc));
        if ($stCheckNuevo->fetch()) {
            $error = 'Este RFC ya existe en el sistema como solicitud de nuevo ingreso';
        } else {
            // Insertar en tabla empleados_nuevo_ingreso
            $sqlIns = "INSERT INTO empleados_nuevo_ingreso (empresa_id, rfc, curp, nombre, apellido_paterno, apellido_materno, estatus, creado_por) 
                       VALUES (:e, :rfc, :curp, :n, :ap, :am, 'nuevo', :u)";
            $stIns = $pdo->prepare($sqlIns);
            $stIns->execute(array(
                ':e' => $empresa_id,
                ':rfc' => $rfc,
                ':curp' => $curp,
                ':n' => $nombre,
                ':ap' => $apellido_paterno,
                ':am' => $apellido_materno,
                ':u' => $usuario_id
            ));
            $nuevo_ingreso_id = $pdo->lastInsertId();
            
            // Redirigir a la gestión del empleado
            header('Location: contratos_gestionar.php?nuevo_ingreso_id=' . $nuevo_ingreso_id);
            exit;
        }
    }
}

// Procesar recuperación de empleado activo
if ($accion === 'recuperar') {
    $empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;
    
    if ($empleado_id > 0) {
        $sqlEmp = "SELECT e.empleado_id, ed.nombre, ed.apellido_paterno, ed.apellido_materno, ed.rfc, ed.curp, ed.nss
                   FROM empleados e
                   LEFT JOIN empleados_demograficos ed ON ed.empleado_id = e.empleado_id
                   WHERE e.empresa_id = :emp AND e.es_activo = 1 AND e.empleado_id = :eid
                   LIMIT 1";
        $stEmp = $pdo->prepare($sqlEmp);
        $stEmp->execute(array(':emp' => $empresa_id, ':eid' => $empleado_id));
        $empleado_activo = $stEmp->fetch(PDO::FETCH_ASSOC);
    }
}

$active_menu = 'contratos_generar';
$page_title = 'Agregar Empleado Nuevo';
require_once __DIR__ . '/../includes/layout/head.php';
require_once __DIR__ . '/../includes/layout/navbar.php';
require_once __DIR__ . '/../includes/layout/sidebar.php';
require_once __DIR__ . '/../includes/layout/content_open.php';
?>

<div class="page-header page-header-light">
  <div class="page-header-content header-elements-md-inline">
    <div class="page-title d-flex">
      <h4><i class="icon-user-plus mr-2"></i> <span class="font-weight-semibold">Agregar Empleado Nuevo</span></h4>
    </div>
  </div>
</div>

<div class="content">
  
  <?php if ($accion === 'recuperar' && $empleado_activo): ?>
  <!-- Sección de recuperación de empleado activo -->
  <div class="card border-success">
    <div class="card-header bg-success text-white">
      <h6 class="card-title"><i class="icon-checkmark-circle mr-2"></i> Empleado Activo Encontrado</h6>
    </div>
    <div class="card-body">
      <p class="mb-3">Se encontró un empleado activo con este RFC. Los datos se cargarán automáticamente:</p>
      
      <div class="row mb-3">
        <div class="col-md-3"><strong>RFC:</strong> <?php echo h($empleado_activo['rfc']); ?></div>
        <div class="col-md-3"><strong>CURP:</strong> <?php echo h($empleado_activo['curp'] ?: '-'); ?></div>
        <div class="col-md-3"><strong>NSS:</strong> <?php echo h($empleado_activo['nss'] ?: '-'); ?></div>
      </div>
      
      <div class="row mb-3">
        <div class="col-md-12">
          <strong>Nombre Completo:</strong> <?php echo h(trim($empleado_activo['nombre'] . ' ' . $empleado_activo['apellido_paterno'] . ' ' . $empleado_activo['apellido_materno'])); ?>
        </div>
      </div>
      
      <div class="alert alert-warning mb-3">
        <i class="icon-info22 mr-2"></i>
        Este empleado activo se utilizará para la solicitud de contratación. Se redireccionará a la sección de captura de datos...
      </div>
      
      <a href="contratos_gestionar.php?empleado_id=<?php echo $empleado_activo['empleado_id']; ?>" class="btn btn-success btn-labeled btn-labeled-left">
        <b><i class="icon-arrow-right13"></i></b>
        Continuar con Este Empleado
      </a>
      
      <a href="contratos_nuevo_empleado.php" class="btn btn-light">
        Volver a Crear Nuevo
      </a>
    </div>
  </div>
  
  <?php else: ?>
  <!-- Sección de creación de nuevo empleado -->
  <div class="card">
    <div class="card-header header-elements-inline">
      <h6 class="card-title">Datos Básicos del Nuevo Empleado</h6>
      <div class="header-elements">
        <a href="contratos_generar.php" class="btn btn-light btn-sm">
          <i class="icon-arrow-left13 mr-1"></i> Volver a Lista
        </a>
      </div>
    </div>
    
    <div class="card-body">
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="icon-alert-circle mr-2"></i><?php echo h($error); ?></div>
      <?php endif; ?>
      
      <form method="POST" action="">
        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label for="rfc">RFC *</label>
              <input type="text" id="rfc" name="rfc" class="form-control text-uppercase" maxlength="13" 
                     placeholder="p.ej. ABC123456XYZ" required autofocus>
              <small class="form-text text-muted">10-13 caracteres</small>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label for="curp">CURP</label>
              <input type="text" id="curp" name="curp" class="form-control text-uppercase" maxlength="18" 
                     placeholder="p.ej. HEMC920315HDFRNM09">
              <small class="form-text text-muted">18 caracteres (se extraerán datos)</small>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label for="nombre">Nombre(s) *</label>
              <input type="text" id="nombre" name="nombre" class="form-control" required>
            </div>
          </div>
        </div>
        
        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label for="apellido_paterno">Apellido Paterno</label>
              <input type="text" id="apellido_paterno" name="apellido_paterno" class="form-control">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label for="apellido_materno">Apellido Materno</label>
              <input type="text" id="apellido_materno" name="apellido_materno" class="form-control">
            </div>
          </div>
        </div>
        
        <div class="alert alert-info border-0">
          <i class="icon-info22 mr-2"></i>
          <strong>Nota:</strong> Si el RFC pertenece a un empleado activo, podrás recuperar su información. De lo contrario, se creará un nuevo registro para completar posteriormente.
        </div>
        
        <button type="submit" class="btn btn-primary btn-labeled btn-labeled-left">
          <b><i class="icon-checkmark3"></i></b>
          Continuar
        </button>
        <a href="contratos_generar.php" class="btn btn-light">Cancelar</a>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/layout/footer.php'; ?>

</body>
</html>
