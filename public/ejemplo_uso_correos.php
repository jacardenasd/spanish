<?php
/**
 * Ejemplo: Usar el sistema de correos para recuperación de contraseña
 * 
 * Este es un ejemplo básico. Adapta según tu estructura de BD y seguridad.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/mail_templates.php';

// Ejemplo de uso:
// 1. Usuario solicita recuperar contraseña
// 2. Generar token de recuperación
// 3. Guardar token en BD con expiración
// 4. Enviar correo con enlace

function enviar_link_recuperacion($correo_usuario, $nombre_usuario) {
    
    // Generar token único (debe almacenarse en BD)
    $token = bin2hex(random_bytes(32));
    $expiracion = date('Y-m-d H:i:s', time() + 3600); // 1 hora
    
    // IMPORTANTE: Guardar en BD
    // UPDATE usuarios SET reset_token = '$token', reset_expira = '$expiracion' 
    // WHERE correo = '$correo_usuario'
    
    // Construir enlace de restablecimiento
    $enlace = defined('APP_URL') 
        ? APP_URL . "public/resetear.php?token=" . $token
        : "http://tudominio.com/sgrh/public/resetear.php?token=" . $token;
    
    // Obtener plantilla
    $html = plantilla_recuperar_contrasena($nombre_usuario, $enlace);
    
    // Enviar correo
    $resultado = enviar_correo(
        $correo_usuario,
        'Recupera tu contraseña - SGRH',
        $html
    );
    
    return [
        'exito' => $resultado,
        'token' => $token,
        'expira' => $expiracion
    ];
}

// Ejemplo de confirmación después de resetear
function enviar_confirmacion_cambio($correo_usuario, $nombre_usuario) {
    $html = plantilla_contrasena_cambiada($nombre_usuario);
    
    return enviar_correo(
        $correo_usuario,
        'Tu contraseña ha sido actualizada - SGRH',
        $html
    );
}

// PRUEBA (descomenta para probar)
// $resultado = enviar_link_recuperacion('usuario@ejemplo.com', 'Juan Pérez');
// echo "Envío " . ($resultado['exito'] ? 'exitoso' : 'fallido');
// echo "<br>Token: " . $resultado['token'];
// echo "<br>Expira: " . $resultado['expira'];
