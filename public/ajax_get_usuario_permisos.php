<?php
/**
 * ajax_get_usuario_permisos.php
 * Obtiene los permisos especiales de un usuario
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/conexion.php';

require_login();
require_empresa();
require_perm('usuarios.admin');

header('Content-Type: application/json; charset=utf-8');

$usuario_id = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
$empresa_id = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;

if ($usuario_id <= 0 || $empresa_id <= 0) {
    echo json_encode(['error' => 'Parámetros inválidos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT permisos_especiales FROM usuarios WHERE usuario_id = :uid AND empresa_id = :eid");
    $stmt->execute([':uid' => $usuario_id, ':eid' => $empresa_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => 'Usuario no encontrado'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $permisos_data = [];
    if (!empty($user['permisos_especiales'])) {
        $permisos_data = json_decode($user['permisos_especiales'], true);
        if (!is_array($permisos_data)) {
            $permisos_data = [];
        }
    }
    
    $response = [
        'permisos' => isset($permisos_data['permisos']) && is_array($permisos_data['permisos']) ? $permisos_data['permisos'] : [],
        'unidades' => isset($permisos_data['unidades']) && is_array($permisos_data['unidades']) ? $permisos_data['unidades'] : []
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al obtener permisos: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
