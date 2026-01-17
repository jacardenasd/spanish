<?php
// public/contratos_generar.php
// Lista de empleados en proceso de contratación (nuevo ingreso)

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

// Manejar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'borrar') {
    $nuevo_ingreso_id = isset($_POST['nuevo_ingreso_id']) ? (int)$_POST['nuevo_ingreso_id'] : 0;
    
    if ($nuevo_ingreso_id > 0) {
        // Verificar que existe y pertenece a esta empresa
        $sqlCheck = "SELECT nuevo_ingreso_id, nombre, apellido_paterno, apellido_materno 
                     FROM empleados_nuevo_ingreso 
                     WHERE nuevo_ingreso_id = :nid AND empresa_id = :e LIMIT 1";
        $stCheck = $pdo->prepare($sqlCheck);
        $stCheck->execute(array(':nid' => $nuevo_ingreso_id, ':e' => $empresa_id));
        $ingreso = $stCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($ingreso) {
            // Verificar que NO hay contratos generados
            // Un empleado_nuevo_ingreso se convierte a empleado cuando se crea un contrato
            // Por eso buscamos si hay contratos generados a partir del RFC/CURP
            $sqlCheckDocs = "SELECT COUNT(*) as cnt FROM contratos c
                             WHERE c.empresa_id = :e
                             AND c.estatus NOT IN ('borrador')
                             AND c.empleado_id IN (
                                 SELECT empleado_id FROM empleados_demograficos 
                                 WHERE rfc = (SELECT rfc FROM empleados_nuevo_ingreso WHERE nuevo_ingreso_id = :nid)
                             )";
            $stCheckDocs = $pdo->prepare($sqlCheckDocs);
            $stCheckDocs->execute(array(':nid' => $nuevo_ingreso_id, ':e' => $empresa_id));
            $docCount = $stCheckDocs->fetch(PDO::FETCH_ASSOC)['cnt'];
            
            if ($docCount > 0) {
                echo json_encode(array('ok' => false, 'error' => 'No se puede borrar: hay contratos generados'));
                exit;
            }
            
            // Borrar el registro
            $sqlDel = "DELETE FROM empleados_nuevo_ingreso WHERE nuevo_ingreso_id = :nid";
            $stDel = $pdo->prepare($sqlDel);
            $stDel->execute(array(':nid' => $nuevo_ingreso_id));
            
            echo json_encode(array('ok' => true, 'mensaje' => 'Registro eliminado correctamente'));
            exit;
        }
    }
    
    echo json_encode(array('ok' => false, 'error' => 'Registro no encontrado'));
    exit;
}

// Cargar empleados de nuevo ingreso
$sqlList = "SELECT * FROM empleados_nuevo_ingreso 
            WHERE empresa_id = :e AND estatus != 'rechazado'
            ORDER BY fecha_creacion DESC";
$stList = $pdo->prepare($sqlList);
$stList->execute(array(':e' => $empresa_id));
$empleados = $stList->fetchAll(PDO::FETCH_ASSOC);

$active_menu = 'contratos_generar';
$page_title = 'Kit de Contratación';
require_once __DIR__ . '/../includes/layout/head.php';
require_once __DIR__ . '/../includes/layout/navbar.php';
require_once __DIR__ . '/../includes/layout/sidebar.php';
require_once __DIR__ . '/../includes/layout/content_open.php';
?>

<div class="page-header page-header-light">
  <div class="page-header-content">
    <div class="page-title d-flex">
      <h4><i class="icon-briefcase mr-2"></i> Kit de Contratación</h4>
    </div>
  </div>
</div>

<div class="content">
  
  <div class="card mb-3">
    <div class="card-body d-flex flex-wrap">
      <a href="contratos_importar_empleado.php" class="btn btn-primary btn-labeled btn-labeled-left mr-2 mb-2">
        <b><i class="icon-file-text2"></i></b> Importar Empleado Tipo 1
      </a>
      <a href="contratos_nuevo_empleado.php" class="btn btn-success btn-labeled btn-labeled-left mb-2">
        <b><i class="icon-user-plus"></i></b> Agregar Empleado Nuevo
      </a>
    </div>
  </div>
  
  <div class="card">
    <div class="card-header">
      <h6 class="card-title">Empleados</h6>
      <span class="badge badge-primary"><?php echo count($empleados); ?> registros</span>
    </div>
    <div class="card-body">
      <table class="table table-sm table-hover datatable-basic">
        <thead class="bg-light">
          <tr>
            <th>Nombre</th>
            <th>RFC</th>
            <th>CURP</th>
            <th>Fecha alta</th>
            <th>Completitud</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($empleados) === 0): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Sin empleados de nuevo ingreso</td></tr>
          <?php else: foreach ($empleados as $emp): ?>
            <?php
              // Verificar si hay contratos generados a partir de este nuevo_ingreso
              $sqlCheckContratos = "SELECT COUNT(*) as cnt FROM contratos c
                                    WHERE c.empresa_id = :e
                                    AND c.estatus NOT IN ('borrador')
                                    AND c.empleado_id IN (
                                        SELECT empleado_id FROM empleados_demograficos 
                                        WHERE rfc = :rfc
                                    )";
              $stCheckContratos = $pdo->prepare($sqlCheckContratos);
              $stCheckContratos->execute(array(':e' => $empresa_id, ':rfc' => $emp['rfc']));
              $tieneContratos = $stCheckContratos->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
            ?>
            <tr>
              <td>
                <strong><?php echo h(trim($emp['nombre'] . ' ' . $emp['apellido_paterno'] . ' ' . $emp['apellido_materno'])); ?></strong>
              </td>
              <td><?php echo h($emp['rfc'] ? $emp['rfc'] : '-'); ?></td>
              <td><?php echo h($emp['curp'] ? $emp['curp'] : '-'); ?></td>
              <td><?php echo !empty($emp['fecha_creacion']) ? date('d/m/Y', strtotime($emp['fecha_creacion'])) : '-'; ?></td>
              <td>
                <?php if ($emp['datos_completos'] == 1): ?>
                  <span class="badge badge-success"><i class="icon-checkmark"></i> Completo</span>
                <?php else: ?>
                  <span class="badge badge-warning"><i class="icon-warning"></i> Incompleto</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="contratos_gestionar.php?nuevo_ingreso_id=<?php echo $emp['nuevo_ingreso_id']; ?>" class="btn btn-sm btn-info">
                  <i class="icon-pencil5"></i> Gestionar
                </a>
                <?php if (!$tieneContratos): ?>
                  <button type="button" class="btn btn-sm btn-danger btnBorrar" data-id="<?php echo $emp['nuevo_ingreso_id']; ?>" 
                          title="Borrar registro">
                    <i class="icon-trash"></i>
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Modal de confirmación para borrar -->
<div class="modal fade" id="modalBorrar" tabindex="-1" role="dialog" aria-labelledby="modalBorrarLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="modalBorrarLabel">
          <i class="icon-warning mr-2"></i> Confirmar Eliminación
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p class="mb-0">
          <strong>¿Está seguro de que desea borrar este registro?</strong>
        </p>
        <p id="modalNombreEmpleado" class="mt-2 mb-0 text-muted"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="btnConfirmarBorrado">
          <i class="icon-trash mr-1"></i> Borrar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Banda de alerta de éxito al borrar -->
<div id="alertaExitoBorrar" class="alert alert-success alert-dismissible fade" style="position: fixed; top: 80px; left: 20px; right: 20px; z-index: 9999; display: none;">
  <i class="icon-checkmark-circle mr-2"></i>
  <span id="alertaExitoMensaje">Registro eliminado correctamente</span>
  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
    <span aria-hidden="true">&times;</span>
  </button>
</div>

<?php require_once __DIR__ . '/../includes/layout/footer.php'; ?>

<script src="<?php echo ASSET_BASE; ?>global_assets/js/plugins/tables/datatables/datatables.min.js"></script>
<script src="<?php echo ASSET_BASE; ?>global_assets/js/plugins/tables/datatables/extensions/responsive.min.js"></script>

<script>
$(function() {
  $('.datatable-basic').DataTable({
    columnDefs: [{
      orderable: false,
      targets: [6]
    }],
    displayLength: 10,
    lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
    language: {
      search: '_INPUT_',
      searchPlaceholder: 'Buscar...',
      processing: 'Procesando...',
      paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' },
      info: 'Mostrando _START_ a _END_ de _TOTAL_ registros'
    },
    dom: '<"datatable-header"<"DTS_length_wrapper"><"DTS_search_wrapper">l>t<"datatable-footer"<"DTS_pagination_wrapper">p>',
    lengthChange: false
  });

  // Variables para mantener estado del modal
  var idParaBorrar = null;
  var $rowParaBorrar = null;

  // Manejar click en botón borrar
  $(document).on('click', '.btnBorrar', function() {
    var $btn = $(this);
    idParaBorrar = $btn.data('id');
    $rowParaBorrar = $btn.closest('tr');
    var nombre = $rowParaBorrar.find('td:first strong').text();

    // Llenar datos en el modal y mostrar
    $('#modalNombreEmpleado').text('Empleado: ' + nombre);
    $('#modalBorrar').modal('show');
  });

  // Manejar confirmación en el modal
  $(document).on('click', '#btnConfirmarBorrado', function() {
    var $btn = $(this);
    $btn.prop('disabled', true);
    $btn.html('<i class="icon-spinner2 fa-spin mr-1"></i> Borrando...');

    $.ajax({
      url: '',
      method: 'POST',
      data: {
        accion: 'borrar',
        nuevo_ingreso_id: idParaBorrar
      },
      dataType: 'json'
    }).done(function(resp) {
      if (resp.ok) {
        // Cerrar modal
        $('#modalBorrar').modal('hide');
        
        // Mostrar alerta de éxito con fadeIn
        $('#alertaExitoMensaje').text(resp.mensaje);
        $('#alertaExitoBorrar').addClass('show').fadeIn(300);
        
        // Auto-ocultar después de 4 segundos
        setTimeout(function() {
          $('#alertaExitoBorrar').fadeOut(300, function() {
            $(this).removeClass('show');
          });
        }, 4000);
        
        // Animar eliminación de la fila
        $rowParaBorrar.fadeOut(300, function() {
          $rowParaBorrar.remove();
          // Recargar página si es la última fila
          if ($('.datatable-basic tbody tr').length === 0) {
            setTimeout(function() { location.reload(); }, 1500);
          }
        });
      } else {
        alert('Error: ' + resp.error);
        $btn.prop('disabled', false);
        $btn.html('<i class="icon-trash mr-1"></i> Borrar');
      }
    }).fail(function() {
      alert('Error de comunicación con el servidor');
      $btn.prop('disabled', false);
      $btn.html('<i class="icon-trash mr-1"></i> Borrar');
    });
  });

  // Resetear variables cuando se cierra el modal
  $('#modalBorrar').on('hidden.bs.modal', function() {
    idParaBorrar = null;
    $rowParaBorrar = null;
    var $btn = $('#btnConfirmarBorrado');
    $btn.prop('disabled', false);
    $btn.html('<i class="icon-trash mr-1"></i> Borrar');
  });
});
</script>

</body>
</html>
