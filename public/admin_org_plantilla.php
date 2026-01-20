<?php
// public/admin_org_plantilla.php
// SGRH - Gestión de Plazas Autorizadas Individuales
// Cada registro representa UNA plaza con su historial completo
// Compatible PHP 5.7 - NO usar operador ??

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/conexion.php';

require_login();
require_empresa();
require_password_change_redirect();
require_demograficos_redirect();
require_perm('organizacion.admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$empresa_id = (int)$_SESSION['empresa_id'];
$usuario_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

// Helpers
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function bitacora($modulo, $accion, $detalle = null) {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE) session_start();
    $empresa_id = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : null;
    $usuario_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

    $sql = "INSERT INTO bitacora (empresa_id, usuario_id, modulo, accion, detalle_json, ip)
            VALUES (:empresa_id, :usuario_id, :modulo, :accion, :detalle_json, :ip)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':empresa_id' => $empresa_id,
        ':usuario_id' => $usuario_id,
        ':modulo' => (string)$modulo,
        ':accion' => (string)$accion,
        ':detalle_json' => $detalle !== null ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : null,
        ':ip' => $ip,
    ]);
}

// Variables de control
$flash = null;
$flash_type = 'info';

// =======================
// PROCESAMIENTO POST
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $flash = 'Token de seguridad inválido.';
        $flash_type = 'danger';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        try {
            if ($action === 'crear_plaza') {
                // Crear nueva plaza
                $codigo = isset($_POST['codigo_plaza']) && $_POST['codigo_plaza'] !== '' ? trim($_POST['codigo_plaza']) : null;
                $unidad_id = isset($_POST['unidad_id']) ? (int)$_POST['unidad_id'] : 0;
                $adscripcion_id = isset($_POST['adscripcion_id']) && $_POST['adscripcion_id'] !== '' ? (int)$_POST['adscripcion_id'] : null;
                $puesto_id = isset($_POST['puesto_id']) && $_POST['puesto_id'] !== '' ? (int)$_POST['puesto_id'] : null;
                $fecha_creacion = isset($_POST['fecha_creacion']) && $_POST['fecha_creacion'] !== '' ? $_POST['fecha_creacion'] : date('Y-m-d');
                $justificacion = isset($_POST['justificacion_creacion']) ? trim($_POST['justificacion_creacion']) : '';
                $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';

                if ($unidad_id <= 0) throw new RuntimeException('Debes seleccionar una Unidad.');
                if (empty($justificacion)) throw new RuntimeException('Debes proporcionar una justificación.');

                // Si no se proporciona código, generar uno automático
                if (empty($codigo)) {
                    $stmt_count = $pdo->prepare("SELECT COUNT(*) + 1 AS siguiente FROM org_plantilla_autorizada WHERE empresa_id = ? AND unidad_id = ?");
                    $stmt_count->execute([$empresa_id, $unidad_id]);
                    $count_row = $stmt_count->fetch(PDO::FETCH_ASSOC);
                    $siguiente = (int)$count_row['siguiente'];
                    $codigo = sprintf('PLZ-%03d-%04d', $unidad_id, $siguiente);
                }

                $sql = "INSERT INTO org_plantilla_autorizada 
                        (empresa_id, codigo_plaza, unidad_id, adscripcion_id, puesto_id, 
                         fecha_creacion, justificacion_creacion, observaciones, estado, created_by)
                        VALUES (:empresa_id, :codigo, :unidad_id, :adscripcion_id, :puesto_id,
                                :fecha_creacion, :justificacion, :observaciones, 'activa', :created_by)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':empresa_id' => $empresa_id,
                    ':codigo' => $codigo,
                    ':unidad_id' => $unidad_id,
                    ':adscripcion_id' => $adscripcion_id,
                    ':puesto_id' => $puesto_id,
                    ':fecha_creacion' => $fecha_creacion,
                    ':justificacion' => $justificacion,
                    ':observaciones' => $observaciones,
                    ':created_by' => $usuario_id,
                ]);
                $plaza_id = $pdo->lastInsertId();
                $flash = 'Plaza creada correctamente: ' . $codigo;
                $flash_type = 'success';
                bitacora('plantilla_autorizada', 'crear_plaza', ['plaza_id' => $plaza_id, 'codigo' => $codigo]);

            } elseif ($action === 'editar_plaza') {
                // Requiere permiso específico
                if (!can('organizacion.plantilla.edit') && !can('organizacion.admin')) {
                    throw new RuntimeException('No tienes permiso para editar plazas.');
                }

                $plaza_id = isset($_POST['plaza_id']) ? (int)$_POST['plaza_id'] : 0;
                $unidad_id = isset($_POST['unidad_id']) ? (int)$_POST['unidad_id'] : 0;
                $adscripcion_id = isset($_POST['adscripcion_id']) && $_POST['adscripcion_id'] !== '' ? (int)$_POST['adscripcion_id'] : null;
                $puesto_id = isset($_POST['puesto_id']) && $_POST['puesto_id'] !== '' ? (int)$_POST['puesto_id'] : null;
                $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';

                if ($plaza_id <= 0) throw new RuntimeException('Plaza inválida.');
                if ($unidad_id <= 0) throw new RuntimeException('Debes seleccionar una Unidad.');

                $sql = "UPDATE org_plantilla_autorizada 
                        SET unidad_id = :unidad_id,
                            adscripcion_id = :adscripcion_id,
                            puesto_id = :puesto_id,
                            observaciones = :observaciones
                        WHERE plaza_id = :plaza_id AND empresa_id = :empresa_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':unidad_id' => $unidad_id,
                    ':adscripcion_id' => $adscripcion_id,
                    ':puesto_id' => $puesto_id,
                    ':observaciones' => $observaciones,
                    ':plaza_id' => $plaza_id,
                    ':empresa_id' => $empresa_id,
                ]);

                $flash = 'Plaza actualizada correctamente.';
                $flash_type = 'success';
                bitacora('plantilla_autorizada', 'editar_plaza', ['plaza_id' => $plaza_id]);

            } elseif ($action === 'congelar_plaza') {
                $plaza_id = isset($_POST['plaza_id']) ? (int)$_POST['plaza_id'] : 0;
                $justificacion = isset($_POST['justificacion_congelacion']) ? trim($_POST['justificacion_congelacion']) : '';
                $fecha = isset($_POST['fecha_congelacion']) && $_POST['fecha_congelacion'] !== '' ? $_POST['fecha_congelacion'] : date('Y-m-d');

                if ($plaza_id <= 0) throw new RuntimeException('Plaza inválida.');
                if (empty($justificacion)) throw new RuntimeException('Debes proporcionar una justificación.');

                $sql = "UPDATE org_plantilla_autorizada 
                        SET estado = 'congelada',
                            fecha_congelacion = :fecha,
                            justificacion_congelacion = :justificacion
                        WHERE plaza_id = :plaza_id AND empresa_id = :empresa_id AND estado = 'activa'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':fecha' => $fecha,
                    ':justificacion' => $justificacion,
                    ':plaza_id' => $plaza_id,
                    ':empresa_id' => $empresa_id,
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $flash = 'Plaza congelada correctamente.';
                    $flash_type = 'success';
                    bitacora('plantilla_autorizada', 'congelar_plaza', ['plaza_id' => $plaza_id]);
                } else {
                    $flash = 'No se pudo congelar la plaza (ya está congelada o cancelada).';
                    $flash_type = 'warning';
                }

            } elseif ($action === 'descongelar_plaza') {
                $plaza_id = isset($_POST['plaza_id']) ? (int)$_POST['plaza_id'] : 0;

                if ($plaza_id <= 0) throw new RuntimeException('Plaza inválida.');

                $sql = "UPDATE org_plantilla_autorizada 
                        SET estado = 'activa',
                            fecha_congelacion = NULL,
                            justificacion_congelacion = NULL
                        WHERE plaza_id = :plaza_id AND empresa_id = :empresa_id AND estado = 'congelada'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':plaza_id' => $plaza_id, ':empresa_id' => $empresa_id]);
                
                if ($stmt->rowCount() > 0) {
                    $flash = 'Plaza descongelada correctamente.';
                    $flash_type = 'success';
                    bitacora('plantilla_autorizada', 'descongelar_plaza', ['plaza_id' => $plaza_id]);
                } else {
                    $flash = 'No se pudo descongelar la plaza.';
                    $flash_type = 'warning';
                }

            } elseif ($action === 'cancelar_plaza') {
                $plaza_id = isset($_POST['plaza_id']) ? (int)$_POST['plaza_id'] : 0;
                $justificacion = isset($_POST['justificacion_cancelacion']) ? trim($_POST['justificacion_cancelacion']) : '';
                $fecha = isset($_POST['fecha_cancelacion']) && $_POST['fecha_cancelacion'] !== '' ? $_POST['fecha_cancelacion'] : date('Y-m-d');

                if ($plaza_id <= 0) throw new RuntimeException('Plaza inválida.');
                if (empty($justificacion)) throw new RuntimeException('Debes proporcionar una justificación.');

                // Verificar que la plaza no esté ocupada
                $check = $pdo->prepare("SELECT empleado_id FROM org_plantilla_autorizada WHERE plaza_id = ? AND empresa_id = ?");
                $check->execute([$plaza_id, $empresa_id]);
                $plaza_check = $check->fetch(PDO::FETCH_ASSOC);
                
                if ($plaza_check && $plaza_check['empleado_id']) {
                    throw new RuntimeException('No se puede cancelar una plaza ocupada. Primero desasigna al empleado.');
                }

                $sql = "UPDATE org_plantilla_autorizada 
                        SET estado = 'cancelada',
                            fecha_cancelacion = :fecha,
                            justificacion_cancelacion = :justificacion
                        WHERE plaza_id = :plaza_id AND empresa_id = :empresa_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':fecha' => $fecha,
                    ':justificacion' => $justificacion,
                    ':plaza_id' => $plaza_id,
                    ':empresa_id' => $empresa_id,
                ]);
                
                $flash = 'Plaza cancelada correctamente.';
                $flash_type = 'success';
                bitacora('plantilla_autorizada', 'cancelar_plaza', ['plaza_id' => $plaza_id]);

            } elseif ($action === 'editar_observaciones') {
                $plaza_id = isset($_POST['plaza_id']) ? (int)$_POST['plaza_id'] : 0;
                $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';

                if ($plaza_id <= 0) throw new RuntimeException('Plaza inválida.');

                $sql = "UPDATE org_plantilla_autorizada 
                        SET observaciones = :observaciones
                        WHERE plaza_id = :plaza_id AND empresa_id = :empresa_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':observaciones' => $observaciones, ':plaza_id' => $plaza_id, ':empresa_id' => $empresa_id]);
                
                $flash = 'Observaciones actualizadas.';
                $flash_type = 'success';

            } elseif ($action === 'asignar_empleado') {
                $plaza_id = isset($_POST['plaza_id']) ? (int)$_POST['plaza_id'] : 0;
                $empleado_id = isset($_POST['empleado_id']) ? (int)$_POST['empleado_id'] : 0;
                $fecha_asignacion = isset($_POST['fecha_asignacion']) && $_POST['fecha_asignacion'] !== '' ? $_POST['fecha_asignacion'] : date('Y-m-d');

                if ($plaza_id <= 0) throw new RuntimeException('Plaza inválida.');
                if ($empleado_id <= 0) throw new RuntimeException('Debes seleccionar un empleado.');

                // Verificar que la plaza esté activa y vacante
                $check = $pdo->prepare("SELECT estado, empleado_id FROM org_plantilla_autorizada WHERE plaza_id = ? AND empresa_id = ?");
                $check->execute([$plaza_id, $empresa_id]);
                $plaza_check = $check->fetch(PDO::FETCH_ASSOC);
                
                if (!$plaza_check) throw new RuntimeException('Plaza no encontrada.');
                if ($plaza_check['estado'] !== 'activa') throw new RuntimeException('Solo se pueden asignar empleados a plazas activas.');
                if ($plaza_check['empleado_id']) throw new RuntimeException('Esta plaza ya está ocupada.');

                // Verificar que el empleado exista y esté activo
                $emp_check = $pdo->prepare("SELECT estatus FROM empleados WHERE empleado_id = ? AND empresa_id = ?");
                $emp_check->execute([$empleado_id, $empresa_id]);
                $emp_data = $emp_check->fetch(PDO::FETCH_ASSOC);
                
                if (!$emp_data) throw new RuntimeException('Empleado no encontrado.');
                if ($emp_data['estatus'] !== 'ACTIVO') throw new RuntimeException('Solo se pueden asignar empleados activos.');

                $sql = "UPDATE org_plantilla_autorizada 
                        SET empleado_id = :empleado_id,
                            fecha_asignacion = :fecha_asignacion
                        WHERE plaza_id = :plaza_id AND empresa_id = :empresa_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':empleado_id' => $empleado_id,
                    ':fecha_asignacion' => $fecha_asignacion,
                    ':plaza_id' => $plaza_id,
                    ':empresa_id' => $empresa_id,
                ]);
                
                $flash = 'Empleado asignado a la plaza correctamente.';
                $flash_type = 'success';
                bitacora('plantilla_autorizada', 'asignar_empleado', ['plaza_id' => $plaza_id, 'empleado_id' => $empleado_id]);

            } elseif ($action === 'desasignar_empleado') {
                $plaza_id = isset($_POST['plaza_id']) ? (int)$_POST['plaza_id'] : 0;

                if ($plaza_id <= 0) throw new RuntimeException('Plaza inválida.');

                $sql = "UPDATE org_plantilla_autorizada 
                        SET empleado_id = NULL,
                            fecha_asignacion = NULL
                        WHERE plaza_id = :plaza_id AND empresa_id = :empresa_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':plaza_id' => $plaza_id, ':empresa_id' => $empresa_id]);
                
                $flash = 'Empleado desasignado de la plaza.';
                $flash_type = 'success';
                bitacora('plantilla_autorizada', 'desasignar_empleado', ['plaza_id' => $plaza_id]);

            } else {
                $flash = 'Acción no reconocida.';
                $flash_type = 'warning';
            }
        } catch (Exception $e) {
            $flash = 'Error: ' . $e->getMessage();
            $flash_type = 'danger';
        }
    }
}

// =======================
// CARGAR DATOS
// =======================

// Filtros
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'todas';
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
            emp.nombre AS empleado_nombre,
            emp.apellido_paterno AS empleado_apellido_paterno,
            emp.no_emp AS empleado_no_emp,
            CASE 
                WHEN p.estado = 'cancelada' THEN 'Cancelada'
                WHEN p.estado = 'congelada' THEN 'Congelada'
                WHEN p.empleado_id IS NOT NULL THEN 'Ocupada'
                ELSE 'Vacante'
            END AS estado_ocupacion
        FROM org_plantilla_autorizada p
        INNER JOIN org_unidades u ON u.unidad_id = p.unidad_id
        LEFT JOIN org_adscripciones a ON a.adscripcion_id = p.adscripcion_id
        LEFT JOIN org_puestos pu ON pu.puesto_id = p.puesto_id
        LEFT JOIN empleados emp ON emp.empleado_id = p.empleado_id
        WHERE $where_sql
        ORDER BY p.estado, u.nombre, p.fecha_creacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$plazas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combos para formularios
$unidades_stmt = $pdo->prepare("SELECT unidad_id, nombre FROM org_unidades WHERE empresa_id = :empresa_id AND estatus = 1 ORDER BY nombre");
$unidades_stmt->execute([':empresa_id' => $empresa_id]);
$unidades = $unidades_stmt->fetchAll(PDO::FETCH_ASSOC);

$adscripciones_stmt = $pdo->prepare("SELECT a.adscripcion_id, a.nombre, a.unidad_id, u.nombre AS unidad_nombre 
                                      FROM org_adscripciones a 
                                      INNER JOIN org_unidades u ON u.unidad_id = a.unidad_id
                                      WHERE a.empresa_id = :empresa_id AND a.estatus = 1 
                                      ORDER BY u.nombre, a.nombre");
$adscripciones_stmt->execute([':empresa_id' => $empresa_id]);
$adscripciones = $adscripciones_stmt->fetchAll(PDO::FETCH_ASSOC);

$puestos_stmt = $pdo->prepare("SELECT MIN(puesto_id) as puesto_id, nombre FROM org_puestos WHERE empresa_id = :empresa_id AND estatus = 1 GROUP BY empresa_id, nombre ORDER BY nombre");
$puestos_stmt->execute([':empresa_id' => $empresa_id]);
$puestos = $puestos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stats_sql = "SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN estado = 'activa' THEN 1 ELSE 0 END) AS activas,
                SUM(CASE WHEN estado = 'congelada' THEN 1 ELSE 0 END) AS congeladas,
                SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) AS canceladas,
                SUM(CASE WHEN estado = 'activa' AND empleado_id IS NOT NULL THEN 1 ELSE 0 END) AS ocupadas,
                SUM(CASE WHEN estado = 'activa' AND empleado_id IS NULL THEN 1 ELSE 0 END) AS vacantes
              FROM org_plantilla_autorizada
              WHERE empresa_id = :empresa_id";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute([':empresa_id' => $empresa_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// =======================
// LAYOUT
// =======================
$page_title = 'Gestión de Plazas Autorizadas';
$active_menu = 'admin_org_plantilla';
$extra_css = [
    'global_assets/css/icons/icomoon/styles.min.css',
    'global_assets/css/plugins/tables/datatables/datatables.min.css',
    'global_assets/css/plugins/forms/selects/select2.min.css',
];
$extra_js = [
    'global_assets/js/plugins/tables/datatables/datatables.min.js',
    'global_assets/js/plugins/forms/selects/select2.min.js',
];

require_once __DIR__ . '/../includes/layout/head.php';
require_once __DIR__ . '/../includes/layout/navbar.php';
require_once __DIR__ . '/../includes/layout/sidebar.php';
require_once __DIR__ . '/../includes/layout/content_open.php';
?>

<!-- Page header -->
<div class="page-header page-header-light">
    <div class="page-header-content header-elements-md-inline">
        <div class="page-title d-flex">
            <h4><i class="icon-office mr-2"></i> <span class="font-weight-semibold"><?php echo h($page_title); ?></span></h4>
        </div>
    </div>
    <div class="breadcrumb-line breadcrumb-line-light header-elements-lg-inline">
        <div class="d-flex">
            <div class="breadcrumb">
                <a href="<?php echo ASSET_BASE; ?>public/dashboard.php" class="breadcrumb-item">
                    <i class="icon-home2 mr-2"></i> Inicio
                </a>
                <span class="breadcrumb-item">Administración</span>
                <span class="breadcrumb-item active">Plazas Autorizadas</span>
            </div>
        </div>
    </div>
</div>

<div class="content">

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash_type); ?> alert-styled-left alert-dismissible">
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            <?php echo h($flash); ?>
        </div>
    <?php endif; ?>

    <!-- Estadísticas -->
    <div class="row">
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3 class="font-weight-semibold mb-0"><?php echo (int)$stats['total']; ?></h3>
                    <div class="font-size-sm">Total Plazas</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3 class="font-weight-semibold mb-0"><?php echo (int)$stats['activas']; ?></h3>
                    <div class="font-size-sm">Activas</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3 class="font-weight-semibold mb-0"><?php echo (int)$stats['ocupadas']; ?></h3>
                    <div class="font-size-sm">Ocupadas</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h3 class="font-weight-semibold mb-0"><?php echo (int)$stats['vacantes']; ?></h3>
                    <div class="font-size-sm">Vacantes</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h3 class="font-weight-semibold mb-0"><?php echo (int)$stats['congeladas']; ?></h3>
                    <div class="font-size-sm">Congeladas</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h3 class="font-weight-semibold mb-0"><?php echo (int)$stats['canceladas']; ?></h3>
                    <div class="font-size-sm">Canceladas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros y tabla -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Registro Individual de Plazas</h5>
            <div class="header-elements">
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalCrearPlaza">
                    <i class="icon-plus-circle2 mr-2"></i> Crear Nueva Plaza
                </button>
            </div>
        </div>

        <div class="card-body">
            <form method="get" class="form-inline mb-3">
                <label class="mr-2">Filtrar por estado:</label>
                <select name="estado" class="form-control form-control-sm mr-2">
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
                        <th>F. Creación</th>
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
                                <div class="list-icons">
                                    <div class="dropdown">
                                        <a href="#" class="list-icons-item" data-toggle="dropdown">
                                            <i class="icon-menu9"></i>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a href="#" class="dropdown-item" data-toggle="modal" data-target="#modalVerDetalle"
                                               onclick='verDetalle(<?php echo json_encode($p, JSON_UNESCAPED_UNICODE); ?>)'>
                                                <i class="icon-eye"></i> Ver Detalle
                                            </a>
                                            
                                            <?php if (can('organizacion.plantilla.edit') || can('organizacion.admin')): ?>
                                                <a href="#" class="dropdown-item" data-toggle="modal" data-target="#modalEditarPlaza"
                                                   onclick='prepararEditar(<?php echo json_encode($p, JSON_UNESCAPED_UNICODE); ?>)'>
                                                    <i class="icon-pencil"></i> Editar Plaza
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($p['estado'] === 'activa' && !$p['empleado_id']): ?>
                                                <a href="#" class="dropdown-item" data-toggle="modal" data-target="#modalAsignarEmpleado"
                                                   onclick='prepararAsignar(<?php echo (int)$p['plaza_id']; ?>, "<?php echo h($p['codigo_plaza']); ?>")'>
                                                    <i class="icon-user-plus"></i> Asignar Empleado
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($p['estado'] === 'activa' && $p['empleado_id']): ?>
                                                <a href="#" class="dropdown-item" data-toggle="modal" data-target="#modalDesasignarEmpleado"
                                                   onclick='prepararDesasignar(<?php echo (int)$p['plaza_id']; ?>, "<?php echo h($p['codigo_plaza']); ?>", "<?php echo h($p['empleado_nombre'] . ' ' . $p['empleado_apellido_paterno']); ?>")'>
                                                    <i class="icon-user-minus"></i> Desasignar Empleado
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($p['estado'] === 'activa'): ?>
                                                <div class="dropdown-divider"></div>
                                                <a href="#" class="dropdown-item" data-toggle="modal" data-target="#modalCongelar"
                                                   onclick='prepararCongelar(<?php echo (int)$p['plaza_id']; ?>, "<?php echo h($p['codigo_plaza']); ?>")'>
                                                    <i class="icon-pause2"></i> Congelar Plaza
                                                </a>
                                                <?php if (!$p['empleado_id']): ?>
                                                <a href="#" class="dropdown-item" data-toggle="modal" data-target="#modalCancelar"
                                                   onclick='prepararCancelar(<?php echo (int)$p['plaza_id']; ?>, "<?php echo h($p['codigo_plaza']); ?>")'>
                                                    <i class="icon-cross2"></i> Cancelar Plaza
                                                </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php if ($p['estado'] === 'congelada'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                    <input type="hidden" name="action" value="descongelar_plaza">
                                                    <input type="hidden" name="plaza_id" value="<?php echo (int)$p['plaza_id']; ?>">
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="icon-play3"></i> Descongelar Plaza
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <div class="dropdown-divider"></div>
                                            <a href="#" class="dropdown-item" data-toggle="modal" data-target="#modalEditarObs"
                                               onclick='editarObservaciones(<?php echo (int)$p['plaza_id']; ?>, "<?php echo h($p['codigo_plaza']); ?>", "<?php echo h($p['observaciones'] ?? ''); ?>")'>
                                                <i class="icon-pencil"></i> Editar Observaciones
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal: Crear Plaza -->
<div class="modal fade" id="modalCrearPlaza" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Crear Nueva Plaza</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="crear_plaza">

                <div class="alert alert-info">
                    <i class="icon-info22 mr-2"></i>
                    Cada plaza representa una posición autorizada individual que puede ser ocupada por un empleado.
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Código de Plaza</label>
                            <input type="text" name="codigo_plaza" class="form-control" placeholder="Ej: DIR-TI-001 (opcional, se genera automático)">
                            <small class="form-text text-muted">Si se deja vacío, se generará automáticamente</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Fecha de Creación <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_creacion" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Unidad (Dirección) <span class="text-danger">*</span></label>
                            <select name="unidad_id" id="modal_unidad_id" class="form-control" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($unidades as $u): ?>
                                    <option value="<?php echo (int)$u['unidad_id']; ?>"><?php echo h($u['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Departamento (Adscripción)</label>
                            <select name="adscripcion_id" id="modal_adscripcion_id" class="form-control">
                                <option value="">Sin departamento específico</option>
                                <?php foreach ($adscripciones as $a): ?>
                                    <option value="<?php echo (int)$a['adscripcion_id']; ?>" data-unidad-id="<?php echo (int)$a['unidad_id']; ?>">
                                        <?php echo h($a['unidad_nombre'] . ' - ' . $a['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Puesto</label>
                    <select name="puesto_id" id="modal_puesto_id" class="form-control">
                        <option value="">Sin puesto específico</option>
                        <?php foreach ($puestos as $pu): ?>
                            <option value="<?php echo (int)$pu['puesto_id']; ?>"><?php echo h($pu['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Selecciona un puesto para esta plaza</small>
                </div>

                <div class="form-group">
                    <label>Justificación de Creación <span class="text-danger">*</span></label>
                    <textarea name="justificacion_creacion" class="form-control" rows="3" required
                              placeholder="Ej: Plaza autorizada según presupuesto 2026, oficio DRH-123/2026..."></textarea>
                    <small class="form-text text-muted">Explica el fundamento para crear esta plaza</small>
                </div>

                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2" 
                              placeholder="Notas adicionales..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="icon-check mr-2"></i> Crear Plaza
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Plaza -->
<div class="modal fade" id="modalEditarPlaza" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="icon-pencil mr-2"></i>Editar Plaza</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="editar_plaza">
                <input type="hidden" name="plaza_id" id="editar_plaza_id">

                <div class="alert alert-info">
                    <i class="icon-info22 mr-2"></i>
                    Editando plaza: <strong id="editar_codigo_plaza"></strong>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Unidad (Dirección) <span class="text-danger">*</span></label>
                            <select name="unidad_id" id="editar_unidad_id" class="form-control" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($unidades as $u): ?>
                                    <option value="<?php echo (int)$u['unidad_id']; ?>"><?php echo h($u['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Departamento (Adscripción)</label>
                            <select name="adscripcion_id" id="editar_adscripcion_id" class="form-control">
                                <option value="">Sin departamento específico</option>
                                <?php foreach ($adscripciones as $a): ?>
                                    <option value="<?php echo (int)$a['adscripcion_id']; ?>" data-unidad-id="<?php echo (int)$a['unidad_id']; ?>">
                                        <?php echo h($a['unidad_nombre'] . ' - ' . $a['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Puesto</label>
                    <select name="puesto_id" id="editar_puesto_id" class="form-control">
                        <option value="">Sin puesto específico</option>
                        <?php foreach ($puestos as $pu): ?>
                            <option value="<?php echo (int)$pu['puesto_id']; ?>"><?php echo h($pu['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea name="observaciones" id="editar_observaciones" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning">
                    <i class="icon-checkmark3 mr-2"></i>Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Congelar Plaza -->
<div class="modal fade" id="modalCongelar" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">Congelar Plaza</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="congelar_plaza">
                <input type="hidden" name="plaza_id" id="congelar_plaza_id">

                <p>¿Deseas congelar temporalmente la plaza <strong id="congelar_codigo"></strong>?</p>
                <p class="text-muted">Una plaza congelada no puede ser asignada hasta que sea descongelada.</p>

                <div class="form-group">
                    <label>Fecha de Congelación <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_congelacion" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Justificación <span class="text-danger">*</span></label>
                    <textarea name="justificacion_congelacion" class="form-control" rows="3" required
                              placeholder="Ej: Restricción presupuestal temporal, reorganización..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-secondary">
                    <i class="icon-pause2 mr-2"></i> Congelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Cancelar Plaza -->
<div class="modal fade" id="modalCancelar" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Cancelar Plaza</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="cancelar_plaza">
                <input type="hidden" name="plaza_id" id="cancelar_plaza_id">

                <div class="alert alert-warning">
                    <i class="icon-warning2 mr-2"></i>
                    <strong>Atención:</strong> La cancelación de una plaza es una acción permanente. La plaza quedará registrada pero no podrá ser asignada.
                </div>

                <p>¿Deseas cancelar definitivamente la plaza <strong id="cancelar_codigo"></strong>?</p>

                <div class="form-group">
                    <label>Fecha de Cancelación <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_cancelacion" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Justificación <span class="text-danger">*</span></label>
                    <textarea name="justificacion_cancelacion" class="form-control" rows="3" required
                              placeholder="Ej: Reducción de plantilla, reestructuración organizacional..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger">
                    <i class="icon-cross2 mr-2"></i> Cancelar Plaza
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Ver Detalle -->
<div class="modal fade" id="modalVerDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de Plaza</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="detalle_contenido">
                <!-- Se llena dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Editar Observaciones -->
<div class="modal fade" id="modalEditarObs" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Observaciones</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="editar_observaciones">
                <input type="hidden" name="plaza_id" id="obs_plaza_id">

                <p>Plaza: <strong id="obs_codigo"></strong></p>

                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea name="observaciones" id="obs_texto" class="form-control" rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Asignar Empleado -->
<div class="modal fade" id="modalAsignarEmpleado" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="icon-user-plus mr-2"></i>Asignar Empleado a Plaza</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="asignar_empleado">
                <input type="hidden" name="plaza_id" id="asignar_plaza_id">

                <div class="alert alert-info">
                    <i class="icon-info22 mr-2"></i>
                    Plaza: <strong id="asignar_codigo"></strong><br>
                    Solo se pueden asignar empleados activos a plazas activas y vacantes.
                </div>

                <div class="form-group">
                    <label>Empleado <span class="text-danger">*</span></label>
                    <select name="empleado_id" id="modal_empleado_id" class="form-control" required>
                        <option value=""></option>
                        <?php
                        $emp_sql = "SELECT empleado_id, no_emp, nombre, apellido_paterno, apellido_materno 
                                    FROM empleados 
                                    WHERE empresa_id = :empresa_id AND estatus = 'ACTIVO'
                                    ORDER BY nombre, apellido_paterno";
                        $emp_stmt = $pdo->prepare($emp_sql);
                        $emp_stmt->execute([':empresa_id' => $empresa_id]);
                        while ($emp = $emp_stmt->fetch(PDO::FETCH_ASSOC)):
                        ?>
                            <option value="<?php echo (int)$emp['empleado_id']; ?>">
                                <?php echo h($emp['no_emp']); ?> - <?php echo h($emp['nombre'] . ' ' . $emp['apellido_paterno'] . ' ' . ($emp['apellido_materno'] ?? '')); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small class="form-text text-muted">Selecciona un empleado activo de la lista</small>
                </div>

                <div class="form-group">
                    <label>Fecha de Asignación</label>
                    <input type="date" name="fecha_asignacion" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    <small class="form-text text-muted">Si se deja vacío, se usará la fecha actual</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="icon-checkmark3 mr-2"></i>Asignar Empleado
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Desasignar Empleado -->
<div class="modal fade" id="modalDesasignarEmpleado" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="icon-user-minus mr-2"></i>Desasignar Empleado</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="desasignar_empleado">
                <input type="hidden" name="plaza_id" id="desasignar_plaza_id">

                <div class="alert alert-warning">
                    <i class="icon-warning2 mr-2"></i>
                    <strong>¿Estás seguro de desasignar al empleado de esta plaza?</strong>
                </div>

                <p>Plaza: <strong id="desasignar_codigo"></strong></p>
                <p>Empleado actual: <strong id="desasignar_empleado"></strong></p>

                <p class="text-muted">
                    <small>Esta acción liberará la plaza y quedará vacante. El empleado podrá ser reasignado a otra plaza.</small>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning">
                    <i class="icon-user-minus mr-2"></i>Desasignar
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/layout/footer.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
require_once __DIR__ . '/../includes/layout/scripts.php';
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

    // Inicializar Select2 en modales cuando se abren
    $('#modalCrearPlaza').on('shown.bs.modal', function () {
        // Inicializar Select2 en el modal
        $('#modal_unidad_id').select2({
            dropdownParent: $('#modalCrearPlaza'),
            width: '100%'
        });
        
        $('#modal_adscripcion_id').select2({
            dropdownParent: $('#modalCrearPlaza'),
            width: '100%'
        });
        
        $('#modal_puesto_id').select2({
            dropdownParent: $('#modalCrearPlaza'),
            width: '100%'
        });
    });

    $('#modalAsignarEmpleado').on('shown.bs.modal', function () {
        $('#modal_empleado_id').select2({
            dropdownParent: $('#modalAsignarEmpleado'),
            width: '100%',
            placeholder: 'Buscar empleado...'
        });
    });

    // Filtrado de departamentos dentro del modal al cambiar unidad
    $(document).on('change', '#modal_unidad_id', function() {
        var unidad_id = $(this).val();
        var $deptSelect = $('#modal_adscripcion_id');
        
        // Mostrar todas las opciones primero
        $deptSelect.find('option').each(function() {
            $(this).prop('disabled', false);
        });
        
        if (unidad_id && unidad_id !== '') {
            // Deshabilitar opciones que no coincidan con la unidad seleccionada
            $deptSelect.find('option[data-unidad-id]').each(function() {
                var optUnidadId = $(this).data('unidad-id');
                if (optUnidadId != unidad_id) {
                    $(this).prop('disabled', true);
                }
            });
        }
        
        // Resetear selección y actualizar Select2
        $deptSelect.val('').trigger('change.select2');
    });

    // Filtrado de departamentos en modal de edición al cambiar unidad
    $(document).on('change', '#editar_unidad_id', function() {
        var unidad_id = $(this).val();
        var $deptSelect = $('#editar_adscripcion_id');
        
        $deptSelect.find('option').each(function() {
            $(this).prop('disabled', false);
        });
        
        if (unidad_id && unidad_id !== '') {
            $deptSelect.find('option[data-unidad-id]').each(function() {
                var optUnidadId = $(this).data('unidad-id');
                if (optUnidadId != unidad_id) {
                    $(this).prop('disabled', true);
                }
            });
        }
        
        $deptSelect.val('').trigger('change.select2');
    });

    // Filtrado de departamentos al cambiar unidad en tabla de filtros
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
                },
                error: function(xhr, status, error) {
                    console.error('Error en AJAX:', error);
                }
            });
        }
    });
});

function prepararCongelar(id, codigo) {
    $('#congelar_plaza_id').val(id);
    $('#congelar_codigo').text(codigo);
}

function prepararCancelar(id, codigo) {
    $('#cancelar_plaza_id').val(id);
    $('#cancelar_codigo').text(codigo);
}

function editarObservaciones(id, codigo, obs) {
    $('#obs_plaza_id').val(id);
    $('#obs_codigo').text(codigo);
    $('#obs_texto').val(obs);
}

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

function prepararAsignar(id, codigo) {
    $('#asignar_plaza_id').val(id);
    $('#asignar_codigo').text(codigo);
}

function prepararEditar(plaza) {
    $('#editar_plaza_id').val(plaza.plaza_id);
    $('#editar_codigo_plaza').text(plaza.codigo_plaza);
    $('#editar_unidad_id').val(plaza.unidad_id);
    $('#editar_adscripcion_id').val(plaza.adscripcion_id || '');
    $('#editar_puesto_id').val(plaza.puesto_id || '');
    $('#editar_observaciones').val(plaza.observaciones || '');
    
    // Inicializar Select2 en el modal de edición
    $('#editar_unidad_id, #editar_adscripcion_id, #editar_puesto_id').select2({
        dropdownParent: $('#modalEditarPlaza'),
        width: '100%'
    });
    
    // Filtrar departamentos según unidad seleccionada
    var unidadId = plaza.unidad_id;
    $('#editar_adscripcion_id option[data-unidad-id]').each(function() {
        var optUnidadId = $(this).data('unidad-id');
        $(this).prop('disabled', optUnidadId != unidadId);
    });
}

function prepararDesasignar(id, codigo, empleado) {
    $('#desasignar_plaza_id').val(id);
    $('#desasignar_codigo').text(codigo);
    $('#desasignar_empleado').text(empleado);
}
</script>
