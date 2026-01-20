<?php
// includes/migrations.php
// Ejecutar migraciones automáticas

require_once __DIR__ . '/conexion.php';

try {
    // Verificar si la columna sidebar_state existe
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'sidebar_state'";
    $stmt = $pdo->query($sql);
    $exists = $stmt->fetch();
    
    if (!$exists) {
        // Agregar la columna si no existe
        $alter = "ALTER TABLE usuarios ADD COLUMN sidebar_state VARCHAR(50) DEFAULT 'normal'";
        $pdo->exec($alter);
    }
} catch (Exception $e) {
    // Silenciar errores de migración
    error_log('Migration error: ' . $e->getMessage());
}
?>
