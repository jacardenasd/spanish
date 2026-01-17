<?php
// public/clima_generar_elegibles.php

// DEBUG (solo en MAMP/dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/conexion.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Helpers opcionales (si en tu proyecto existen, se usan; si no, se omiten sin fatal)
if (function_exists('require_login')) {
    require_login();
} else {
    if (empty($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

if (function_exists('require_empresa')) {
    require_empresa();
}

if (function_exists('require_password_change_redirect')) {
    require_password_change_redirect();
}

// Ajusta permiso según tu sistema
if (function_exists('require_perm_any')) {
  require_perm_any(['organizacion.admin', 'clima.admin']);
} else if (function_exists('require_perm')) {
  if (!can('organizacion.admin') && !can('clima.admin')) {
    header('Location: sin_permiso.php');
    exit;
  }
}

$empresa_id = (int)($_SESSION['empresa_id'] ?? 0);
if ($empresa_id <= 0) {
    // No matamos la página; mostramos algo útil con layout
    $empresa_id = 0;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Helper: verifica si existe una tabla sin depender de information_schema
if (!function_exists('table_exists')) {
  function table_exists(PDO $pdo, string $table): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
    try {
      // Consulta ligera; si la tabla no existe, MySQL lanza 1146
      $pdo->query("SELECT 1 FROM `".$table."` LIMIT 1");
      return true;
    } catch (Throwable $e) {
      return false;
    }
  }
}

$flash = null;
$flash_type = 'info';

$periodo_id = (int)($_GET['periodo_id'] ?? 0);

// Periodos por empresa
$periodos = [];
if ($empresa_id > 0) {
    $periodos_stmt = $pdo->prepare("SELECT * FROM clima_periodos WHERE empresa_id=? ORDER BY anio DESC");
    $periodos_stmt->execute([$empresa_id]);
    $periodos = $periodos_stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($periodo_id <= 0 && !empty($periodos)) {
    $periodo_id = (int)$periodos[0]['periodo_id'];
}

// Carga periodo actual
$periodo = null;
if ($periodo_id > 0 && $empresa_id > 0) {
    $pstmt = $pdo->prepare("SELECT * FROM clima_periodos WHERE periodo_id=? AND empresa_id=? LIMIT 1");
    $pstmt->execute([$periodo_id, $empresa_id]);
    $periodo = $pstmt->fetch(PDO::FETCH_ASSOC);
}

function clima_count_elegibles(PDO $pdo, int $periodo_id): array {
    $out = ['total' => 0, 'elegibles' => 0];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clima_elegibles WHERE periodo_id=?");
    $stmt->execute([$periodo_id]);
    $out['total'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clima_elegibles WHERE periodo_id=? AND elegible=1");
    $stmt->execute([$periodo_id]);
    $out['elegibles'] = (int)$stmt->fetchColumn();

    return $out;
}

function clima_has_responses(PDO $pdo, int $periodo_id): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM clima_respuestas WHERE periodo_id=? LIMIT 1");
        $stmt->execute([$periodo_id]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

// POST generar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $post_token)) {
        $flash = 'Solicitud inválida (token).';
        $flash_type = 'danger';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $periodo_id_post = (int)($_POST['periodo_id'] ?? 0);
        $force = (int)($_POST['force'] ?? 0);

        if ($action === 'generar') {

            if ($empresa_id <= 0) {
                $flash = 'No hay empresa activa en sesión.';
                $flash_type = 'danger';
            } else {
                $pstmt = $pdo->prepare("SELECT * FROM clima_periodos WHERE periodo_id=? AND empresa_id=? LIMIT 1");
                $pstmt->execute([$periodo_id_post, $empresa_id]);
                $periodo_post = $pstmt->fetch(PDO::FETCH_ASSOC);

                if (!$periodo_post) {
                    $flash = 'Periodo inválido o no pertenece a la empresa seleccionada.';
                    $flash_type = 'danger';
                } else {
                    try {
                        if (clima_has_responses($pdo, $periodo_id_post)) {
                            throw new RuntimeException('No se puede regenerar elegibles: el periodo ya tiene respuestas registradas.');
                        }

                        $counts_before = clima_count_elegibles($pdo, $periodo_id_post);
                        if ($counts_before['total'] > 0 && $force !== 1) {
                            throw new RuntimeException('El universo ya fue generado. Usa "Forzar regeneración" solo antes de iniciar captura.');
                        }

                        $pdo->beginTransaction();

                        if ($force === 1) {
                            $del = $pdo->prepare("DELETE FROM clima_elegibles WHERE periodo_id=?");
                            $del->execute([$periodo_id_post]);
                        }

                        $fecha_corte = (string)$periodo_post['fecha_corte_elegibilidad'];

                        // es_activo ENUM('activo','inactivo','baja'). Permitir excepciones si existe tabla clima_excepciones
                        $hasEx = table_exists($pdo, 'clima_excepciones');
                        if ($hasEx) {
                            $sql = "
                                INSERT INTO clima_elegibles
                                  (periodo_id, empleado_id, empresa_id, unidad_id, elegible, motivo_no_elegible, created_at)
                                SELECT
                                  ?,
                                  e.empleado_id,
                                  e.empresa_id,
                                  COALESCE(e.unidad_id, p.unidad_id, ex.unidad_id_override) AS unidad_id,
                                  CASE
                                    WHEN (
                                        e.es_activo = 1
                                        AND e.fecha_ingreso IS NOT NULL
                                        AND e.fecha_ingreso <= DATE_SUB(?, INTERVAL 1 MONTH)
                                    ) OR (ex.reset = 1)
                                    THEN 1 ELSE 0
                                  END,
                                  CASE
                                    WHEN ex.reset = 1 THEN 'EXCEPCION'
                                    WHEN e.es_activo <> 1 THEN 'INACTIVO/BAJA'
                                    WHEN e.fecha_ingreso IS NULL THEN 'SIN_FECHA_INGRESO'
                                    WHEN e.fecha_ingreso > DATE_SUB(?, INTERVAL 1 MONTH) THEN 'ANTIGUEDAD_MENOR_1_MES'
                                    ELSE NULL
                                  END,
                                  NOW()
                                FROM empleados e
                                LEFT JOIN org_puestos p ON p.puesto_id = e.puesto_id
                                LEFT JOIN clima_excepciones ex
                                  ON ex.periodo_id = ?
                                  AND ex.empresa_id = e.empresa_id
                                  AND ex.empleado_id = e.empleado_id
                                WHERE e.empresa_id = ?
                                  AND COALESCE(e.unidad_id, p.unidad_id, ex.unidad_id_override) IS NOT NULL
                                ON DUPLICATE KEY UPDATE
                                  empresa_id = VALUES(empresa_id),
                                  unidad_id  = VALUES(unidad_id),
                                  elegible   = VALUES(elegible),
                                  motivo_no_elegible = VALUES(motivo_no_elegible)
                            ";
                        } else {
                            $sql = "
                                INSERT INTO clima_elegibles
                                  (periodo_id, empleado_id, empresa_id, unidad_id, elegible, motivo_no_elegible, created_at)
                                SELECT
                                  ?,
                                  e.empleado_id,
                                  e.empresa_id,
                                  COALESCE(e.unidad_id, p.unidad_id) AS unidad_id,
                                  CASE
                                    WHEN e.es_activo = 1
                                     AND e.fecha_ingreso IS NOT NULL
                                     AND e.fecha_ingreso <= DATE_SUB(?, INTERVAL 1 MONTH)
                                    THEN 1 ELSE 0
                                  END,
                                  CASE
                                    WHEN e.es_activo <> 1 THEN 'INACTIVO/BAJA'
                                    WHEN e.fecha_ingreso IS NULL THEN 'SIN_FECHA_INGRESO'
                                    WHEN e.fecha_ingreso > DATE_SUB(?, INTERVAL 1 MONTH) THEN 'ANTIGUEDAD_MENOR_1_MES'
                                    ELSE NULL
                                  END,
                                  NOW()
                                FROM empleados e
                                LEFT JOIN org_puestos p ON p.puesto_id = e.puesto_id
                                WHERE e.empresa_id = ?
                                  AND COALESCE(e.unidad_id, p.unidad_id) IS NOT NULL
                                ON DUPLICATE KEY UPDATE
                                  empresa_id = VALUES(empresa_id),
                                  unidad_id  = VALUES(unidad_id),
                                  elegible   = VALUES(elegible),
                                  motivo_no_elegible = VALUES(motivo_no_elegible)
                            ";
                        }

                        $stmt = $pdo->prepare($sql);
                        if ($hasEx) {
                          $stmt->execute([
                            $periodo_id_post,
                            $fecha_corte,
                            $fecha_corte,
                            $periodo_id_post,
                            $empresa_id
                          ]);
                        } else {
                          $stmt->execute([
                            $periodo_id_post,
                            $fecha_corte,
                            $fecha_corte,
                            $empresa_id
                          ]);
                        }

                        $pdo->commit();

                        $counts_after = clima_count_elegibles($pdo, $periodo_id_post);
                        $flash = "Universo generado. Total: {$counts_after['total']}. Elegibles: {$counts_after['elegibles']}.";
                        $flash_type = 'success';

                        header("Location: clima_generar_elegibles.php?periodo_id=" . (int)$periodo_id_post);
                        exit;

                    } catch (Exception $ex) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $flash = $ex->getMessage();
                        $flash_type = 'danger';
                    }
                }
            }
        }
        elseif ($action === 'add_excepcion') {
          // Agregar excepción manual (por No. empleado)
          if (!function_exists('table_exists') || !table_exists($pdo, 'clima_excepciones')) {
            $flash = 'No existe la tabla clima_excepciones. Crea la tabla antes de usar excepciones.';
            $flash_type = 'danger';
          } else {
            $periodo_id_post = (int)($_POST['periodo_id'] ?? 0);
            $no_emp = trim((string)($_POST['no_emp'] ?? ''));
            $unidad_override = (int)($_POST['unidad_override'] ?? 0);

            if ($empresa_id <= 0 || $periodo_id_post <= 0 || $no_emp === '') {
              $flash = 'Datos incompletos para agregar excepción.';
              $flash_type = 'danger';
            } else {
              $stE = $pdo->prepare("SELECT empleado_id, unidad_id, nombre, apellido_paterno, apellido_materno FROM empleados WHERE empresa_id=? AND no_emp=? LIMIT 1");
              $stE->execute([$empresa_id, $no_emp]);
              $emp = $stE->fetch(PDO::FETCH_ASSOC);
              if (!$emp) {
                $flash = 'Empleado no encontrado por No. empleado.';
                $flash_type = 'danger';
              } else {
                $empleado_id = (int)$emp['empleado_id'];
                $uov = $unidad_override > 0 ? $unidad_override : null;
                $stIns = $pdo->prepare("INSERT INTO clima_excepciones (periodo_id, empresa_id, empleado_id, unidad_id_override, reset, motivo, created_by, created_at)
                             VALUES (?, ?, ?, ?, 1, 'EXCEPCION_MANUAL', ?, NOW())
                             ON DUPLICATE KEY UPDATE unidad_id_override = VALUES(unidad_id_override), reset = 1, motivo = VALUES(motivo), created_by = VALUES(created_by), created_at = NOW()");
                $stIns->execute([$periodo_id_post, $empresa_id, $empleado_id, $uov, (int)($_SESSION['usuario_id'] ?? 0)]);

                $flash = 'Excepción agregada para ' . htmlspecialchars(($emp['nombre'].' '.$emp['apellido_paterno'].' '.($emp['apellido_materno']??'')), ENT_QUOTES, 'UTF-8');
                $flash_type = 'success';
                header("Location: clima_generar_elegibles.php?periodo_id=" . (int)$periodo_id_post);
                exit;
              }
            }
          }
        }
        elseif ($action === 'del_excepcion') {
          if (!function_exists('table_exists') || !table_exists($pdo, 'clima_excepciones')) {
            $flash = 'No existe la tabla clima_excepciones.';
            $flash_type = 'danger';
          } else {
            $periodo_id_post = (int)($_POST['periodo_id'] ?? 0);
            $empleado_id = (int)($_POST['empleado_id'] ?? 0);
            if ($periodo_id_post > 0 && $empleado_id > 0) {
              $del = $pdo->prepare("DELETE FROM clima_excepciones WHERE periodo_id=? AND empresa_id=? AND empleado_id=?");
              $del->execute([$periodo_id_post, $empresa_id, $empleado_id]);
              $flash = 'Excepción eliminada.';
              $flash_type = 'info';
              header("Location: clima_generar_elegibles.php?periodo_id=" . (int)$periodo_id_post);
              exit;
            }
          }
        }
        elseif ($action === 'make_elegible') {
          // Convierte un registro no elegible en elegible por excepción (inline toggle)
          $periodo_id_post = (int)($_POST['periodo_id'] ?? 0);
          $empleado_id = (int)($_POST['empleado_id'] ?? 0);
          if ($empresa_id > 0 && $periodo_id_post > 0 && $empleado_id > 0) {
            try {
              $pdo->beginTransaction();

              // Asegura excepción registrada (si existe tabla)
              if (table_exists($pdo, 'clima_excepciones')) {
                $stIns = $pdo->prepare("INSERT INTO clima_excepciones (periodo_id, empresa_id, empleado_id, unidad_id_override, reset, motivo, created_by, created_at)
                                         VALUES (?, ?, ?, NULL, 1, 'EXCEPCION_MANUAL', ?, NOW())
                                         ON DUPLICATE KEY UPDATE reset = 1, motivo = VALUES(motivo), created_by = VALUES(created_by), created_at = NOW()");
                $stIns->execute([$periodo_id_post, $empresa_id, $empleado_id, (int)($_SESSION['usuario_id'] ?? 0)]);
              }

              // Actualiza snapshot directamente
              $up = $pdo->prepare("UPDATE clima_elegibles SET elegible=1, motivo_no_elegible='EXCEPCION' WHERE periodo_id=? AND empresa_id=? AND empleado_id=?");
              $up->execute([$periodo_id_post, $empresa_id, $empleado_id]);

              $pdo->commit();
              $flash = 'Empleado marcado como elegible por excepción.';
              $flash_type = 'success';
              header("Location: clima_generar_elegibles.php?periodo_id=" . (int)$periodo_id_post);
              exit;
            } catch (Exception $ex) {
              if ($pdo->inTransaction()) $pdo->rollBack();
              $flash = $ex->getMessage();
              $flash_type = 'danger';
            }
          }
        }
    }
}

// Conteos y detalle por unidad
$counts = ['total' => 0, 'elegibles' => 0];
$detalle = [];

if ($periodo && $empresa_id > 0) {
    $periodo_id = (int)$periodo['periodo_id'];
    $counts = clima_count_elegibles($pdo, $periodo_id);

    $sql_detalle = "
        SELECT
          ce.unidad_id,
          u.nombre AS unidad_nombre,
          COUNT(*) AS total_registros,
          SUM(CASE WHEN ce.elegible=1 THEN 1 ELSE 0 END) AS total_elegibles,
          SUM(CASE WHEN ce.elegible=0 THEN 1 ELSE 0 END) AS total_no_elegibles
        FROM clima_elegibles ce
        LEFT JOIN org_unidades u ON u.unidad_id = ce.unidad_id
        WHERE ce.periodo_id=? AND ce.empresa_id=?
        GROUP BY ce.unidad_id, u.nombre
        ORDER BY u.nombre ASC
    ";
    $dst = $pdo->prepare($sql_detalle);
    $dst->execute([$periodo_id, $empresa_id]);
    $detalle = $dst->fetchAll(PDO::FETCH_ASSOC);
    
    // Lista detallada de empleados elegibles
    $sql_lista = "
        SELECT
          ce.empleado_id,
          CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', COALESCE(e.apellido_materno, '')) AS nombre_completo,
          e.no_emp,
          u.nombre AS unidad_nombre,
          e.fecha_ingreso,
          ce.elegible,
          ce.motivo_no_elegible
        FROM clima_elegibles ce
        LEFT JOIN empleados e ON e.empleado_id = ce.empleado_id
        LEFT JOIN org_unidades u ON u.unidad_id = ce.unidad_id
        WHERE ce.periodo_id=? AND ce.empresa_id=?
        ORDER BY u.nombre ASC, e.nombre ASC
    ";
    $lst = $pdo->prepare($sql_lista);
    $lst->execute([$periodo_id, $empresa_id]);
    $lista_empleados = $lst->fetchAll(PDO::FETCH_ASSOC);

    // Excepciones actuales
    $excepciones = [];
    if (table_exists($pdo, 'clima_excepciones')) {
      $sx = $pdo->prepare("SELECT ex.empleado_id, ex.unidad_id_override, e.no_emp,
                     CONCAT(e.nombre,' ',e.apellido_paterno,' ',COALESCE(e.apellido_materno,'')) AS nombre,
                     u.nombre AS unidad_nombre
                  FROM clima_excepciones ex
                  LEFT JOIN empleados e ON e.empleado_id = ex.empleado_id
                  LEFT JOIN org_unidades u ON u.unidad_id = COALESCE(ex.unidad_id_override, e.unidad_id)
                  WHERE ex.periodo_id = ? AND ex.empresa_id = ?");
      $sx->execute([$periodo_id, $empresa_id]);
      $excepciones = $sx->fetchAll(PDO::FETCH_ASSOC);
    }

    // Unidades para override
    $unidades = [];
    $su = $pdo->prepare("SELECT unidad_id, nombre FROM org_unidades WHERE empresa_id = ? ORDER BY nombre");
    $su->execute([$empresa_id]);
    $unidades = $su->fetchAll(PDO::FETCH_ASSOC);
} else {
    $lista_empleados = [];
    $excepciones = [];
    $unidades = [];
}

// Layout UI
$page_title  = 'Clima Laboral - Universo Elegible';
$active_menu = 'clima_elegibles';

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
  <div class="page-header-content header-elements-md-inline">
    <div class="page-title d-flex">
      <h4><i class="icon-database mr-2"></i> <span class="font-weight-semibold">Clima Laboral</span> - Universo elegible</h4>
    </div>
  </div>
</div>

<div class="content">

  <?php if ($flash): ?>
    <div class="alert alert-<?=h($flash_type)?> alert-dismissible">
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      <?=h($flash)?>
    </div>
  <?php endif; ?>

  <?php if ($empresa_id <= 0): ?>
    <div class="card">
      <div class="card-body">
        <div class="alert alert-warning mb-0">
          No hay empresa activa en sesión. Revisa tu selector de empresa o la sesión.
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($empresa_id > 0 && empty($periodos)): ?>
    <div class="card">
      <div class="card-body">
        <div class="alert alert-info mb-0">
          No existen periodos de clima para esta empresa. Primero crea un periodo.
        </div>
        <a class="btn btn-success mt-2" href="clima_periodos.php">
          <i class="icon-plus2 mr-1"></i> Crear periodo
        </a>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($empresa_id > 0 && !empty($periodos)): ?>

    <div class="card">
      <div class="card-header header-elements-inline">
        <h5 class="card-title">Generar snapshot de elegibilidad (≥ 1 mes)</h5>
        <div class="header-elements">
          <div class="list-icons">
            <a class="list-icons-item" data-action="collapse"></a>
          </div>
        </div>
      </div>

      <div class="card-body">
        <div class="row align-items-end">
          <div class="col-md-6">
            <label>Periodo</label>
            <select class="form-control"
                    onchange="location.href='clima_generar_elegibles.php?periodo_id='+this.value;">
              <?php foreach ($periodos as $p): ?>
                <option value="<?= (int)$p['periodo_id'] ?>" <?= ((int)$p['periodo_id'] === (int)$periodo_id ? 'selected' : '') ?>>
                  <?= h($p['anio']) ?> (<?= h($p['estatus']) ?>) | Corte: <?= h($p['fecha_corte_elegibilidad']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6 text-right">
            <?php if ($periodo): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?=h($_SESSION['csrf_token'])?>">
                <input type="hidden" name="action" value="generar">
                <input type="hidden" name="periodo_id" value="<?= (int)$periodo_id ?>">
                <button type="submit" class="btn btn-success">
                  <i class="icon-database-insert mr-1"></i> Generar
                </button>
              </form>

              <button type="button" class="btn btn-outline-danger ml-2" data-toggle="modal" data-target="#modal_force">
                <i class="icon-warning22 mr-1"></i> Forzar regeneración
              </button>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($periodo): ?>
          <div class="mt-3">
            <div class="row">
              <div class="col-md-4">
                <div class="border rounded p-2">
                  <div class="text-muted">Año</div>
                  <div class="h5 mb-0"><?=h($periodo['anio'])?></div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2">
                  <div class="text-muted">Corte elegibilidad</div>
                  <div class="h5 mb-0"><?=h($periodo['fecha_corte_elegibilidad'])?></div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2">
                  <div class="text-muted">Snapshot</div>
                  <div class="h5 mb-0">Total: <?= (int)$counts['total'] ?> | Elegibles: <?= (int)$counts['elegibles'] ?></div>
                </div>
              </div>
            </div>

            <small class="text-muted d-block mt-2">
              El snapshot congela el universo para el cálculo del 90%. Solo incluye activos con ≥1 mes de antigüedad. Empleados sin <code>unidad_id</code> se excluyen.
            </small>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header header-elements-inline">
        <h5 class="card-title">Detalle por Unidad (Empresa + Unidad)</h5>
        <div class="header-elements">
          <div class="list-icons">
            <a class="list-icons-item" data-action="collapse"></a>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table datatable-basic" id="tbl_detalle">
          <thead>
            <tr>
              <th>Unidad</th>
              <th>Total snapshot</th>
              <th>Elegibles</th>
              <th>No elegibles</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($detalle as $d): ?>
              <tr>
                <td><?=h($d['unidad_nombre'] ?: ('Unidad ID '.$d['unidad_id']))?></td>
                <td><?= (int)$d['total_registros'] ?></td>
                <td><?= (int)$d['total_elegibles'] ?></td>
                <td><?= (int)$d['total_no_elegibles'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card-body">
        <a class="btn btn-light" href="clima_periodos.php">
          <i class="icon-arrow-left52 mr-1"></i> Regresar a periodos
        </a>
        <a class="btn btn-info ml-2" href="clima_participacion.php?periodo_id=<?= (int)$periodo_id ?>">
          <i class="icon-stats-bars2 mr-1"></i> Monitoreo participación (90%)
        </a>
      </div>
    </div>

    <!-- Lista detallada de empleados -->
    <?php if (!empty($lista_empleados)): ?>
    <div class="card">
      <div class="card-header header-elements-inline">
        <h5 class="card-title">Lista detallada de empleados en snapshot</h5>
        <div class="header-elements">
          <div class="list-icons">
            <a class="list-icons-item" data-action="collapse"></a>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table datatable-basic" id="tbl_lista_empleados">
          <thead>
            <tr>
              <th>No. Empleado</th>
              <th>Nombre</th>
              <th>Unidad</th>
              <th>Fecha Ingreso</th>
              <th>Elegible</th>
              <th>Motivo no elegible</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lista_empleados as $emp): ?>
              <tr>
                <td><?=h($emp['no_emp'])?></td>
                <td><?=h($emp['nombre_completo'])?></td>
                <td><?=h($emp['unidad_nombre'] ?: 'Sin unidad')?></td>
                <td><?=h($emp['fecha_ingreso'] ?: 'N/A')?></td>
                <td>
                  <?php if ((int)$emp['elegible'] === 1): ?>
                    <span class="badge badge-success">Elegible</span>
                  <?php else: ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?=h($_SESSION['csrf_token'])?>">
                      <input type="hidden" name="action" value="make_elegible">
                      <input type="hidden" name="periodo_id" value="<?= (int)$periodo_id ?>">
                      <input type="hidden" name="empleado_id" value="<?= (int)$emp['empleado_id'] ?>">
                      <button type="submit" class="btn btn-outline-primary btn-sm">Hacer elegible</button>
                    </form>
                  <?php endif; ?>
                </td>
                <td><small class="text-muted"><?=h($emp['motivo_no_elegible'] ?: '-')?></small></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Excepciones de elegibilidad -->
    <div class="card">
      <div class="card-header header-elements-inline">
        <h5 class="card-title">Excepciones de elegibilidad</h5>
      </div>
      <div class="card-body">
        <?php if (!function_exists('table_exists') || !table_exists($pdo, 'clima_excepciones')): ?>
          <div class="alert alert-warning">La tabla <code>clima_excepciones</code> no existe. Crea la tabla para habilitar excepciones.</div>
        <?php else: ?>
          <form method="post" class="form-inline mb-3">
            <input type="hidden" name="csrf_token" value="<?=h($_SESSION['csrf_token'])?>">
            <input type="hidden" name="action" value="add_excepcion">
            <input type="hidden" name="periodo_id" value="<?= (int)$periodo_id ?>">
            <div class="form-group mr-2">
              <label class="mr-2">No. empleado</label>
              <input type="text" name="no_emp" class="form-control" placeholder="Ej. 12345" required>
            </div>
            <div class="form-group mr-2">
              <label class="mr-2">Unidad (opcional)</label>
              <select name="unidad_override" class="form-control">
                <option value="0">(usar unidad actual)</option>
                <?php foreach ($unidades as $u): ?>
                  <option value="<?= (int)$u['unidad_id'] ?>"><?= h($u['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-primary">Agregar excepción</button>
          </form>

          <?php if (!empty($excepciones)): ?>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>No. Emp</th>
                    <th>Nombre</th>
                    <th>Unidad</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($excepciones as $ex): ?>
                    <tr>
                      <td><?= h($ex['no_emp']) ?></td>
                      <td><?= h($ex['nombre']) ?></td>
                      <td><?= h($ex['unidad_nombre'] ?: 'N/A') ?></td>
                      <td>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="csrf_token" value="<?=h($_SESSION['csrf_token'])?>">
                          <input type="hidden" name="action" value="del_excepcion">
                          <input type="hidden" name="periodo_id" value="<?= (int)$periodo_id ?>">
                          <input type="hidden" name="empleado_id" value="<?= (int)$ex['empleado_id'] ?>">
                          <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="text-muted">Sin excepciones registradas para este periodo.</div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

  <?php endif; ?>

</div>

<!-- Modal: Forzar regeneración -->
<div class="modal fade" id="modal_force" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?=h($_SESSION['csrf_token'])?>">
      <input type="hidden" name="action" value="generar">
      <input type="hidden" name="periodo_id" value="<?= (int)$periodo_id ?>">
      <input type="hidden" name="force" value="1">

      <div class="modal-header">
        <h5 class="modal-title">Forzar regeneración de elegibles</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>

      <div class="modal-body">
        <p class="mb-2">
          Esta acción eliminará el snapshot actual y lo reconstruirá con base en la tabla <code>empleados</code>.
        </p>
        <div class="alert alert-warning mb-0">
          Úsalo solo antes de iniciar captura. Si ya hay respuestas, el sistema bloquea la regeneración.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger">Sí, forzar</button>
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
(function() {
  if (typeof $ !== 'undefined' && $.fn.DataTable) {
    var dtLangConfig = {
      search: "Buscar:",
      lengthMenu: "Mostrar _MENU_",
      info: "Mostrando _START_ a _END_ de _TOTAL_",
      paginate: { first:"Primero", last:"Último", next:"Siguiente", previous:"Anterior" },
      zeroRecords: "Sin registros"
    };
    
    $('#tbl_detalle').DataTable({
      autoWidth: false,
      order: [[0,'asc']],
      language: dtLangConfig
    });
    
    $('#tbl_lista_empleados').DataTable({
      autoWidth: false,
      order: [[1,'asc']],
      pageLength: 25,
      language: dtLangConfig
    });
  }
})();
</script>
