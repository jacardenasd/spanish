<?php
/**
 * public/plantilla.php
 * SGRH - Vista de Consulta de Plantilla Autorizada
 * Interfaz de solo lectura para usuarios con permiso plantilla.ver
 * Compatible PHP 5.7 - NO usar operador ??
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/conexion.php';

require_login();
require_empresa();
require_password_change_redirect();
require_demograficos_redirect();

// Requiere al menos plantilla.ver
if (!can('plantilla.ver') && !can('plantilla.admin') && !can('organizacion.admin')) {
    header('Location: sin_permiso.php');
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$empresa_id = (int)$_SESSION['empresa_id'];

// Helpers
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Obtener estadísticas
$stats_sql = "SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN estado = 'activa' THEN 1 ELSE 0 END) AS activas,
                SUM(CASE WHEN estado = 'activa' AND empleado_id IS NOT NULL THEN 1 ELSE 0 END) AS ocupadas,
                SUM(CASE WHEN estado = 'activa' AND empleado_id IS NULL THEN 1 ELSE 0 END) AS vacantes,
                SUM(CASE WHEN estado = 'congelada' THEN 1 ELSE 0 END) AS congeladas,
                SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) AS canceladas
              FROM org_plantilla_autorizada 
              WHERE empresa_id = :empresa_id";
$stmt_stats = $pdo->prepare($stats_sql);
$stmt_stats->execute([':empresa_id' => $empresa_id]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Obtener unidades para filtro
$unidades_sql = "SELECT unidad_id, nombre FROM org_unidades WHERE empresa_id = :empresa_id AND estatus = 1 ORDER BY nombre";
$stmt_unidades = $pdo->prepare($unidades_sql);
$stmt_unidades->execute([':empresa_id' => $empresa_id]);
$unidades = $stmt_unidades->fetchAll(PDO::FETCH_ASSOC);

// Obtener adscripciones para filtro
$adscripciones_sql = "SELECT a.adscripcion_id, a.nombre, a.unidad_id, u.nombre AS unidad_nombre
                      FROM org_adscripciones a
                      INNER JOIN org_unidades u ON u.unidad_id = a.unidad_id
                      WHERE a.empresa_id = :empresa_id AND a.estatus = 1
                      ORDER BY u.nombre, a.nombre";
$stmt_adscripciones = $pdo->prepare($adscripciones_sql);
$stmt_adscripciones->execute([':empresa_id' => $empresa_id]);
$adscripciones = $stmt_adscripciones->fetchAll(PDO::FETCH_ASSOC);

// Filtros
$filtro_estado = isset($_GET['estado']) ? trim($_GET['estado']) : 'todas';
$filtro_unidad = isset($_GET['unidad_id']) ? (int)$_GET['unidad_id'] : 0;
$filtro_departamento = isset($_GET['adscripcion_id']) ? (int)$_GET['adscripcion_id'] : 0;

// Construir query con filtros
$where_parts = ['p.empresa_id = :empresa_id'];
$params = [':empresa_id' => $empresa_id];

if ($filtro_estado !== 'todas') {
    $where_parts[] = 'p.estado = :estado';
    $params[':estado'] = $filtro_estado;
}

if ($filtro_unidad > 0) {
    $where_parts[] = 'p.unidad_id = :unidad_id';
    $params[':unidad_id'] = $filtro_unidad;
}

if ($filtro_departamento > 0) {
    $where_parts[] = 'p.adscripcion_id = :adscripcion_id';
    $params[':adscripcion_id'] = $filtro_departamento;
}

$where_sql = implode(' AND ', $where_parts);

// Obtener plazas
$sql = "SELECT 
            p.*,
            u.nombre AS unidad_nombre,
            a.nombre AS adscripcion_nombre,
            pu.nombre AS puesto_nombre,
            emp.no_emp AS empleado_no_emp,
            emp.nombre AS empleado_nombre,
            emp.apellido_paterno AS empleado_apellido_paterno,
            CASE 
                WHEN p.empleado_id IS NOT NULL THEN 'Ocupada'
                WHEN p.estado = 'activa' THEN 'Vacante'
                ELSE 'N/A'
            END AS estado_ocupacion
        FROM org_plantilla_autorizada p
        LEFT JOIN org_unidades u ON u.unidad_id = p.unidad_id
        LEFT JOIN org_adscripciones a ON a.adscripcion_id = p.adscripcion_id
        LEFT JOIN org_puestos pu ON pu.puesto_id = p.puesto_id
        LEFT JOIN empleados emp ON emp.empleado_id = p.empleado_id
        WHERE $where_sql
        ORDER BY p.fecha_creacion DESC, p.codigo_plaza";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$plazas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Plantilla Autorizada';
$active_menu = 'plantilla';
$extra_css = [
    'global_assets/css/icons/icomoon/styles.min.css',
    'global_assets/css/plugins/tables/datatables/datatables.min.css',
];
$extra_js = [
    'global_assets/js/plugins/tables/datatables/datatables.min.js',
];

require_once __DIR__ . '/../includes/layout/head.php';
require_once __DIR__ . '/../includes/layout/navbar.php';
require_once __DIR__ . '/../includes/layout/sidebar.php';
require_once __DIR__ . '/../includes/layout/content_open.php';
?>

<div class="page-header page-header-light">
    <div class="page-header-content header-elements-lg-inline">
        <div class="page-title d-flex">
            <h4><i class="icon-office mr-2"></i><span class="font-weight-semibold"><?php echo h($page_title); ?></span></h4>
            <a href="#" class="header-elements-toggle text-body d-lg-none"><i class="icon-more"></i></a>
        </div>
    </div>

    <div class="breadcrumb-line breadcrumb-line-light header-elements-lg-inline">
        <div class="d-flex">
            <div class="breadcrumb">
                <a href="<?php echo ASSET_BASE; ?>public/index.php" class="breadcrumb-item"><i class="icon-home2 mr-2"></i> Inicio</a>
                <span class="breadcrumb-item">Organización</span>
                <span class="breadcrumb-item active">Plantilla Autorizada</span>
            </div>
        </div>
    </div>
</div>

<div class="content">

    <!-- Tarjetas de estadísticas -->
    <div class="row">
        <div class="col-sm-6 col-xl-2">
            <div class="card card-body">
                <div class="media">
                    <div class="mr-3 align-self-center">
                        <i class="icon-office icon-3x text-secondary"></i>
                    </div>
                    <div class="media-body text-right">
                        <h3 class="font-weight-semibold mb-0"><?php echo (int)$stats['total']; ?></h3>
                        <span class="text-uppercase font-size-sm text-muted">Total Plazas</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-body">
                <div class="media">
                    <div class="mr-3 align-self-center">
                        <i class="icon-checkmark-circle icon-3x text-success"></i>
                    </div>
                    <div class="media-body text-right">
                        <h3 class="font-weight-semibold mb-0"><?php echo (int)$stats['activas']; ?></h3>
                        <span class="text-uppercase font-size-sm text-muted">Activas</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-body">
                <div class="media">
                    <div class="mr-3 align-self-center">
                        <i class="icon-user-check icon-3x text-info"></i>
                    </div>
                    <div class="media-body text-right">
                        <h3 class="font-weight-semibold mb-0"><?php echo (int)$stats['ocupadas']; ?></h3>
                        <span class="text-uppercase font-size-sm text-muted">Ocupadas</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-body">
                <div class="media">
                    <div class="mr-3 align-self-center">
                        <i class="icon-user-plus icon-3x text-warning"></i>
                    </div>
                    <div class="media-body text-right">
                        <h3 class="font-weight-semibold mb-0"><?php echo (int)$stats['vacantes']; ?></h3>
                        <span class="text-uppercase font-size-sm text-muted">Vacantes</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-body">
                <div class="media">
                    <div class="mr-3 align-self-center">
                        <i class="icon-pause2 icon-3x text-secondary"></i>
                    </div>
                    <div class="media-body text-right">
                        <h3 class="font-weight-semibold mb-0"><?php echo (int)$stats['congeladas']; ?></h3>
                        <span class="text-uppercase font-size-sm text-muted">Congeladas</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-body">
                <div class="media">
                    <div class="mr-3 align-self-center">
                        <i class="icon-cross2 icon-3x text-danger"></i>
                    </div>
                    <div class="media-body text-right">
                        <h3 class="font-weight-semibold mb-0"><?php echo (int)$stats['canceladas']; ?></h3>
                        <span class="text-uppercase font-size-sm text-muted">Canceladas</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de plazas -->
    <div class="card">
        <div class="card-header header-elements-inline">
            <h5 class="card-title">Lista de Plazas</h5>
            <div class="header-elements">
                <span class="badge badge-primary"><?php echo count($plazas); ?> plazas</span>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card-body">
            <form method="get" action="" class="form-inline">
                <label class="mr-2">Estado:</label>
                <select name="estado" class="form-control form-control-sm mr-3">
                    <option value="todas" <?php echo $filtro_estado === 'todas' ? 'selected' : ''; ?>>Todas</option>
                    <option value="activa" <?php echo $filtro_estado === 'activa' ? 'selected' : ''; ?>>Activas</option>
                    <option value="congelada" <?php echo $filtro_estado === 'congelada' ? 'selected' : ''; ?>>Congeladas</option>
                    <option value="cancelada" <?php echo $filtro_estado === 'cancelada' ? 'selected' : ''; ?>>Canceladas</option>
                </select>

                <label class="mr-2">Unidad:</label>
                <select name="unidad_id" id="filtro_unidad_id" class="form-control form-control-sm mr-2">
                    <option value="0">Todas las unidades</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?php echo (int)$u['unidad_id']; ?>" <?php echo $filtro_unidad === (int)$u['unidad_id'] ? 'selected' : ''; ?>>
                            <?php echo h($u['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="mr-2">Departamento:</label>
                <select name="adscripcion_id" id="filtro_adscripcion_id" class="form-control form-control-sm mr-2">
                    <option value="0">Todos los departamentos</option>
                    <?php foreach ($adscripciones as $a): ?>
                        <?php if ($filtro_unidad == 0 || (int)$a['unidad_id'] === $filtro_unidad): ?>
                        <option value="<?php echo (int)$a['adscripcion_id']; ?>" <?php echo $filtro_departamento === (int)$a['adscripcion_id'] ? 'selected' : ''; ?>>
                            <?php echo h($a['nombre']); ?>
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-sm btn-secondary">
                    <i class="icon-filter3"></i> Filtrar
                </button>
                <a href="?" class="btn btn-sm btn-light ml-2">
                    <i class="icon-reset"></i> Limpiar
                </a>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover datatable-basic">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Unidad</th>
                        <th>Departamento</th>
                        <th>Puesto</th>
                        <th>Fecha Creación</th>
                        <th>Estado</th>
                        <th>Ocupación</th>
                        <th>Empleado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plazas as $p): ?>
                        <?php 
                        $badge_estado = $p['estado'] === 'activa' ? 'success' : ($p['estado'] === 'congelada' ? 'secondary' : 'danger');
                        $badge_ocupacion = $p['estado_ocupacion'] === 'Ocupada' ? 'info' : ($p['estado_ocupacion'] === 'Vacante' ? 'warning' : 'dark');
                        ?>
                        <tr>
                            <td><strong><?php echo h($p['codigo_plaza']); ?></strong></td>
                            <td><?php echo h($p['unidad_nombre']); ?></td>
                            <td><?php echo $p['adscripcion_nombre'] ? h($p['adscripcion_nombre']) : '<span class="text-muted">-</span>'; ?></td>
                            <td><?php echo $p['puesto_nombre'] ? h($p['puesto_nombre']) : '<span class="text-muted">-</span>'; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($p['fecha_creacion'])); ?></td>
                            <td><span class="badge badge-<?php echo $badge_estado; ?>"><?php echo ucfirst($p['estado']); ?></span></td>
                            <td><span class="badge badge-<?php echo $badge_ocupacion; ?>"><?php echo h($p['estado_ocupacion']); ?></span></td>
                            <td>
                                <?php if ($p['empleado_id']): ?>
                                    <span class="font-weight-semibold"><?php echo h($p['empleado_no_emp']); ?></span><br>
                                    <small><?php echo h($p['empleado_nombre'] . ' ' . $p['empleado_apellido_paterno']); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-info" onclick='verDetalle(<?php echo json_encode($p, JSON_UNESCAPED_UNICODE); ?>)' data-toggle="modal" data-target="#modalVerDetalle">
                                    <i class="icon-eye"></i> Ver
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal: Ver Detalle -->
<div class="modal fade" id="modalVerDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="icon-eye mr-2"></i>Detalle de Plaza</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="detalle_contenido">
                <!-- Se llena por JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/layout/footer.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
?>

<script>
$(document).ready(function() {
    $('.datatable-basic').DataTable({
        language: {
            url: '<?php echo ASSET_BASE; ?>global_assets/js/plugins/tables/datatables/es-ES.json'
        },
        order: [[4, 'desc']],
        columnDefs: [{ orderable: false, targets: [8] }]
    });

    // Filtrado Ajax de departamentos al cambiar unidad
    $('#filtro_unidad_id').on('change', function() {
        var unidad_id = $(this).val();
        var departamento_select = $('#filtro_adscripcion_id');
        
        if (unidad_id == '0') {
            // Mostrar todos los departamentos
            departamento_select.find('option').show();
        } else {
            // Filtrar departamentos por Ajax
            $.ajax({
                url: 'ajax_get_adscripciones.php',
                method: 'GET',
                data: { unidad_id: unidad_id },
                dataType: 'json',
                success: function(data) {
                    departamento_select.find('option:not(:first)').remove();
                    $.each(data, function(index, dept) {
                        departamento_select.append(
                            $('<option></option>')
                                .attr('value', dept.adscripcion_id)
                                .text(dept.nombre)
                        );
                    });
                }
            });
        }
    });
});

function verDetalle(p) {
    var html = '<div class="table-responsive"><table class="table table-bordered">';
    html += '<tr><th width="30%">Código Plaza</th><td><strong>' + p.codigo_plaza + '</strong></td></tr>';
    html += '<tr><th>Unidad</th><td>' + p.unidad_nombre + '</td></tr>';
    html += '<tr><th>Departamento</th><td>' + (p.adscripcion_nombre || '<em>-</em>') + '</td></tr>';
    html += '<tr><th>Puesto</th><td>' + (p.puesto_nombre || '<em>-</em>') + '</td></tr>';
    html += '<tr><th>Estado</th><td><span class="badge badge-' + (p.estado === 'activa' ? 'success' : (p.estado === 'congelada' ? 'secondary' : 'danger')) + '">' + p.estado.toUpperCase() + '</span></td></tr>';
    html += '<tr><th>Fecha Creación</th><td>' + p.fecha_creacion + '</td></tr>';
    html += '<tr><th>Justificación Creación</th><td>' + p.justificacion_creacion + '</td></tr>';
    
    if (p.fecha_congelacion) {
        html += '<tr class="table-secondary"><th>Fecha Congelación</th><td>' + p.fecha_congelacion + '</td></tr>';
        html += '<tr class="table-secondary"><th>Justificación Congelación</th><td>' + (p.justificacion_congelacion || '') + '</td></tr>';
    }
    
    if (p.fecha_cancelacion) {
        html += '<tr class="table-danger"><th>Fecha Cancelación</th><td>' + p.fecha_cancelacion + '</td></tr>';
        html += '<tr class="table-danger"><th>Justificación Cancelación</th><td>' + (p.justificacion_cancelacion || '') + '</td></tr>';
    }
    
    if (p.empleado_id) {
        html += '<tr class="table-info"><th>Empleado Asignado</th><td>' + (p.empleado_no_emp || '') + ' - ' + (p.empleado_nombre || '') + ' ' + (p.empleado_apellido_paterno || '') + '</td></tr>';
        html += '<tr class="table-info"><th>Fecha Asignación</th><td>' + (p.fecha_asignacion || '') + '</td></tr>';
    }
    
    if (p.observaciones) {
        html += '<tr><th>Observaciones</th><td>' + p.observaciones + '</td></tr>';
    }
    
    html += '</table></div>';
    $('#detalle_contenido').html(html);
}
</script>

<?php require_once __DIR__ . '/../includes/layout/scripts.php'; ?>
