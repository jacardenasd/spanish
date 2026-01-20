<?php
/**
 * Verificador de Instalación - Módulo Plantilla Autorizada v2.1
 * Guarda este archivo en: public/verificar_plantilla.php
 * Accede desde browser: http://localhost/sgrh/public/verificar_plantilla.php
 */

// Estilos inline para mejor visualización
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verificador - Plantilla Autorizada v2.1</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #ddd;
        }
        .ok {
            border-left-color: #28a745;
            background: #f0fff4;
        }
        .error {
            border-left-color: #dc3545;
            background: #fff8f8;
        }
        .warning {
            border-left-color: #ffc107;
            background: #fffef0;
        }
        h3 {
            margin-top: 0;
            color: #333;
        }
        .check {
            margin: 5px 0;
            padding: 5px 0;
            display: flex;
            align-items: center;
        }
        .check-icon {
            margin-right: 10px;
            font-weight: bold;
            min-width: 30px;
        }
        .ok .check-icon {
            color: #28a745;
        }
        .error .check-icon {
            color: #dc3545;
        }
        .warning .check-icon {
            color: #ffc107;
        }
        .code {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 3px;
            font-family: monospace;
            margin: 10px 0;
            overflow-x: auto;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            margin: 5px 5px 5px 0;
        }
        .button:hover {
            background: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        table th {
            background: #f4f4f4;
            font-weight: bold;
        }
        .summary {
            margin-top: 20px;
            padding: 15px;
            background: #f0f8ff;
            border: 1px solid #007bff;
            border-radius: 3px;
        }
    </style>
</head>
<body>

<div class="container">

<h1>✅ Verificador de Instalación - Plantilla Autorizada v2.1</h1>

<?php

$checks = [
    'ok' => [],
    'error' => [],
    'warning' => []
];

// ============================================================================
// 1. VERIFICAR ARCHIVOS
// ============================================================================

echo '<div class="section"><h3>1. Verificación de Archivos</h3>';

$archivos = [
    'admin_org_plantilla.php' => '../public/admin_org_plantilla.php',
    'plantilla.php' => '../public/plantilla.php',
    'ajax_get_adscripciones.php' => '../public/ajax_get_adscripciones.php',
    'PLANTILLA_AUTORIZADA_README.md' => '../mds/PLANTILLA_AUTORIZADA_README.md',
    'PLANTILLA_INSTALACION.md' => '../mds/PLANTILLA_INSTALACION.md',
    'CAMBIOS_RESUMEN_v2.1.md' => '../mds/CAMBIOS_RESUMEN_v2.1.md',
];

foreach ($archivos as $nombre => $ruta) {
    if (file_exists(__DIR__ . '/' . $ruta)) {
        $checks['ok'][] = "Archivo: $nombre";
        echo '<div class="check"><div class="check-icon">✓</div><div>Archivo: <strong>' . htmlspecialchars($nombre) . '</strong> - Encontrado</div></div>';
    } else {
        $checks['error'][] = "Archivo: $nombre";
        echo '<div class="check"><div class="check-icon">✗</div><div>Archivo: <strong>' . htmlspecialchars($nombre) . '</strong> - NO ENCONTRADO</div></div>';
    }
}

echo '</div>';

// ============================================================================
// 2. VERIFICAR TABLA DE BASE DE DATOS
// ============================================================================

echo '<div class="section"><h3>2. Verificación de Base de Datos</h3>';

try {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../includes/config.php';
        require_once __DIR__ . '/../includes/conexion.php';
    }
    
    // Verificar tabla
    $check_table = $pdo->query("SHOW TABLES LIKE 'org_plantilla_autorizada'");
    if ($check_table->rowCount() > 0) {
        $checks['ok'][] = 'Tabla org_plantilla_autorizada';
        echo '<div class="check"><div class="check-icon">✓</div><div>Tabla: <strong>org_plantilla_autorizada</strong> - Existe</div></div>';
        
        // Contar registros
        $count = $pdo->query("SELECT COUNT(*) FROM org_plantilla_autorizada")->fetchColumn();
        echo '<div class="check"><div class="check-icon">ℹ</div><div>Registros en tabla: <strong>' . (int)$count . '</strong></div></div>';
    } else {
        $checks['error'][] = 'Tabla org_plantilla_autorizada';
        echo '<div class="check"><div class="check-icon">✗</div><div>Tabla: <strong>org_plantilla_autorizada</strong> - NO EXISTE</div></div>';
    }
    
    // Verificar estructura
    $cols = $pdo->query("SHOW COLUMNS FROM org_plantilla_autorizada")->fetchAll(PDO::FETCH_ASSOC);
    $field_names = array_column($cols, 'Field');
    
    $required_fields = ['plaza_id', 'codigo_plaza', 'empresa_id', 'unidad_id', 'empleado_id', 'fecha_asignacion', 'estado'];
    foreach ($required_fields as $field) {
        if (in_array($field, $field_names)) {
            echo '<div class="check"><div class="check-icon">✓</div><div>Campo: <strong>' . htmlspecialchars($field) . '</strong></div></div>';
        } else {
            echo '<div class="check"><div class="check-icon">✗</div><div>Campo: <strong>' . htmlspecialchars($field) . '</strong> - NO EXISTE</div></div>';
            $checks['error'][] = 'Campo ' . $field;
        }
    }
    
} catch (Exception $e) {
    $checks['error'][] = 'Error base de datos: ' . $e->getMessage();
    echo '<div class="check"><div class="check-icon">✗</div><div>Error: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
}

echo '</div>';

// ============================================================================
// 3. VERIFICAR PERMISOS
// ============================================================================

echo '<div class="section"><h3>3. Verificación de Permisos</h3>';

try {
    $permisos = $pdo->query("SELECT clave FROM permisos WHERE clave IN ('plantilla.admin', 'plantilla.ver')")->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('plantilla.admin', $permisos)) {
        $checks['ok'][] = 'Permiso plantilla.admin';
        echo '<div class="check"><div class="check-icon">✓</div><div>Permiso: <strong>plantilla.admin</strong> - Creado</div></div>';
    } else {
        $checks['warning'][] = 'Permiso plantilla.admin';
        echo '<div class="check"><div class="check-icon">⚠</div><div>Permiso: <strong>plantilla.admin</strong> - NO CREADO (ejecutar SQL)</div></div>';
    }
    
    if (in_array('plantilla.ver', $permisos)) {
        $checks['ok'][] = 'Permiso plantilla.ver';
        echo '<div class="check"><div class="check-icon">✓</div><div>Permiso: <strong>plantilla.ver</strong> - Creado</div></div>';
    } else {
        $checks['warning'][] = 'Permiso plantilla.ver';
        echo '<div class="check"><div class="check-icon">⚠</div><div>Permiso: <strong>plantilla.ver</strong> - NO CREADO (ejecutar SQL)</div></div>';
    }
    
} catch (Exception $e) {
    echo '<div class="check"><div class="check-icon">⚠</div><div>Error al verificar permisos: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
}

echo '</div>';

// ============================================================================
// 4. VERIFICAR ASIGNACIONES DE PERMISOS
// ============================================================================

echo '<div class="section"><h3>4. Asignación de Permisos a Roles</h3>';

try {
    $result = $pdo->query("
        SELECT r.nombre AS rol, p.clave AS permiso
        FROM rol_permisos rp
        JOIN roles r ON r.rol_id = rp.rol_id
        JOIN permisos p ON p.permiso_id = rp.permiso_id
        WHERE p.clave IN ('plantilla.admin', 'plantilla.ver')
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($result)) {
        echo '<table><tr><th>Rol</th><th>Permiso</th></tr>';
        foreach ($result as $row) {
            echo '<tr><td>' . htmlspecialchars($row['rol']) . '</td><td><strong>' . htmlspecialchars($row['permiso']) . '</strong></td></tr>';
        }
        echo '</table>';
        $checks['ok'][] = 'Asignaciones de permiso';
    } else {
        echo '<div class="check"><div class="check-icon">⚠</div><div>No hay asignaciones de permisos (puede asignar manualmente vía Admin Usuarios)</div></div>';
        $checks['warning'][] = 'Asignaciones';
    }
} catch (Exception $e) {
    echo '<div class="check"><div class="check-icon">⚠</div><div>Error: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
}

echo '</div>';

// ============================================================================
// 5. RESUMEN FINAL
// ============================================================================

echo '<div class="summary">';
echo '<h2>📊 RESUMEN</h2>';

$total_ok = count($checks['ok']);
$total_error = count($checks['error']);
$total_warning = count($checks['warning']);

echo '<div class="check"><div class="check-icon">✓</div><div><strong>OK:</strong> ' . $total_ok . '</div></div>';
echo '<div class="check"><div class="check-icon">⚠</div><div><strong>ADVERTENCIAS:</strong> ' . $total_warning . '</div></div>';
echo '<div class="check"><div class="check-icon">✗</div><div><strong>ERRORES:</strong> ' . $total_error . '</div></div>';

if ($total_error === 0 && $total_warning === 0) {
    echo '<p style="color: #28a745; font-weight: bold; font-size: 16px;">✅ ¡Instalación completa y correcta!</p>';
} elseif ($total_error === 0) {
    echo '<p style="color: #ffc107; font-weight: bold; font-size: 16px;">⚠️ Instalación parcial - Ejecutar SQL de permisos</p>';
} else {
    echo '<p style="color: #dc3545; font-weight: bold; font-size: 16px;">❌ Problemas detectados - Ver detalles arriba</p>';
}

echo '</div>';

// ============================================================================
// 6. PRÓXIMOS PASOS
// ============================================================================

echo '<div class="section"><h3>📋 Próximos Pasos</h3>';

if ($total_warning > 0) {
    echo '<p><strong>Para completar la instalación:</strong></p>';
    echo '<div class="code">mysql -u usuario -p sgrh &lt; migrations/02_permisos_plantilla_autorizada.sql</div>';
    echo '<p><strong>O ejecutar manualmente en phpMyAdmin:</strong></p>';
    echo '<a href="../../mds/PLANTILLA_INSTALACION.md" class="button">Ver Guía Completa</a>';
}

echo '<p><strong>Para probar:</strong></p>';
echo '<ol>';
echo '<li>Log in como usuario con permiso "plantilla.admin"</li>';
echo '<li>Navega a Organización → Plantilla Autorizada (Admin)</li>';
echo '<li>Crea una plaza nueva</li>';
echo '<li>Asigna un empleado</li>';
echo '<li>Verifica en Bitácora</li>';
echo '</ol>';

echo '<p><strong>Documentación:</strong></p>';
echo '<ul>';
echo '<li><a href="../../mds/PLANTILLA_AUTORIZADA_README.md" class="button">📖 README Completo</a></li>';
echo '<li><a href="../../mds/PLANTILLA_INSTALACION.md" class="button">🔧 Guía Instalación</a></li>';
echo '<li><a href="../../mds/CAMBIOS_RESUMEN_v2.1.md" class="button">📝 Cambios v2.1</a></li>';
echo '</ul>';

echo '</div>';

?>

</div>

</body>
</html>
