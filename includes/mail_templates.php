<?php
/**
 * Plantillas de correos para SGRH
 */

function plantilla_recuperar_contrasena($nombre, $enlace_reset) {
    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .content p { line-height: 1.6; color: #333; }
        .button-wrapper { text-align: center; margin: 30px 0; }
        .button { background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold; }
        .button:hover { background: #764ba2; }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #999; font-size: 12px; }
        .code { background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Recupera tu Contraseña</h1>
        </div>
        <div class="content">
            <p>Hola <strong>$nombre</strong>,</p>
            
            <p>Recibimos una solicitud para restablecer tu contraseña. Si no fuiste tú, puedes ignorar este correo.</p>
            
            <p>Para crear una nueva contraseña, haz clic en el botón de abajo:</p>
            
            <div class="button-wrapper">
                <a href="$enlace_reset" class="button">Restablecer Contraseña</a>
            </div>
            
            <p>O copia y pega este enlace en tu navegador:</p>
            <div class="code">$enlace_reset</div>
            
            <div class="warning">
                <strong>⚠ Seguridad:</strong> Este enlace expira en 1 hora. Si no restableces tu contraseña en ese tiempo, deberás solicitar uno nuevo.
            </div>
            
            <p>Si tienes problemas, contacta a nuestro equipo de soporte.</p>
            
            <p>Saludos,<br>El equipo de SGRH</p>
        </div>
        <div class="footer">
            <p>Este correo fue enviado automáticamente por SGRH. Por favor, no respondas a este correo.</p>
            <p>&copy; 2026 Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
HTML;
    return $html;
}

function plantilla_contrasena_cambiada($nombre) {
    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #52c41a 0%, #1890ff 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .content p { line-height: 1.6; color: #333; }
        .success { background: #f6ffed; border-left: 4px solid #52c41a; padding: 10px; margin: 15px 0; }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #999; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✓ Contraseña Actualizada</h1>
        </div>
        <div class="content">
            <p>Hola <strong>$nombre</strong>,</p>
            
            <div class="success">
                Tu contraseña ha sido cambiada exitosamente. Ya puedes acceder con tu nueva contraseña.
            </div>
            
            <p>Si no realizaste este cambio, por favor contacta de inmediato a nuestro equipo de soporte.</p>
            
            <p>Saludos,<br>El equipo de SGRH</p>
        </div>
        <div class="footer">
            <p>Este correo fue enviado automáticamente por SGRH. Por favor, no respondas a este correo.</p>
            <p>&copy; 2026 Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
HTML;
    return $html;
}
