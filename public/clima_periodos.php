<?php
// public/clima_periodos.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/conexion.php';

require_login();
require_empresa();
require_password_change_redirect();

// Puedes cambiar este permiso por uno específico como 'clima.admin' cuando lo agregues.
require_perm('organizacion.admin');

if (session_status() === PHP_SESSION_NONE) session_start();
$empresa_id = (int)$_SESSION['empresa_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$flash = null;
$flash_type = 'info';

$errors = [];
$rows = [];

// Procesa acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $post_token)) {
        $flash = 'Solicitud inválida (token).';
        $flash_type = 'danger';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'save') {
                $periodo_id = (int)($_POST['periodo_id'] ?? 0);
                $anio = (int)($_POST['anio'] ?? 0);
                $fecha_inicio = trim((string)($_POST['fecha_inicio'] ?? ''));
                $fecha_fin = trim((string)($_POST['fecha_fin'] ?? ''));
                $fecha_corte = trim((string)($_POST['fecha_corte_elegibilidad'] ?? ''));
                $estatus = trim((string)($_POST['estatus'] ?? 'borrador'));

                if ($anio < 2000 || $anio > 2100) throw new RuntimeException('El año es inválido.');
                if ($fecha_inicio === '' || $fecha_fin === '' || $fecha_corte === '') throw new RuntimeException('Todas las fechas son obligatorias.');
                if (!in_array($estatus, ['borrador','publicado','cerrado'], true)) throw new RuntimeException('Estatus inválido.');
                if ($fecha_inicio > $fecha_fin) throw new RuntimeException('La fecha inicio no puede ser mayor que la fecha fin.');

                // Unicidad por empresa+anio
                if ($periodo_id > 0) {
                    $stmt = $pdo->prepare("SELECT periodo_id FROM clima_periodos WHERE empresa_id=? AND anio=? AND periodo_id<>? LIMIT 1");
                    $stmt->execute([$empresa_id, $anio, $periodo_id]);
                    if ($stmt->fetch()) throw new RuntimeException("Ya existe un periodo para el año {$anio} en esta empresa.");
                } else {
                    $stmt = $pdo->prepare("SELECT periodo_id FROM clima_periodos WHERE empresa_id=? AND anio=? LIMIT 1");
                    $stmt->execute([$empresa_id, $anio]);
                    if ($stmt->fetch()) throw new RuntimeException("Ya existe un periodo para el año {$anio} en esta empresa.");
                }

                if ($periodo_id > 0) {
                    $upd = $pdo->prepare("
                        UPDATE clima_periodos
                        SET anio=?, fecha_inicio=?, fecha_fin=?, fecha_corte_elegibilidad=?, estatus=?, updated_at=NOW()
                        WHERE periodo_id=? AND empresa_id=?
                    ");
                    $upd->execute([$anio, $fecha_inicio, $fecha_fin, $fecha_corte, $estatus, $periodo_id, $empresa_id]);
                    $flash = 'Periodo actualizado correctamente.';
                    $flash_type = 'success';
                } else {
                    $ins = $pdo->prepare("
                        INSERT INTO clima_periodos (empresa_id, anio, fecha_inicio, fecha_fin, fecha_corte_elegibilidad, estatus, creado_por, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $ins->execute([$empresa_id, $anio, $fecha_inicio, $fecha_fin, $fecha_corte, $estatus, (int)$_SESSION['usuario_id']]);
                    $flash = 'Periodo creado correctamente.';
                    $flash_type = 'success';
                }

            } elseif ($action === 'delete') {
                $periodo_id = (int)($_POST['periodo_id'] ?? 0);
                if ($periodo_id <= 0) throw new RuntimeException('Periodo inválido.');

                // Regla recomendada: no borrar si ya hay respuestas (si ya existe esa tabla)
                // Puedes comentar si aún no implementas respuestas.
                $hasResponses = false;
                try {
                    $chk = $pdo->prepare("SELECT 1 FROM clima_respuestas WHERE periodo_id=? LIMIT 1");
                    $chk->execute([$periodo_id]);
                    $hasResponses = (bool)$chk->fetchColumn();
                } catch (Exception $e) {
                    // Si la tabla aún no existe, no bloqueamos.
                    $hasResponses = false;
                }
                if ($hasResponses) throw new RuntimeException('No se puede eliminar: el periodo ya tiene respuestas registradas.');

                $del = $pdo->prepare("DELETE FROM clima_periodos WHERE periodo_id=? AND empresa_id=?");
                $del->execute([$periodo_id, $empresa_id]);

                $flash = 'Periodo eliminado.';
                $flash_type = 'success';
            } else {
                $flash = 'Acción no reconocida.';
                $flash_type = 'warning';
            }
        } catch (Exception $ex) {
            $flash = $ex->getMessage();
            $flash_type = 'danger';
        }
    }
}

// Carga listado
$stmt = $pdo->prepare("SELECT * FROM clima_periodos WHERE empresa_id=? ORDER BY anio DESC");
$stmt->execute([$empresa_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// UI/layout (igual que admin_org_adscripciones.php)
$page_title = 'Clima Laboral - Periodos';
$active_menu = 'clima_periodos';

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
      <h4><i class="icon-calendar3 mr-2"></i> <span class="font-weight-semibold">Clima Laboral</span> - Periodos</h4>
    </div>
    <div class="header-elements d-none d-md-flex">
      <button class="btn btn-success" type="button" data-toggle="modal" data-target="#modal_periodo" onclick="openPeriodoCreate()">
        <i class="icon-plus2 mr-1"></i> Nuevo periodo
      </button>
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
      <h5 class="card-title">Listado de periodos</h5>
      <div class="header-elements">
        <div class="list-icons">
          <a class="list-icons-item" data-action="collapse"></a>
        </div>
      </div>
    </div>

    <div class="card-body">
      <p class="mb-0">
        Administra los periodos anuales de clima para la empresa seleccionada. Una vez creado, el siguiente paso es
        <strong>generar universo elegible</strong> (snapshot) para controlar la regla de 3 meses y el cálculo del 90%.
      </p>
    </div>

    <div class="table-responsive">
      <table class="table" id="tbl_periodos">
        <thead>
          <tr>
            <th>Año</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Corte elegibilidad</th>
            <th>Estatus</th>
            <th class="text-center" style="width:220px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?=h($r['anio'])?></td>
              <td><?=h($r['fecha_inicio'])?></td>
              <td><?=h($r['fecha_fin'])?></td>
              <td><?=h($r['fecha_corte_elegibilidad'])?></td>
              <td>
                <?php
                  $st = (string)$r['estatus'];
                  $badge = 'badge-secondary';
                  if ($st === 'borrador') $badge = 'badge-warning';
                  if ($st === 'publicado') $badge = 'badge-success';
                  if ($st === 'cerrado') $badge = 'badge-dark';
                ?>
                <span class="badge <?=h($badge)?>"><?=h($st)?></span>
              </td>
              <td class="text-center">
                <button class="btn btn-light btn-sm" type="button"
                        data-toggle="modal" data-target="#modal_periodo"
                        onclick='openPeriodoEdit(<?=json_encode($r, JSON_UNESCAPED_UNICODE)?>)'>
                  <i class="icon-pencil7 mr-1"></i> Editar
                </button>

                <a class="btn btn-info btn-sm"
                   href="clima_generar_elegibles.php?periodo_id=<?= (int)$r['periodo_id'] ?>">
                  <i class="icon-database mr-1"></i> Elegibles
                </a>

                <button class="btn btn-danger btn-sm" type="button"
                        data-toggle="modal" data-target="#modal_delete"
                        onclick="openPeriodoDelete(<?= (int)$r['periodo_id'] ?>, <?= (int)$r['anio'] ?>)">
                  <i class="icon-trash mr-1"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Modal: Crear/Editar -->
<div class="modal fade" id="modal_periodo" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?=h($_SESSION['csrf_token'])?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="periodo_id" id="periodo_id" value="0">

      <div class="modal-header">
        <h5 class="modal-title" id="modal_periodo_title">Periodo</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>

      <div class="modal-body">
        <div class="row">
          <div class="col-md-3">
            <label>Año</label>
            <input type="number" class="form-control" name="anio" id="anio" min="2000" max="2100" required>
          </div>
          <div class="col-md-3">
            <label>Inicio</label>
            <input type="date" class="form-control" name="fecha_inicio" id="fecha_inicio" required>
          </div>
          <div class="col-md-3">
            <label>Fin</label>
            <input type="date" class="form-control" name="fecha_fin" id="fecha_fin" required>
          </div>
          <div class="col-md-3">
            <label>Corte elegibilidad</label>
            <input type="date" class="form-control" name="fecha_corte_elegibilidad" id="fecha_corte_elegibilidad" required>
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-md-4">
            <label>Estatus</label>
            <select class="form-control" name="estatus" id="estatus" required>
              <option value="borrador">borrador</option>
              <option value="publicado">publicado</option>
              <option value="cerrado">cerrado</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="d-block">Notas</label>
            <small class="text-muted">
              Recomendación: genera “elegibles” una sola vez por periodo para congelar el universo y proteger el cálculo del 90%.
            </small>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-light" type="button" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Eliminar -->
<div class="modal fade" id="modal_delete" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?=h($_SESSION['csrf_token'])?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="periodo_id" id="delete_periodo_id" value="0">

      <div class="modal-header">
        <h5 class="modal-title">Eliminar periodo</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>

      <div class="modal-body">
        <p class="mb-0">¿Deseas eliminar el periodo <strong id="delete_periodo_label"></strong>?</p>
        <small class="text-muted">No se recomienda eliminar si ya existe captura o respuestas.</small>
      </div>

      <div class="modal-footer">
        <button class="btn btn-light" type="button" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger" type="submit">Eliminar</button>
      </div>
    </form>
  </div>
</div>

<?php
require_once __DIR__ . '/../includes/layout/footer.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
?>
<?php require_once __DIR__ . '/../includes/layout/scripts.php'; ?>

<script>
(function() {
  // DataTables
  if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $('#tbl_periodos').DataTable({
      autoWidth: false,
      order: [[0,'desc']],
      columnDefs: [
        { orderable: false, targets: [5] }
      ],
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

function openPeriodoCreate() {
  $('#modal_periodo_title').text('Nuevo periodo');
  $('#periodo_id').val(0);
  $('#anio').val(new Date().getFullYear());
  $('#fecha_inicio').val('');
  $('#fecha_fin').val('');
  $('#fecha_corte_elegibilidad').val('');
  $('#estatus').val('borrador');
}

function openPeriodoEdit(row) {
  $('#modal_periodo_title').text('Editar periodo');
  $('#periodo_id').val(row.periodo_id);
  $('#anio').val(row.anio);
  $('#fecha_inicio').val(row.fecha_inicio);
  $('#fecha_fin').val(row.fecha_fin);
  $('#fecha_corte_elegibilidad').val(row.fecha_corte_elegibilidad);
  $('#estatus').val(row.estatus);
}

function openPeriodoDelete(periodoId, anio) {
  $('#delete_periodo_id').val(periodoId);
  $('#delete_periodo_label').text(anio);
}
</script>
