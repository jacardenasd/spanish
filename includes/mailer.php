<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviar_correo($to, $subject, $html) {
    
    // En DEV: guardar en archivo para depuración Y también enviar
    if (defined('APP_ENV') && APP_ENV === 'dev') {
        $baseDir = realpath(__DIR__ . '/..');
        $dir = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mails';
        
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $safeTo = preg_replace('/[^a-zA-Z0-9_\-\.@]/', '_', (string)$to);
        $file = $dir . DIRECTORY_SEPARATOR . date('Ymd_His') . '__' . $safeTo . '.html';
        
        $content = "<h3>TO: " . htmlspecialchars($to) . "</h3>"
                 . "<h3>SUBJECT: " . htmlspecialchars($subject) . "</h3><hr>"
                 . $html;
        
        $ok = @file_put_contents($file, $content);
        @error_log('[SGRH][mailer] Guardado en: ' . $file);
        
        // También intentar enviar por SMTP en DEV
        return enviar_por_smtp($to, $subject, $html);
    }
    
    // En PROD: enviar por SMTP
    return enviar_por_smtp($to, $subject, $html);
}

function enviar_por_smtp($to, $subject, $html) {
    try {
        $mail = new PHPMailer(true);
        
        // Configuración SMTP para GoDaddy Relay
        // IMPORTANTE: NO usar isSMTP() para GoDaddy
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = false; // GoDaddy Relay NO requiere autenticación
        $mail->SMTPSecure = SMTP_SECURE ?: ''; // Vacío para GoDaddy Relay
        
        // Configuración de lenguaje
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        // Remitente
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        
        // Destinatario
        $mail->addAddress($to);
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = strip_tags($html);
        
        // Debug
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("[SGRH][PHPMailer] $str");
        };
        
        // Enviar
        $result = $mail->send();
        error_log('[SGRH][mailer] Resultado: ' . ($result ? 'ÉXITO' : 'FALLO'));
        return $result;
        
    } catch (Exception $e) {
        @error_log('[SGRH][mailer] Error: ' . $e->getMessage());
        @error_log('[SGRH][mailer] ErrorInfo: ' . ($mail->ErrorInfo ?? 'N/A'));
        return false;
    }
}
