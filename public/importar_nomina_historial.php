<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';

require_login();
require_password_change_redirect();
require_demograficos_redirect();
require_empresa();
require_perm('nomina.importar');

$page_title = 'Historial de Importaciones | SGRH';
include __DIR__ . '/../includes/layout/head.php';
include __DIR__ . '/../includes/layout/navbar.php';
include __DIR__ . '/../includes/layout/sidebar.php';

// Obtener historial de importaciones
$stmt = $pdo->prepare("
    SELECT 
        ni.import_id,
        ni.empresa_id,
        ni.archivo_nombre,
        ni.total_registros,
        ni.status,
        ni.mensaje,
        ni.created_at,
        u.nombre AS usuario_nombre,
        u.apellido_paterno AS usuario_apellido,
        e.nombre AS empresa_nombre
    FROM nomina_importaciones ni
    LEFT JOIN usuarios u ON u.usuario_id = ni.usuario_id
    LEFT JOIN empresas e ON e.empresa_id = ni.empresa_id
    ORDER BY ni.created_at DESC
    LIMIT 50
");
$stmt->execute();
$importaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ver detalle de una importación
$detalle = [];
$import_id_sel = isset($_GET['import_id']) ? (int)$_GET['import_id'] : 0;
if ($import_id_sel > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            import_detalle_id, no_emp, rfc_base, accion, mensaje, created_at, payload_json
        FROM nomina_import_detalle
        WHERE import_id = ?
        ORDER BY import_detalle_id ASC
    ");
    $stmt->execute([$import_id_sel]);
    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="page-content">
  <div class="content-wrapper">
    <div class="content">
      
      <!-- Historial de importaciones -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">Historial de Importaciones</h5>
          <a href="importar_nomina.php" class="btn btn-primary btn-sm">
            <i class="icon-upload"></i> Nueva Importación
          </a>
        </div>
        <div class="card-body">
          <?php if (empty($importaciones)): ?>
            <div class="alert alert-info">No hay importaciones registradas.</div>
          <?php else: ?>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
              <table class="table table-sm">
                <thead style="position: sticky; top: 0; background-color: #fff; z-index: 10;">
                  <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Archivo</th>
                    <th>Empresa</th>
                    <th>Usuario</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Mensaje</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($importaciones as $imp): ?>
                    <tr class="<?php echo $import_id_sel === (int)$imp['import_id'] ? 'table-info' : ''; ?>">
                      <td><?php echo (int)$imp['import_id']; ?></td>
                      <td><?php echo date('d/m/Y H:i', strtotime($imp['created_at'])); ?></td>
                      <td><small><?php echo htmlspecialchars($imp['archivo_nombre']); ?></small></td>
                      <td><?php echo htmlspecialchars($imp['empresa_nombre'] ?? 'N/A'); ?></td>
                      <td><?php echo htmlspecialchars(($imp['usuario_nombre'] ?? '') . ' ' . ($imp['usuario_apellido'] ?? '')); ?></td>
                      <td><span class="badge badge-secondary"><?php echo (int)$imp['total_registros']; ?></span></td>
                      <td>
                        <?php if ($imp['status'] === 'procesado'): ?>
                          <span class="badge badge-success">Procesado</span>
                        <?php elseif ($imp['status'] === 'cargado'): ?>
                          <span class="badge badge-warning">Cargado</span>
                        <?php else: ?>
                          <span class="badge badge-danger"><?php echo htmlspecialchars($imp['status']); ?></span>
                        <?php endif; ?>
                      </td>
                      <td><small><?php echo htmlspecialchars($imp['mensaje'] ?? ''); ?></small></td>
                      <td>
                        <a href="?import_id=<?php echo (int)$imp['import_id']; ?>" 
                           class="btn btn-sm btn-info" title="Ver detalle">
                          <i class="icon-eye"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Detalle de importación -->
      <?php if (!empty($detalle)): ?>
        <div class="card mt-3">
          <div class="card-header">
            <h5 class="card-title">Detalle de Importación #<?php echo $import_id_sel; ?></h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <strong>Total de registros:</strong> <?php echo count($detalle); ?>
              <?php
              $errores = array_filter($detalle, fn($d) => $d['accion'] === 'error');
              $insertados = array_filter($detalle, fn($d) => $d['accion'] === 'insert');
              $actualizados = array_filter($detalle, fn($d) => $d['accion'] === 'update');
              ?>
              | <span class="badge badge-success">Insertados: <?php echo count($insertados); ?></span>
              | <span class="badge badge-info">Actualizados: <?php echo count($actualizados); ?></span>
              | <span class="badge badge-danger">Errores: <?php echo count($errores); ?></span>
            </div>

            <!-- Filtros -->
            <div class="mb-3">
              <button class="btn btn-sm btn-outline-danger" onclick="filtrarDetalle('error')">Ver solo errores</button>
              <button class="btn btn-sm btn-outline-success" onclick="filtrarDetalle('insert')">Ver solo insertados</button>
              <button class="btn btn-sm btn-outline-info" onclick="filtrarDetalle('update')">Ver solo actualizados</button>
              <button class="btn btn-sm btn-outline-secondary" onclick="filtrarDetalle('')">Ver todos</button>
            </div>

            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
              <table class="table table-sm table-bordered" id="tabla-detalle">
                <thead style="position: sticky; top: 0; background-color: #fff; z-index: 10;">
                  <tr>
                    <th style="min-width: 80px;">No. Emp</th>
                    <th style="min-width: 100px;">RFC</th>
                    <th style="min-width: 90px;">Acción</th>
                    <th style="min-width: 150px;">Mensaje</th>
                    <th style="min-width: 70px;">Fecha</th>
                    <th style="min-width: 70px;">Datos</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($detalle as $det): ?>
                    <tr class="detalle-row" data-accion="<?php echo htmlspecialchars($det['accion']); ?>">
                      <td><?php echo htmlspecialchars($det['no_emp']); ?></td>
                      <td><?php echo htmlspecialchars($det['rfc_base']); ?></td>
                      <td>
                        <?php if ($det['accion'] === 'insert'): ?>
                          <span class="badge badge-success">INSERT</span>
                        <?php elseif ($det['accion'] === 'update'): ?>
                          <span class="badge badge-info">UPDATE</span>
                        <?php else: ?>
                          <span class="badge badge-danger">ERROR</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars($det['mensaje']); ?></td>
                      <td><small><?php echo date('H:i:s', strtotime($det['created_at'])); ?></small></td>
                      <td>
                        <button class="btn btn-xs btn-link" 
                                onclick="verPayload(<?php echo htmlspecialchars(json_encode($det['payload_json'])); ?>)">
                          <i class="icon-file-text2"></i> Ver
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- Modal para ver payload -->
<div class="modal fade" id="modalPayload" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Datos del Registro</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <pre id="payload-content" style="max-height: 500px; overflow-y: auto;"></pre>
      </div>
    </div>
  </div>
</div>

<script>
function filtrarDetalle(accion) {
  const rows = document.querySelectorAll('.detalle-row');
  rows.forEach(row => {
    if (accion === '' || row.dataset.accion === accion) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

function verPayload(payload) {
  const obj = typeof payload === 'string' ? JSON.parse(payload) : payload;
  document.getElementById('payload-content').textContent = JSON.stringify(obj, null, 2);
  $('#modalPayload').modal('show');
}
</script>

<?php include __DIR__ . '/../includes/layout/scripts.php'; ?>
