<?php
/**
 * Configuración de envío de correos con PHPMailer
 * Para: Office 365 / Microsoft 365
 */

// Configuración SMTP para GoDaddy Relay
define('MAIL_DRIVER', 'smtp'); // 'smtp' o 'mail'

// SMTP (GoDaddy Relay - compatible con Office 365)
define('SMTP_HOST', 'smtpout.secureserver.net'); // Relay SMTP de GoDaddy
define('SMTP_PORT', 465); // Puerto sin encriptación
define('SMTP_SECURE', ''); // Sin TLS/SSL

define('SMTP_USERNAME', 'contacto@rhfarma.mx'); // Tu correo de Office 365
define('SMTP_PASSWORD', 'Card3n4x!Mx2025'); // Contraseña de Office 365

// Información del remitente
define('MAIL_FROM_ADDRESS', 'contacto@rhfarma.mx');
define('MAIL_FROM_NAME', 'SGRH - Sistema de Gestión de Recursos Humanos');

// Información para contacto (pueden ser diferentes del remitente)
define('MAIL_ADMIN_ADDRESS', 'contacto@rhfarma.mx');