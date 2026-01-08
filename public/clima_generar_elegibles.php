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
if (function_exists('require_perm')) {
    require_perm('organizacion.admin');
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

                        // es_activo ENUM('activo','inactivo','baja')
                        $sql = "
                            INSERT INTO clima_elegibles
                              (periodo_id, empleado_id, empresa_id, unidad_id, elegible, motivo_no_elegible, created_at)
                            SELECT
                              ?,
                              e.empleado_id,
                              e.empresa_id,
                              e.unidad_id,
                              CASE
                                WHEN e.es_activo = 1
                                 AND e.fecha_ingreso IS NOT NULL
                                 AND e.fecha_ingreso <= DATE_SUB(?, INTERVAL 3 MONTH)
                                THEN 1 ELSE 0
                              END,
                              CASE
                                WHEN e.es_activo <> 1 THEN 'INACTIVO/BAJA'
                                WHEN e.fecha_ingreso IS NULL THEN 'SIN_FECHA_INGRESO'
                                WHEN e.fecha_ingreso > DATE_SUB(?, INTERVAL 3 MONTH) THEN 'ANTIGUEDAD_MENOR_3_MESES'
                                ELSE NULL
                              END,
                              NOW()
                            FROM empleados e
                            WHERE e.empresa_id = ?
                              AND e.unidad_id IS NOT NULL
                            ON DUPLICATE KEY UPDATE
                              empresa_id = VALUES(empresa_id),
                              unidad_id  = VALUES(unidad_id),
                              elegible   = VALUES(elegible),
                              motivo_no_elegible = VALUES(motivo_no_elegible)
                        ";

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            $periodo_id_post,
                            $fecha_corte,
                            $fecha_corte,
                            $empresa_id
                        ]);

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
        <h5 class="card-title">Generar snapshot de elegibilidad (≥ 3 meses)</h5>
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
              El snapshot congela el universo para el cálculo del 90%. Empleados sin <code>unidad_id</code> se excluyen.
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
    $('#tbl_detalle').DataTable({
      autoWidth: false,
      order: [[0,'asc']],
      language: {
        search: "Buscar:",
        lengthMenu: "Mostrar _MENU_",
        info: "Mostrando _START_ a _END_ de _TOTAL_",
        paginate: { first:"Primero", last:"Último", next:"Siguiente", previous:"Anterior" },
        zeroRecords: "Sin registros"
      }
    });
  }
})();
</script>
