<?php
// public/admin_documentos.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();
require_empresa();
require_password_change_redirect();
require_demograficos_redirect();
require_perm('admin.documentos');

$empresa_id = (int)$_SESSION['empresa_id'];
$usuario_id = (int)$_SESSION['usuario_id'];

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function bitacora($modulo, $accion, $detalle = null) {
    global $pdo;
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

// Manejo de POST
$flash = null;
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

    try {
        $pdo->beginTransaction();

        if ($action === 'crear') {
            $titulo = isset($_POST['titulo']) ? trim((string)$_POST['titulo']) : '';
            $descripcion = isset($_POST['descripcion']) ? trim((string)$_POST['descripcion']) : '';
            $seccion = isset($_POST['seccion']) ? trim((string)$_POST['seccion']) : '';
            $orden = isset($_POST['orden']) ? (int)$_POST['orden'] : 0;

            if ($titulo === '') {
                throw new Exception('El título es obligatorio.');
            }
            if ($seccion === '') {
                throw new Exception('La sección es obligatoria.');
            }

            // Procesar archivo si existe
            $archivo_path = null;
            if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Error al subir el archivo.');
                }

                $tmp = $_FILES['archivo']['tmp_name'];
                $size = (int)$_FILES['archivo']['size'];
                $name = $_FILES['archivo']['name'];

                // Máx 10MB
                if ($size > 10 * 1024 * 1024) {
                    throw new Exception('El archivo no debe exceder 10 MB.');
                }

                // Validar que sea PDF
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    throw new Exception('Solo se permiten archivos PDF.');
                }

                $storage_base = realpath(__DIR__ . '/../storage');
                if ($storage_base === false) {
                    throw new Exception('No existe la carpeta storage.');
                }

                $dir = $storage_base . '/documentos/empresa_' . $empresa_id;
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }

                if (!is_dir($dir)) {
                    throw new Exception('No se pudo crear la carpeta para documentos.');
                }

                $filename = 'doc_' . time() . '_' . preg_replace('/[^a-z0-9_.-]/i', '_', $name);
                $destino = $dir . '/' . $filename;

                if (!move_uploaded_file($tmp, $destino)) {
                    throw new Exception('No se pudo guardar el archivo.');
                }

                $archivo_path = 'documentos/empresa_' . $empresa_id . '/' . $filename;
            }

            $stmt = $pdo->prepare("
                INSERT INTO documentos (empresa_id, titulo, descripcion, archivo_path, seccion, orden, estatus)
                VALUES (:empresa_id, :titulo, :descripcion, :archivo_path, :seccion, :orden, 'activo')
            ");
            $stmt->execute([
                ':empresa_id' => $empresa_id,
                ':titulo' => $titulo,
                ':descripcion' => $descripcion ?: null,
                ':archivo_path' => $archivo_path,
                ':seccion' => $seccion,
                ':orden' => $orden,
            ]);

            bitacora('admin_documentos', 'crear', ['titulo' => $titulo, 'seccion' => $seccion]);
            $flash = 'Documento creado exitosamente.';

        } elseif ($action === 'editar') {
            $documento_id = isset($_POST['documento_id']) ? (int)$_POST['documento_id'] : 0;
            $titulo = isset($_POST['titulo']) ? trim((string)$_POST['titulo']) : '';
            $descripcion = isset($_POST['descripcion']) ? trim((string)$_POST['descripcion']) : '';
            $seccion = isset($_POST['seccion']) ? trim((string)$_POST['seccion']) : '';
            $orden = isset($_POST['orden']) ? (int)$_POST['orden'] : 0;
            $estatus = isset($_POST['estatus']) ? trim((string)$_POST['estatus']) : 'activo';

            if ($documento_id <= 0) {
                throw new Exception('ID de documento inválido.');
            }
            if ($titulo === '') {
                throw new Exception('El título es obligatorio.');
            }
            if ($seccion === '') {
                throw new Exception('La sección es obligatoria.');
            }

            // Obtener documento actual
            $stmt = $pdo->prepare("SELECT archivo_path FROM documentos WHERE documento_id = ? AND empresa_id = ? LIMIT 1");
            $stmt->execute([$documento_id, $empresa_id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$doc) {
                throw new Exception('Documento no encontrado.');
            }

            $archivo_path = $doc['archivo_path'];

            // Procesar archivo si existe
            if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Error al subir el archivo.');
                }

                $tmp = $_FILES['archivo']['tmp_name'];
                $size = (int)$_FILES['archivo']['size'];
                $name = $_FILES['archivo']['name'];

                if ($size > 10 * 1024 * 1024) {
                    throw new Exception('El archivo no debe exceder 10 MB.');
                }

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    throw new Exception('Solo se permiten archivos PDF.');
                }

                // Eliminar archivo anterior si existe
                if ($archivo_path) {
                    $old_file = realpath(__DIR__ . '/../storage') . '/' . $archivo_path;
                    if (file_exists($old_file)) {
                        @unlink($old_file);
                    }
                }

                $storage_base = realpath(__DIR__ . '/../storage');
                $dir = $storage_base . '/documentos/empresa_' . $empresa_id;

                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }

                $filename = 'doc_' . time() . '_' . preg_replace('/[^a-z0-9_.-]/i', '_', $name);
                $destino = $dir . '/' . $filename;

                if (!move_uploaded_file($tmp, $destino)) {
                    throw new Exception('No se pudo guardar el archivo.');
                }

                $archivo_path = 'documentos/empresa_' . $empresa_id . '/' . $filename;
            }

            $stmt = $pdo->prepare("
                UPDATE documentos 
                SET titulo = :titulo, descripcion = :descripcion, archivo_path = :archivo_path, 
                    seccion = :seccion, orden = :orden, estatus = :estatus
                WHERE documento_id = :id AND empresa_id = :empresa_id
            ");
            $stmt->execute([
                ':id' => $documento_id,
                ':empresa_id' => $empresa_id,
                ':titulo' => $titulo,
                ':descripcion' => $descripcion ?: null,
                ':archivo_path' => $archivo_path,
                ':seccion' => $seccion,
                ':orden' => $orden,
                ':estatus' => $estatus,
            ]);

            bitacora('admin_documentos', 'editar', ['documento_id' => $documento_id, 'titulo' => $titulo]);
            $flash = 'Documento actualizado exitosamente.';

        } elseif ($action === 'eliminar') {
            $documento_id = isset($_POST['documento_id']) ? (int)$_POST['documento_id'] : 0;

            if ($documento_id <= 0) {
                throw new Exception('ID de documento inválido.');
            }

            $stmt = $pdo->prepare("SELECT archivo_path, titulo FROM documentos WHERE documento_id = ? AND empresa_id = ? LIMIT 1");
            $stmt->execute([$documento_id, $empresa_id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$doc) {
                throw new Exception('Documento no encontrado.');
            }

            // Eliminar archivo
            if ($doc['archivo_path']) {
                $old_file = realpath(__DIR__ . '/../storage') . '/' . $doc['archivo_path'];
                if (file_exists($old_file)) {
                    @unlink($old_file);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM documentos WHERE documento_id = ? AND empresa_id = ? LIMIT 1");
            $stmt->execute([$documento_id, $empresa_id]);

            bitacora('admin_documentos', 'eliminar', ['documento_id' => $documento_id, 'titulo' => $doc['titulo']]);
            $flash = 'Documento eliminado exitosamente.';
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $flash = 'Error: ' . $e->getMessage();
        $flash_type = 'danger';
    }
}

// Obtener documento a editar si aplica
$doc_editar = null;
$documento_id_edit = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($documento_id_edit > 0) {
    $stmt = $pdo->prepare("SELECT * FROM documentos WHERE documento_id = ? AND empresa_id = ? LIMIT 1");
    $stmt->execute([$documento_id_edit, $empresa_id]);
    $doc_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Listar documentos
$stmt = $pdo->prepare("
    SELECT documento_id, titulo, descripcion, seccion, orden, estatus, created_at
    FROM documentos
    WHERE empresa_id = :empresa_id
    ORDER BY seccion, orden, created_at DESC
");
$stmt->execute([':empresa_id' => $empresa_id]);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por sección
$docs_por_seccion = [];
foreach ($documentos as $doc) {
    $sec = $doc['seccion'];
    if (!isset($docs_por_seccion[$sec])) {
        $docs_por_seccion[$sec] = [];
    }
    $docs_por_seccion[$sec][] = $doc;
}

// Variables de página
$page_title = 'Gestión de Documentos';
$breadcrumb_home = 'Inicio';
$breadcrumb_lvl1 = 'Administración';
$active_menu = 'admin_documentos';

$extra_css = [];
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
            <h4><span class="font-weight-semibold"><?php echo h($page_title); ?></span></h4>
        </div>
    </div>
    <div class="breadcrumb-line breadcrumb-line-light header-elements-lg-inline">
        <div class="d-flex">
            <div class="breadcrumb">
                <a href="<?php echo ASSET_BASE; ?>public/dashboard.php" class="breadcrumb-item">
                    <i class="icon-home2 mr-2"></i> <?php echo $breadcrumb_home; ?>
                </a>
                <span class="breadcrumb-item"><?php echo $breadcrumb_lvl1; ?></span>
                <span class="breadcrumb-item active"><?php echo h($page_title); ?></span>
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

    <!-- Formulario para crear/editar documento -->
    <div class="card mb-3">
        <div class="card-header header-elements-inline">
            <h5 class="card-title"><?php echo $doc_editar ? 'Editar Documento' : 'Crear Nuevo Documento'; ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo $doc_editar ? 'editar' : 'crear'; ?>">
                <?php if ($doc_editar): ?>
                <input type="hidden" name="documento_id" value="<?php echo (int)$doc_editar['documento_id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Título <span class="text-danger">*</span></label>
                    <input type="text" name="titulo" class="form-control" value="<?php echo h($doc_editar['titulo'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3"><?php echo h($doc_editar['descripcion'] ?? ''); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Sección <span class="text-danger">*</span></label>
                            <select name="seccion" class="form-control" required>
                                <option value="">Seleccionar sección...</option>
                                <option value="clima" <?php echo ($doc_editar['seccion'] ?? '') === 'clima' ? 'selected' : ''; ?>>Clima Laboral</option>
                                <option value="desempeño" <?php echo ($doc_editar['seccion'] ?? '') === 'desempeño' ? 'selected' : ''; ?>>Evaluación del Desempeño</option>
                                <option value="perfil" <?php echo ($doc_editar['seccion'] ?? '') === 'perfil' ? 'selected' : ''; ?>>Mi Perfil</option>
                                <option value="admin" <?php echo ($doc_editar['seccion'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administración</option>
                                <option value="general" <?php echo ($doc_editar['seccion'] ?? '') === 'general' ? 'selected' : ''; ?>>General</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Orden</label>
                            <input type="number" name="orden" class="form-control" value="<?php echo (int)($doc_editar['orden'] ?? 0); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Archivo PDF <?php if (!$doc_editar) echo '<span class="text-danger">*</span>'; ?></label>
                    <div class="custom-file">
                        <input type="file" name="archivo" class="custom-file-input" accept=".pdf" <?php echo !$doc_editar ? 'required' : ''; ?>>
                        <label class="custom-file-label">Elegir archivo...</label>
                    </div>
                    <?php if ($doc_editar && $doc_editar['archivo_path']): ?>
                    <small class="form-text text-muted mt-2">Archivo actual: <?php echo basename($doc_editar['archivo_path']); ?></small>
                    <?php endif; ?>
                </div>

                <?php if ($doc_editar): ?>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estatus" class="form-control">
                        <option value="activo" <?php echo $doc_editar['estatus'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo $doc_editar['estatus'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="icon-checkmark mr-2"></i> <?php echo $doc_editar ? 'Actualizar' : 'Crear'; ?>
                    </button>
                    <?php if ($doc_editar): ?>
                    <a href="admin_documentos.php" class="btn btn-light">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de documentos -->
    <div class="card">
        <div class="card-header header-elements-inline">
            <h5 class="card-title">Documentos Registrados</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Sección</th>
                        <th>Título</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documentos)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No hay documentos registrados</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($docs_por_seccion as $seccion => $docs): ?>
                            <?php foreach ($docs as $doc): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-primary"><?php echo h($doc['seccion']); ?></span>
                                </td>
                                <td><?php echo h($doc['titulo']); ?></td>
                                <td>
                                    <?php if ($doc['descripcion']): ?>
                                    <small><?php echo h(substr($doc['descripcion'], 0, 50)); ?></small>
                                    <?php else: ?>
                                    <small class="text-muted">Sin descripción</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($doc['estatus'] === 'activo'): ?>
                                    <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                    <span class="badge badge-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></small></td>
                                <td>
                                    <a href="?edit=<?php echo (int)$doc['documento_id']; ?>" class="btn btn-sm btn-info" title="Editar">
                                        <i class="icon-pencil"></i>
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="eliminar">
                                        <input type="hidden" name="documento_id" value="<?php echo (int)$doc['documento_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este documento?');">
                                            <i class="icon-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/../includes/layout/footer.php';
require_once __DIR__ . '/../includes/layout/content_close.php';
?>

<?php
require_once __DIR__ . '/../includes/layout/scripts.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mejorar apariencia de input file
    const fileInputs = document.querySelectorAll('.custom-file-input');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const label = this.nextElementSibling;
            label.textContent = this.files[0] ? this.files[0].name : 'Elegir archivo...';
        });
    });
});
</script>
