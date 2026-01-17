<?php
/**
 * Diagnóstico de Configuración de Correos
 * Accede a: http://localhost/sgrh/public/diagnostico_correos.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mail_config.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Correos - SGRH</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { 
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
        }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #999; margin-bottom: 30px; }
        .section { margin-bottom: 30px; }
        .section-title { 
            font-size: 18px; 
            font-weight: 600; 
            color: #667eea; 
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .check { 
            display: flex; 
            align-items: center;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
            background: #f9f9f9;
        }
        .check-icon { 
            font-size: 20px; 
            margin-right: 15px; 
            min-width: 30px;
        }
        .check-content { flex: 1; }
        .check-label { font-weight: 500; color: #333; }
        .check-value { 
            color: #666; 
            font-size: 13px; 
            margin-top: 4px;
            font-family: monospace;
        }
        .status-ok { background: #f6ffed; border-left: 4px solid #52c41a; }
        .status-ok .check-icon { color: #52c41a; }
        .status-warning { background: #fffbe6; border-left: 4px solid #faad14; }
        .status-warning .check-icon { color: #faad14; }
        .status-error { background: #fff1f0; border-left: 4px solid #ff4d4f; }
        .status-error .check-icon { color: #ff4d4f; }
        .config-box {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            font-size: 13px;
            font-family: monospace;
            border-left: 4px solid #667eea;
        }
        .code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
        .info { color: #666; font-size: 13px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #f5f5f5; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico de Configuración de Correos</h1>
        <p class="subtitle">Sistema SGRH - Validación de Configuración SMTP</p>

        <?php
        // Recopilar información
        $checks = [];

        // 1. PHP Version
        $php_version = phpversion();
        $checks['php_version'] = [
            'label' => 'Versión de PHP',
            'value' => $php_version,
            'status' => version_compare($php_version, '7.4.0', '>=') ? 'ok' : 'error',
            'message' => version_compare($php_version, '7.4.0', '>=') 
                ? 'Compatible con PHPMailer' 
                : 'Se recomienda PHP 7.4 o superior'
        ];

        // 2. OpenSSL
        $has_openssl = extension_loaded('openssl');
        $checks['openssl'] = [
            'label' => 'Extensión OpenSSL',
            'value' => $has_openssl ? 'Instalada' : 'NO INSTALADA',
            'status' => $has_openssl ? 'ok' : 'error',
            'message' => $has_openssl 
                ? 'Necesaria para SMTP seguro (SSL/TLS)' 
                : 'Crítico: Se requiere OpenSSL habilitado'
        ];

        // 3. Sockets
        $has_sockets = extension_loaded('sockets');
        $checks['sockets'] = [
            'label' => 'Extensión Sockets',
            'value' => $has_sockets ? 'Instalada' : 'NO INSTALADA',
            'status' => $has_sockets ? 'ok' : 'warning',
            'message' => $has_sockets 
                ? 'Requerida para conexión SMTP' 
                : 'Advertencia: PHPMailer intenta usarla'
        ];

        // 4. PHPMailer
        $has_phpmailer = file_exists(__DIR__ . '/../vendor/autoload.php') && 
                        file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php');
        $checks['phpmailer'] = [
            'label' => 'PHPMailer instalado',
            'value' => $has_phpmailer ? 'Sí' : 'No',
            'status' => $has_phpmailer ? 'ok' : 'error',
            'message' => $has_phpmailer 
                ? 'Librería lista para usar' 
                : 'Ejecuta: composer update'
        ];

        // 5. mail_config.php
        $has_mail_config = file_exists(__DIR__ . '/../includes/mail_config.php');
        $checks['mail_config'] = [
            'label' => 'Archivo mail_config.php',
            'value' => $has_mail_config ? 'Existe' : 'NO EXISTE',
            'status' => $has_mail_config ? 'ok' : 'error',
            'message' => $has_mail_config 
                ? 'Archivo de configuración encontrado' 
                : 'Archivo no encontrado'
        ];

        // 6. Configuración SMTP
        $smtp_configured = defined('SMTP_HOST') && defined('SMTP_USERNAME') && defined('SMTP_PASSWORD');
        $checks['smtp_configured'] = [
            'label' => 'SMTP configurado',
            'value' => $smtp_configured ? 'Sí' : 'No',
            'status' => $smtp_configured ? 'ok' : 'warning',
            'message' => $smtp_configured 
                ? 'Credenciales definidas' 
                : 'Revisa mail_config.php'
        ];

        // 7. Permiso de escritura en storage
        $storage_path = realpath(__DIR__ . '/../storage/mails');
        $writable = is_writable(__DIR__ . '/../storage') || @mkdir(__DIR__ . '/../storage/mails', 0777, true);
        $checks['storage_writable'] = [
            'label' => 'Permiso de escritura (storage/mails)',
            'value' => $writable ? 'Sí' : 'No',
            'status' => $writable ? 'ok' : 'warning',
            'message' => $writable 
                ? 'Los correos se pueden guardar en DEV' 
                : 'Verifica permisos del directorio'
        ];

        // 8. Envío por mail()
        $mail_enabled = ini_get('sendmail_path') || ini_get('SMTP');
        $checks['mail_function'] = [
            'label' => 'Función mail() disponible',
            'value' => $mail_enabled ? 'Sí' : 'No',
            'status' => 'warning',
            'message' => $mail_enabled 
                ? 'Disponible pero se usa SMTP en su lugar' 
                : 'No disponible (usaremos SMTP)'
        ];

        // Mostrar checks
        foreach ($checks as $key => $check):
            $status_class = 'status-' . $check['status'];
        ?>
        <div class="check <?php echo $status_class; ?>">
            <div class="check-icon">
                <?php 
                if ($check['status'] === 'ok') echo '✓';
                elseif ($check['status'] === 'warning') echo '⚠';
                else echo '✗';
                ?>
            </div>
            <div class="check-content">
                <div class="check-label"><?php echo $check['label']; ?></div>
                <div class="check-value"><?php echo $check['value']; ?> — <?php echo $check['message']; ?></div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Configuración Actual -->
        <div class="section">
            <div class="section-title">⚙️ Configuración SMTP Actual</div>
            
            <?php if (defined('SMTP_HOST')): ?>
            <table>
                <tr>
                    <th>Parámetro</th>
                    <th>Valor</th>
                    <th>Estado</th>
                </tr>
                <tr>
                    <td><span class="code">SMTP_HOST</span></td>
                    <td><?php echo SMTP_HOST; ?></td>
                    <td><?php echo (SMTP_HOST === 'mail.tunombre.com') ? '⚠ Valor por defecto' : '✓ Configurado'; ?></td>
                </tr>
                <tr>
                    <td><span class="code">SMTP_PORT</span></td>
                    <td><?php echo SMTP_PORT; ?></td>
                    <td><?php echo (in_array(SMTP_PORT, [465, 587])) ? '✓ Válido' : '✗ Revisar'; ?></td>
                </tr>
                <tr>
                    <td><span class="code">SMTP_SECURE</span></td>
                    <td><?php echo SMTP_SECURE; ?></td>
                    <td><?php echo (in_array(SMTP_SECURE, ['ssl', 'tls', false])) ? '✓ Válido' : '✗ Revisar'; ?></td>
                </tr>
                <tr>
                    <td><span class="code">SMTP_USERNAME</span></td>
                    <td><?php echo (strlen(SMTP_USERNAME) > 20) ? substr(SMTP_USERNAME, 0, 20) . '...' : SMTP_USERNAME; ?></td>
                    <td><?php echo (SMTP_USERNAME === 'tu_correo@tunombre.com') ? '⚠ Valor por defecto' : '✓ Configurado'; ?></td>
                </tr>
                <tr>
                    <td><span class="code">MAIL_FROM_ADDRESS</span></td>
                    <td><?php echo MAIL_FROM_ADDRESS; ?></td>
                    <td><?php echo (MAIL_FROM_ADDRESS !== 'tu_correo@tunombre.com') ? '✓ Configurado' : '⚠ Por defecto'; ?></td>
                </tr>
                <tr>
                    <td><span class="code">MAIL_FROM_NAME</span></td>
                    <td><?php echo MAIL_FROM_NAME; ?></td>
                    <td>✓ OK</td>
                </tr>
            </table>
            <?php else: ?>
            <div class="check status-error">
                <div class="check-icon">✗</div>
                <div class="check-content">
                    <div class="check-label">Configuración SMTP no encontrada</div>
                    <div class="check-value">Revisa que mail_config.php esté correctamente incluido en includes/config.php</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Entorno -->
        <div class="section">
            <div class="section-title">🌍 Entorno</div>
            <div class="check status-ok">
                <div class="check-icon">ℹ</div>
                <div class="check-content">
                    <div class="check-label">Modo actual</div>
                    <div class="check-value">
                        <?php 
                        if (defined('APP_ENV') && APP_ENV === 'dev') {
                            echo 'DESARROLLO (Los correos se guardan en storage/mails/)';
                        } else {
                            echo 'PRODUCCIÓN (Los correos se envían por SMTP)';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Próximos pasos -->
        <div class="section">
            <div class="section-title">📋 Próximos Pasos</div>
            <div class="info">
                <strong>1. Instalar PHPMailer:</strong><br>
                Ejecuta en terminal: <span class="code">composer update</span><br><br>

                <strong>2. Configurar credenciales:</strong><br>
                Edita <span class="code">includes/mail_config.php</span> con tus datos de Titan en GoDaddy:<br>
                • SMTP_HOST: mail.TU_DOMINIO.com<br>
                • SMTP_USERNAME: tu_correo@TU_DOMINIO.com<br>
                • SMTP_PASSWORD: tu_contraseña<br><br>

                <strong>3. Probar conexión:</strong><br>
                Accede a <span class="code">public/test_mailer.php</span> y envía un correo de prueba<br><br>

                <strong>4. Revisar logs:</strong><br>
                Los errores aparecerán en <span class="code">storage/mails/</span> (en DEV) o en error_log (en PROD)
            </div>
        </div>

        <!-- Troubleshooting -->
        <div class="section">
            <div class="section-title">🔧 Solución de Problemas</div>
            <table>
                <tr>
                    <th>Problema</th>
                    <th>Solución</th>
                </tr>
                <tr>
                    <td>SMTP connect() failed</td>
                    <td>Verifica puerto 465, intenta 587 con STARTTLS</td>
                </tr>
                <tr>
                    <td>Authentication failed</td>
                    <td>Revisa usuario/contraseña, elimina espacios</td>
                </tr>
                <tr>
                    <td>No se abre test_mailer.php</td>
                    <td>Revisa que PHPMailer esté instalado (composer update)</td>
                </tr>
                <tr>
                    <td>El correo no llega</td>
                    <td>Revisa SPAM, configura SPF/DKIM en GoDaddy</td>
                </tr>
            </table>
        </div>

        <div class="info" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
            <strong>Última verificación:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
            <strong>Sistema:</strong> <?php echo php_uname('s'); ?><br>
            <strong>Servidor:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido'; ?>
        </div>
    </div>
</body>
</html>
