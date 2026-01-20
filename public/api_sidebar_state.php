<?php
// public/api_sidebar_state.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/conexion.php';

// Verificar que el usuario esté autenticado
require_login();

// Permitir AJAX desde el mismo sitio
header('Content-Type: application/json');

// Log de depuración
error_log('=== API SIDEBAR STATE ===');
error_log('Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('User ID: ' . (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 'NO SESSION'));
error_log('POST data: ' . json_encode($_POST));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = (int)$_SESSION['usuario_id'];
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $state = isset($_POST['state']) ? (string)$_POST['state'] : '';
    
    error_log("Action: $action, State: $state, User: $usuario_id");
    
    try {
        if ($action === 'save') {
            // Guardar estado del sidebar
            $sql = "UPDATE usuarios 
                    SET sidebar_state = :state 
                    WHERE usuario_id = :uid";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                ':state' => $state,
                ':uid' => $usuario_id
            ]);
            
            error_log("Query executed. Rows affected: " . $stmt->rowCount());
            
            // Verificar que se guardó
            $verify = $pdo->prepare("SELECT sidebar_state FROM usuarios WHERE usuario_id = :uid LIMIT 1");
            $verify->execute([':uid' => $usuario_id]);
            $row = $verify->fetch(PDO::FETCH_ASSOC);
            error_log("Verification - Database value: " . ($row ? $row['sidebar_state'] : 'NULL'));
            
            echo json_encode(['success' => true, 'message' => 'Estado guardado', 'value' => $state]);
        } elseif ($action === 'get') {
            // Obtener estado del sidebar
            $sql = "SELECT sidebar_state FROM usuarios WHERE usuario_id = :uid LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid' => $usuario_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $state = $row ? ($row['sidebar_state'] ?: '') : '';
            error_log("Sidebar state retrieved for user $usuario_id: $state");
            echo json_encode(['success' => true, 'state' => $state]);
        } else {
            error_log("Invalid action: $action");
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        error_log('Sidebar API Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    error_log('Invalid method: ' . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
