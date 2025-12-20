<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';

require_login();

$empresas = $_SESSION['empresas'] ?? [];
$empresa_id = (int)($_POST['empresa_id'] ?? 0);

$found = null;
foreach ($empresas as $e) {
    if ((int)$e['empresa_id'] === $empresa_id) {
        $found = $e;
        break;
    }
}

if (!$found) {
    header('Location: seleccionar_empresa.php');
    exit;
}

$_SESSION['empresa_id'] = (int)$found['empresa_id'];
$_SESSION['empresa_nombre'] = $found['nombre'];
$_SESSION['empresa_alias'] = $found['alias'];
$_SESSION['es_admin_empresa'] = (int)$found['es_admin'];

// Recalcular permisos para el contexto de empresa actual
cargar_permisos_sesion((int)$_SESSION['usuario_id']);

header('Location: index.php');
exit;
