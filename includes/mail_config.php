<?php
/**
 * Configuración de envío de correos con PHPMailer
 * Para: GoDaddy - Relay SMTP sin autenticación
 * Basado en configuración probada y funcional
 */

// Configuración SMTP para GoDaddy Relay
define('MAIL_DRIVER', 'smtp');

// SMTP (GoDaddy Relay) - Configuración probada y funcional
define('SMTP_HOST', 'smtpout.secureserver.net'); // Servidor SMTP de GoDaddy
define('SMTP_PORT', 465); // Puerto SSL
define('SMTP_SECURE', ''); // Sin TLS/SSL (relay sin encriptación)

// IMPORTANTE: SMTPAuth debe ser FALSE para GoDaddy Relay
define('SMTP_USERNAME', 'contacto@rhfarma.mx'); // Se incluye pero no se usa
define('SMTP_PASSWORD', 'Card3n4x!Mx2025'); // Se incluye pero no se usa

// Información del remitente
define('MAIL_FROM_ADDRESS', 'contacto@rhfarma.mx');
define('MAIL_FROM_NAME', 'SGRH - Sistema de Gestión de Recursos Humanos');

// Información para contacto (pueden ser diferentes del remitente)
define('MAIL_ADMIN_ADDRESS', 'contacto@rhfarma.mx');