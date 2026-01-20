<?php
/**
 * Ajax: Obtener adscripciones (departamentos) filtradas por unidad
 * Usado en el filtrado dinámico de plantilla autorizada
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

// Verificar método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    $pdo = getPDO();
    $empresa_id = $_SESSION['empresa_id'];
    $unidad_id = isset($_GET['unidad_id']) ? (int)$_GET['unidad_id'] : 0;

    if ($unidad_id <= 0) {
        echo json_encode([]);
        exit;
    }

    // Obtener adscripciones de la unidad seleccionada
    $sql = "SELECT adscripcion_id, nombre 
            FROM org_adscripciones 
            WHERE empresa_id = :empresa_id 
              AND unidad_id = :unidad_id
              AND activo = 1
            ORDER BY nombre";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':empresa_id' => $empresa_id,
        ':unidad_id' => $unidad_id
    ]);

    $adscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($adscripciones);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos']);
    error_log('Error en ajax_get_adscripciones: ' . $e->getMessage());
}
