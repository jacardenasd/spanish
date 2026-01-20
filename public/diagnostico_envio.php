<?php
/**
 * Diagnóstico en Tiempo Real - Ver logs de envío de correos
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/mail_templates.php';

// Limpiar logs anteriores
ini_set('display_errors', 1);
error_reporting(E_ALL);

$resultado = null;
$logs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = filter_var($_POST['correo'] ?? '', FILTER_VALIDATE_EMAIL);
    
    if ($correo) {
        // Capturar todos los logs
        ob_start();
        
        $html = plantilla_recuperar_contrasena(
            'Usuario de Prueba',
            'http://localhost/sgrh/public/resetear.php?token=TEST123'
        );
        
        $resultado = enviar_correo($correo, 'Prueba SGRH - Diagnóstico', $html);
        
        $logs = ob_get_clean();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Envío - SGRH</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { 
            max-width: 900px;
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
        input[type="email"] { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            font-size: 14px;
        }
        button { 
            width: 100%;
            padding: 12px; 
            border: none; 
            border-radius: 4px; 
            font-size: 16px; 
            font-weight: 600;
            cursor: pointer;
            background: #667eea; 
            color: white;
        }
        button:hover { 
            background: #764ba2;
        }
        .result { 
            margin-top: 20px; 
            padding: 15px; 
            border-radius: 4px;
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
        .logs {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 4px;
            margin-top: 20px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .config-box {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            font-size: 13px;
        }
        .config-box h3 {
            margin-bottom: 10px;
            color: #667eea;
        }
        .config-item {
            display: flex;
            padding: 5px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .config-label {
            font-weight: 600;
            width: 150px;
            color: #555;
        }
        .config-value {
            color: #333;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico en Tiempo Real</h1>
        <p class="subtitle">Ver logs detallados del envío de correos</p>

        <form method="POST">
            <div class="form-group">
                <label for="correo">Correo de Prueba:</label>
                <input 
                    type="email" 
                    id="correo" 
                    name="correo" 
                    placeholder="tu@email.com" 
                    value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>"
                    required
                >
            </div>
            <button type="submit">🚀 Enviar y Ver Logs</button>
        </form>

        <?php if ($resultado !== null): ?>
        <div class="result <?php echo $resultado ? 'success' : 'error'; ?>">
            <strong><?php echo $resultado ? '✓ Éxito' : '✗ Error'; ?></strong><br>
            <?php if ($resultado): ?>
                El correo se envió correctamente. Revisa tu bandeja de entrada (puede tardar unos segundos).
            <?php else: ?>
                Hubo un error al enviar. Revisa los logs abajo para más detalles.
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($logs): ?>
        <div class="logs">
            <strong>📋 LOGS DETALLADOS:</strong>
            <?php echo htmlspecialchars($logs); ?>
        </div>
        <?php endif; ?>

        <div class="config-box">
            <h3>⚙️ Configuración Actual</h3>
            <div class="config-item">
                <div class="config-label">Servidor SMTP:</div>
                <div class="config-value"><?php echo defined('SMTP_HOST') ? SMTP_HOST : 'No configurado'; ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Puerto:</div>
                <div class="config-value"><?php echo defined('SMTP_PORT') ? SMTP_PORT : 'No configurado'; ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Seguridad:</div>
                <div class="config-value"><?php echo defined('SMTP_SECURE') ? SMTP_SECURE : 'No configurado'; ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Usuario:</div>
                <div class="config-value"><?php echo defined('SMTP_USERNAME') ? SMTP_USERNAME : 'No configurado'; ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Modo:</div>
                <div class="config-value"><?php echo defined('APP_ENV') ? APP_ENV : 'No configurado'; ?></div>
            </div>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; color: #999; font-size: 13px;">
            <strong>💡 Cómo interpretar los logs:</strong><br>
            • Busca "250" = Éxito en SMTP<br>
            • Busca "SMTP ERROR" = Hay un problema de conexión<br>
            • Busca "authentication" = Problema con usuario/contraseña<br>
            • Si no ves logs = PHP no está capturando salida (revisa error_log)
        </div>
    </div>
</body>
</html>
