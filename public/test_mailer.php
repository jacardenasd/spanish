<?php
/**
 * Prueba de envío de correos con PHPMailer
 * 
 * Accede a: http://localhost/sgrh/public/test_mailer.php
 */

require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/mail_templates.php';

// PROCESAR FORMULARIO PRIMERO (antes de cualquier output HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $_POST['correo'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $asunto = $_POST['asunto'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if ($action === 'preview') {
        // Generar HTML
        if ($tipo === 'recuperacion') {
            $enlace = (defined('APP_URL') ? APP_URL : 'http://localhost/sgrh/') . 'public/resetear.php?token=ABC123XYZ...';
            $html = plantilla_recuperar_contrasena($nombre, $enlace);
        } elseif ($tipo === 'confirmacion') {
            $html = plantilla_contrasena_cambiada($nombre);
        } else {
            $html = $_POST['contenido'] ?? '<p>Contenido personalizado</p>';
        }
        
        // Guardar en storage
        $baseDir = realpath(__DIR__ . '/..');
        $dir = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mails';
        
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $file = $dir . DIRECTORY_SEPARATOR . date('Ymd_His') . '__PLANTILLA.html';
        file_put_contents($file, $html);
        
        header('Location: ?info=' . urlencode('Plantilla guardada en storage/mails/'));
        exit;
    }
    
    if ($action === 'test') {
        try {
            // Generar HTML
            if ($tipo === 'recuperacion') {
                $enlace = (defined('APP_URL') ? APP_URL : 'http://localhost/sgrh/') . 'public/resetear.php?token=ABC123XYZ...';
                $html = plantilla_recuperar_contrasena($nombre, $enlace);
            } elseif ($tipo === 'confirmacion') {
                $html = plantilla_contrasena_cambiada($nombre);
            } else {
                $html = $_POST['contenido'] ?? '<p>Contenido personalizado</p>';
            }
            
            // Enviar
            $resultado = enviar_correo($correo, $asunto, $html);
            
            if ($resultado) {
                header('Location: ?success=1');
            } else {
                header('Location: ?error=' . urlencode('Error al enviar. Revisa la configuración.'));
            }
            exit;
        } catch (Exception $e) {
            header('Location: ?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Envío de Correos - SGRH</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { 
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
        }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #999; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { 
            display: block; 
            margin-bottom: 8px; 
            color: #555; 
            font-weight: 500; 
        }
        input[type="email"], textarea { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            font-size: 14px;
            font-family: inherit;
        }
        input[type="email"]:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        textarea { resize: vertical; min-height: 120px; }
        .buttons { 
            display: flex; 
            gap: 10px; 
            margin-top: 30px;
        }
        button { 
            flex: 1;
            padding: 12px; 
            border: none; 
            border-radius: 4px; 
            font-size: 16px; 
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-test { 
            background: #667eea; 
            color: white; 
        }
        .btn-test:hover { 
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-plantilla { 
            background: #f0f0f0; 
            color: #333; 
        }
        .btn-plantilla:hover { 
            background: #e0e0e0;
        }
        .result { 
            margin-top: 20px; 
            padding: 15px; 
            border-radius: 4px;
            display: none;
        }
        .result.success { 
            background: #f6ffed; 
            border-left: 4px solid #52c41a; 
            color: #22863a;
        }
        .result.error { 
            background: #fff1f0; 
            border-left: 4px solid #ff4d4f; 
            color: #d32f2f;
        }
        .result.info {
            background: #e6f7ff;
            border-left: 4px solid #1890ff;
            color: #0050b3;
        }
        .info-box { 
            background: #f9f9f9; 
            padding: 15px; 
            border-radius: 4px; 
            margin-bottom: 20px;
            font-size: 13px;
            color: #666;
            border: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✉️ Prueba de Envío de Correos</h1>
        <p class="subtitle">Sistema SGRH - Configuración SMTP Titan</p>
        
        <div class="info-box">
            <strong>ℹ️ Información:</strong>
            En modo DEV, los correos se guardan en <code>storage/mails/</code> para inspección.
            <?php if (defined('APP_ENV') && APP_ENV === 'dev'): ?>
                <br><strong>Modo actual: DESARROLLO</strong>
            <?php else: ?>
                <br><strong>Modo actual: PRODUCCIÓN</strong>
            <?php endif; ?>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="correo">Correo Destino:</label>
                <input 
                    type="email" 
                    id="correo" 
                    name="correo" 
                    placeholder="usuario@ejemplo.com" 
                    value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="nombre">Nombre del Usuario:</label>
                <input 
                    type="text" 
                    id="nombre" 
                    name="nombre" 
                    placeholder="Juan Pérez" 
                    value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="asunto">Asunto:</label>
                <input 
                    type="text" 
                    id="asunto" 
                    name="asunto" 
                    placeholder="Recupera tu contraseña" 
                    value="<?php echo htmlspecialchars($_POST['asunto'] ?? 'Recupera tu contraseña'); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="tipo">Tipo de Correo:</label>
                <select name="tipo" id="tipo" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit;">
                    <option value="recuperacion">Recuperación de Contraseña</option>
                    <option value="confirmacion">Confirmación de Cambio</option>
                    <option value="custom">Personalizado</option>
                </select>
            </div>

            <div class="form-group" id="custom-content" style="display: none;">
                <label for="contenido">Contenido HTML:</label>
                <textarea 
                    id="contenido" 
                    name="contenido" 
                    placeholder="Ingresa el HTML del correo aquí"
                ><?php echo htmlspecialchars($_POST['contenido'] ?? ''); ?></textarea>
            </div>

            <div class="buttons">
                <button type="submit" name="action" value="test" class="btn-test">🚀 Enviar Prueba</button>
                <button type="submit" name="action" value="plantilla" class="btn-plantilla">📋 Ver Plantilla</button>
            </div>
        </form>

        <div id="result" class="result"></div>
    </div>

    <script>
        // Mostrar/ocultar campo personalizado
        document.getElementById('tipo').addEventListener('change', function() {
            document.getElementById('custom-content').style.display = 
                this.value === 'custom' ? 'block' : 'none';
        });

        // Procesar respuesta
        window.addEventListener('load', function() {
            const resultDiv = document.getElementById('result');
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.get('success') === '1') {
                resultDiv.className = 'result success';
                resultDiv.innerHTML = '<strong>✓ Éxito</strong><br>El correo ha sido enviado correctamente.';
                resultDiv.style.display = 'block';
            } else if (urlParams.get('error')) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = '<strong>✗ Error</strong><br>' + decodeURIComponent(urlParams.get('error'));
                resultDiv.style.display = 'block';
            } else if (urlParams.get('info')) {
                resultDiv.className = 'result info';
                resultDiv.innerHTML = '<strong>ℹ️ Información</strong><br>' + decodeURIComponent(urlParams.get('info'));
                resultDiv.style.display = 'block';
            }
        });
    </script>
</body>
</html>

