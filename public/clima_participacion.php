<?php
// public/clima_participacion.php
// Dashboard de participación por Empresa+Unidad (unidad mínima) + publicación de resultados
// Reglas:
// - Solo elegibles (clima_elegibles.elegible=1)
// - Participación = empleados con al menos 1 respuesta (DISTINCT empleado_id en clima_respuestas)
// - Publicación de resultados (clima_publicacion.habilitado=1) solo si >= 90% (por unidad)

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/conexion.php';

require_login();
require_empresa();
require_password_change_redirect();
require_demograficos_redirect();

// Ajusta el permiso si tu clave es otra (ej. 'clima.admin')
// Permitir acceso con cualquiera de estos permisos
if (function_exists('require_perm_any')) {
  require_perm_any(['organizacion.admin', 'clima.admin']);
} else {
  if (!can('organizacion.admin') && !can('clima.admin')) {
    header('Location: sin_permiso.php');
    exit;
  }
}

if (session_status() === PHP_SESSION_NONE) session_start();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$empresa_id = (int)($_SESSION['empresa_id'] ?? 0);
$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);

if ($empresa_id <= 0) {
    http_response_code(400);
    die('Empresa inválida en sesión.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$flash = null;
$flash_type = 'info';

$periodo_id = (int)($_GET['periodo_id'] ?? 0);

// Periodos de la empresa
$periodos_stmt = $pdo->prepare("SELECT periodo_id, anio, estatus, fecha_inicio, fecha_fin FROM clima_periodos WHERE empresa_id=? ORDER BY anio DESC");
$periodos_stmt->execute([$empresa_id]);
$periodos = $periodos_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($periodo_id <= 0 && !empty($periodos)) {
    $periodo_id = (int)$periodos[0]['periodo_id'];
}

// Periodo actual
$periodo = null;
if ($periodo_id > 0) {
    $pstmt = $pdo->prepare("SELECT * FROM clima_periodos WHERE periodo_id=? AND empresa_id=? LIMIT 1");
    $pstmt->execute([$periodo_id, $empresa_id]);
    $periodo = $pstmt->fetch(PDO::FETCH_ASSOC);
}

if (!$periodo) {
    $flash = 'No hay un periodo válido seleccionado para esta empresa.';
    $flash_type = 'warning';
}

// --- Acciones POST: publicar / despublicar por unidad (clima_publicacion) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Solicitud inválida (CSRF).');
        }

        $action = (string)($_POST['action'] ?? '');
        $pid = (int)($_POST['periodo_id'] ?? 0);
        $uid = (int)($_POST['unidad_id'] ?? 0);
        $habilitar = (int)($_POST['habilitar'] ?? 0); // 1 publicar, 0 despublicar

        if (!$periodo || $pid !== (int)$periodo_id) {
            throw new RuntimeException('Periodo inválido.');
        }
        if ($pid <= 0 || $uid <= 0) {
            throw new RuntimeException('Parámetros inválidos.');
        }

        if ($action === 'toggle_publicacion') {

            // Regla 90%: calcular participación (con tabla de respuestas vacía funciona perfecto)
            $sql_pct = "
                SELECT
                  COUNT(*) AS total_elegibles,
                  SUM(CASE WHEN r.empleado_id IS NOT NULL THEN 1 ELSE 0 END) AS respondidos
                FROM clima_elegibles e
                LEFT JOIN (
                    SELECT DISTINCT periodo_id, empleado_id
                    FROM clima_respuestas
                    WHERE periodo_id = ?
                ) r ON r.periodo_id = e.periodo_id AND r.empleado_id = e.empleado_id
                WHERE e.periodo_id = ?
                  AND e.empresa_id = ?
                  AND e.unidad_id = ?
                  AND e.elegible = 1
            ";
            $st = $pdo->prepare($sql_pct);
            $st->execute([$pid, $pid, $empresa_id, $uid]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            $total_e = (int)($row['total_elegibles'] ?? 0);
            $resp = (int)($row['respondidos'] ?? 0);
            $pct = ($total_e > 0) ? round(($resp * 100) / $total_e, 2) : 0.0;

            if ($habilitar === 1) {
                if ($total_e <= 0) {
                    throw new RuntimeException('No hay elegibles para esta unidad. No se puede publicar.');
                }
                if ($pct < 90) {
                    throw new RuntimeException("No se puede publicar: participación {$pct}% (mínimo 90%).");
                }
            }

            // Upsert en clima_publicacion (tu tabla “oficial” para habilitar vista)
            $pdo->beginTransaction();

            $sql_up = "
                INSERT INTO clima_publicacion
                  (periodo_id, empresa_id, unidad_id, habilitado, fecha_publicacion, publicado_por)
                VALUES
                  (?, ?, ?, ?, " . ($habilitar === 1 ? "NOW()" : "NULL") . ", ?)
                ON DUPLICATE KEY UPDATE
                  habilitado = VALUES(habilitado),
                  fecha_publicacion = VALUES(fecha_publicacion),
                  publicado_por = VALUES(publicado_por)
            ";
            $up = $pdo->prepare($sql_up);
            $up->execute([$pid, $empresa_id, $uid, $habilitar, $usuario_id]);

            $pdo->commit();

            $flash = ($habilitar === 1) ? 'Resultados publicados para la unidad.' : 'Resultados despublicados para la unidad.';
            $flash_type = 'success';

            header("Location: clima_participacion.php?periodo_id=" . (int)$pid);
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $flash = $e->getMessage();
        $flash_type = 'danger';
    }
}

// --- Datos dashboard ---
$resumen = ['total_elegibles' => 0, 'respondidos' => 0, 'pct' => 0.0];
$detalle = [];

if ($periodo) {

    // Resumen empresa (solo elegibles)
    $sql_res = "
        SELECT
          COUNT(*) AS total_elegibles,
          SUM(CASE WHEN r.empleado_id IS NOT NULL THEN 1 ELSE 0 END) AS respondidos
        FROM clima_elegibles e
        LEFT JOIN (
            SELECT DISTINCT periodo_id, empleado_id
            FROM clima_respuestas
            WHERE periodo_id = ?
        ) r ON r.periodo_id = e.periodo_id AND r.empleado_id = e.empleado_id
        WHERE e.periodo_id = ?
          AND e.empresa_id = ?
          AND e.elegible = 1
    ";
    $st = $pdo->prepare($sql_res);
    $st->execute([$periodo_id, $periodo_id, $empresa_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $total_e = (int)($row['total_elegibles'] ?? 0);
    $resp = (int)($row['respondidos'] ?? 0);
    $pct = ($total_e > 0) ? round(($resp * 100) / $total_e, 2) : 0.0;

    $resumen = ['total_elegibles' => $total_e, 'respondidos' => $resp, 'pct' => $pct];

    // Detalle por unidad + publicación
    $sql_det = "
        SELECT
          e.unidad_id,
          u.nombre AS unidad_nombre,
          COUNT(*) AS total_elegibles,
          SUM(CASE WHEN r.empleado_id IS NOT NULL THEN 1 ELSE 0 END) AS respondidos,
          ROUND(
            (SUM(CASE WHEN r.empleado_id IS NOT NULL THEN 1 ELSE 0 END) * 100) / NULLIF(COUNT(*),0)
          , 2) AS pct,
          COALESCE(p.habilitado, 0) AS publicado
        FROM clima_elegibles e
        LEFT JOIN org_unidades u ON u.unidad_id = e.unidad_id
        LEFT JOIN (
            SELECT DISTINCT periodo_id, empleado_id
            FROM clima_respuestas
            WHERE periodo_id = ?
        ) r ON r.periodo_id = e.periodo_id AND r.empleado_id = e.empleado_id
        LEFT JOIN clima_publicacion p
          ON p.periodo_id = e.periodo_id
         AND p.empresa_id = e.empresa_id
         AND p.unidad_id  = e.unidad_id
        WHERE e.periodo_id = ?
          AND e.empresa_id = ?
          AND e.elegible = 1
        GROUP BY e.unidad_id, u.nombre, p.habilitado
        ORDER BY u.nombre
    ";
    $dst = $pdo->prepare($sql_det);
    $dst->execute([$periodo_id, $periodo_id, $empresa_id]);
    $detalle = $dst->fetchAll(PDO::FETCH_ASSOC);
}

// --- Layout (Limitless) ---
$page_title  = 'Clima Laboral - Participación';
$active_menu = 'clima_participacion';

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
      <h4><i class="icon-stats-bars2 mr-2"></i> <span class="font-weight-semibold">Clima Laboral</span> - Participación</h4>
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

  <div class="card">
    <div class="card-header header-elements-inline">
      <h5 class="card-title">Periodo</h5>
      <div class="header-elements">
        <div class="list-icons">
          <a class="list-icons-item" data-action="collapse"></a>
        </div>
      </div>
    </div>

    <div class="card-body">
      <div class="row align-items-end">
        <div class="col-md-6">
          <label>Selecciona periodo</label>
          <select class="form-control" onchange="location.href='clima_participacion.php?periodo_id='+this.value;">
            <?php foreach ($periodos as $p): ?>
              <option value="<?= (int)$p['periodo_id'] ?>" <?= ((int)$p['periodo_id']===(int)$periodo_id ? 'selected' : '') ?>>
                <?= h($p['anio']) ?> (<?= h($p['estatus']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <?php if ($periodo): ?>
            <div class="border rounded p-2">
              <div class="text-muted">Resumen empresa (solo elegibles)</div>
              <div class="h5 mb-0">
                Elegibles: <?= (int)$resumen['total_elegibles'] ?> |
                Respondidos: <?= (int)$resumen['respondidos'] ?> |
                Participación: <?= h($resumen['pct']) ?>%
              </div>
              <small class="text-muted">
                Nota: Si nadie ha contestado, Respondidos=0 y Participación=0% (esto es esperado).
                Regla: publicación por unidad requiere ≥ 90%.
              </small>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header header-elements-inline">
      <h5 class="card-title">Participación por Unidad (Empresa + Unidad)</h5>
      <div class="header-elements">
        <div class="list-icons">
          <a class="list-icons-item" data-action="collapse"></a>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table datatable-basic" id="tbl_part">
        <thead>
          <tr>
            <th>Unidad</th>
            <th>Elegibles</th>
            <th>Respondidos</th>
            <th>%</th>
            <th>Regla 90%</th>
            <th>Resultados (publicación)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($detalle as $d):
            $pct = (float)($d['pct'] ?? 0.0);
            $cumple = ($pct >= 90.0);
            $publicado = ((int)($d['publicado'] ?? 0) === 1);
          ?>
            <tr>
              <td><?=h($d['unidad_nombre'] ?: ('Unidad ID '.$d['unidad_id']))?></td>
              <td><?= (int)$d['total_elegibles'] ?></td>
              <td><?= (int)$d['respondidos'] ?></td>
              <td><?= h($d['pct']) ?>%</td>
              <td>
                <?php if ($cumple): ?>
                  <span class="badge badge-success">Cumple</span>
                <?php else: ?>
                  <span class="badge badge-warning">No cumple</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="clima_elegibles_detalle.php?periodo_id=<?= (int)$periodo_id ?>&unidad_id=<?= (int)$d['unidad_id'] ?>" class="btn btn-info btn-sm mr-1" title="Ver detalle de elegibles">
                  <i class="icon-list2 mr-1"></i> Detalle
                </a>

                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?=h($_SESSION['csrf_token'])?>">
                  <input type="hidden" name="action" value="toggle_publicacion">
                  <input type="hidden" name="periodo_id" value="<?= (int)$periodo_id ?>">
                  <input type="hidden" name="unidad_id" value="<?= (int)$d['unidad_id'] ?>">
                  <input type="hidden" name="habilitar" value="<?= $publicado ? 0 : 1 ?>">

                  <?php if ($publicado): ?>
                    <button type="submit" class="btn btn-danger btn-sm">
                      <i class="icon-eye-blocked mr-1"></i> Despublicar
                    </button>
                  <?php else: ?>
                    <?php if (!$cumple): ?>
                      <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Requiere 90% de participación">
                        <i class="icon-lock2 mr-1"></i> Bloqueado (&lt;90%)
                      </button>
                    <?php else: ?>
                      <button type="submit" class="btn btn-success btn-sm">
                        <i class="icon-eye mr-1"></i> Publicar
                      </button>
                    <?php endif; ?>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card-body">
      <a class="btn btn-light" href="clima_generar_elegibles.php?periodo_id=<?= (int)$periodo_id ?>">
        <i class="icon-arrow-left52 mr-1"></i> Regresar a elegibles
      </a>
    </div>
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
    $('#tbl_part').DataTable({
      autoWidth: false,
      order: [[0,'asc']]
    });
  }
})();
</script>
