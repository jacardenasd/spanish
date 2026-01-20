<?php
// public/contratos_importar_empleado.php
// Lista de empleados con tipo_empleado_id = 1 para importar y generar contratos permanentes

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

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Cargar empleados con tipo_empleado_id = 1
$sqlEmpleados = "SELECT empleado_id, no_emp, rfc_base, curp, nombre, apellido_paterno, apellido_materno, 
                        puesto_nombre, fecha_ingreso, salario_mensual
                 FROM empleados 
                 WHERE empresa_id = :emp AND es_activo = 1 AND tipo_empleado_id = 1
                 ORDER BY nombre, apellido_paterno";
$stEmpleados = $pdo->prepare($sqlEmpleados);
$stEmpleados->execute(array(':emp' => $empresa_id));
$empleados_tipo1 = $stEmpleados->fetchAll(PDO::FETCH_ASSOC);

$active_menu = 'contratos_generar';
$page_title = 'Importar Empleado para Contrato Permanente';
require_once __DIR__ . '/../includes/layout/head.php';
require_once __DIR__ . '/../includes/layout/navbar.php';
require_once __DIR__ . '/../includes/layout/sidebar.php';
require_once __DIR__ . '/../includes/layout/content_open.php';
?>

<div class="page-header page-header-light">
  <div class="page-header-content">
    <div class="page-title d-flex">
      <h4><i class="icon-users mr-2"></i> Seleccionar Empleado para Contrato Permanente</h4>
      <a href="contratos_generar.php" class="btn btn-light btn-sm ml-auto">
        <i class="icon-arrow-left13"></i> Volver a Lista
      </a>
    </div>
  </div>
</div>

<div class="content">
  <div class="card">
    <div class="card-header">
      <h6 class="card-title">Empleados con Tipo: Temporal / Determinado (Tipo 1)</h6>
      <p class="text-muted mb-0 small">Seleccione un empleado para generar su contrato permanente</p>
    </div>
    <div class="card-body">
      <?php if (count($empleados_tipo1) > 0): ?>
        <div class="table-responsive">
          <table class="table datatable-basic">
            <thead>
              <tr>
                <th>No. Empleado</th>
                <th>Nombre Completo</th>
                <th>RFC</th>
                <th>CURP</th>
                <th>Puesto</th>
                <th>Fecha Ingreso</th>
                <th>Salario Mensual</th>
                <th width="100" class="text-center">Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($empleados_tipo1 as $emp): 
                $nombreCompleto = trim($emp['nombre'] . ' ' . ($emp['apellido_paterno'] ?: '') . ' ' . ($emp['apellido_materno'] ?: ''));
              ?>
              <tr>
                <td><?php echo h($emp['no_emp']); ?></td>
                <td><strong><?php echo h($nombreCompleto); ?></strong></td>
                <td><?php echo h($emp['rfc_base']); ?></td>
                <td class="text-muted"><?php echo h($emp['curp'] ?: '-'); ?></td>
                <td><?php echo h($emp['puesto_nombre'] ?: '-'); ?></td>
                <td><?php echo $emp['fecha_ingreso'] ? date('d/m/Y', strtotime($emp['fecha_ingreso'])) : '-'; ?></td>
                <td><?php echo $emp['salario_mensual'] ? '$' . number_format($emp['salario_mensual'], 2) : '-'; ?></td>
                <td class="text-center">
                  <a href="contratos_gestionar.php?empleado_id=<?php echo $emp['empleado_id']; ?>" 
                     class="btn btn-sm btn-primary" title="Seleccionar">
                    <i class="icon-checkmark3"></i> Seleccionar
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="alert alert-info">
          <i class="icon-info22 mr-2"></i>No se encontraron empleados con tipo "Temporal / Determinado" (tipo_empleado_id = 1).
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="<?php echo ASSET_BASE; ?>global_assets/js/plugins/tables/datatables/datatables.min.js"></script>
<script>
$(function() {
    $('.datatable-basic').DataTable({
        language: {
            url: '<?php echo ASSET_BASE; ?>global_assets/locales/es-ES.json'
        },
        pageLength: 25,
        order: [[1, 'asc']]
    });
});
</script>

</body>
</html>