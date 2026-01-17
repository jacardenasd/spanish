╔══════════════════════════════════════════════════════════════════════════════╗
║                                                                              ║
║                   ✨ SISTEMA DE CORREOS COMPLETADO ✨                        ║
║                  Configuración Titan + GoDaddy para SGRH                     ║
║                                                                              ║
╚══════════════════════════════════════════════════════════════════════════════╝


📦 PAQUETES INSTALADOS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  • PHPMailer 6.8+  → Librería profesional para SMTP
  • Composer        → Gestor de dependencias PHP


📁 ESTRUCTURA DE ARCHIVOS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

sgrh/
├── 📄 includes/
│   ├── 🆕 mail_config.php              ← EDITA AQUÍ (credenciales)
│   ├── 🔄 mailer.php                   (reescrito con PHPMailer)
│   └── 🆕 mail_templates.php           (plantillas HTML)
│
├── 📄 public/
│   ├── 🆕 test_mailer.php              (panel de pruebas)
│   ├── 🆕 diagnostico_correos.php      (sistema de diagnóstico)
│   ├── 🆕 ejemplo_uso_correos.php      (ejemplos de código)
│   └── recuperar_contrasena.php        (ya está aquí)
│
├── 📚 DOCUMENTACION/
│   ├── 📋 INICIO_CORREOS.txt                (ESTE ARCHIVO)
│   ├── 📋 CONFIG_CORREOS_TITAN.md          (guía detallada)
│   ├── 📋 RESUMEN_CONFIGURACION_CORREOS.md (cambios realizados)
│   ├── 📋 GUIA_RAPIDA_CORREOS.md           (3 pasos)
│   └── 📋 INTEGRACION_RECUPERACION_CONTRASENA.txt
│
└── 📄 composer.json  (actualizado con PHPMailer)


🎯 QUÉ NECESITAS HACER
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1️⃣  CONFIGURAR CREDENCIALES
    
    📁 Archivo: includes/mail_config.php
    
    Necesitas obtener de GoDaddy:
       • SMTP_HOST      = mail.TUDOMINITO.com
       • SMTP_PORT      = 465
       • SMTP_USERNAME  = admin@TUDOMINITO.com
       • SMTP_PASSWORD  = tu_contraseña_titan


2️⃣  INSTALAR DEPENDENCIAS
    
    🖥️  Terminal:
       cd c:\MAMP\htdocs\sgrh
       composer update
    
    ✓ Esto descargará PHPMailer


3️⃣  PROBAR LA CONFIGURACIÓN
    
    🌐 Navegador:
       http://localhost/sgrh/public/test_mailer.php
    
    ✓ Interfaz para enviar correos de prueba


4️⃣  (OPCIONAL) INTEGRAR EN TU CÓDIGO
    
    📝 Archivo: public/recuperar_contrasena.php
    
    Ver: INTEGRACION_RECUPERACION_CONTRASENA.txt


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🌐 DÓNDE OBTENER DATOS DE GODADDY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Sitio:    https://www.godaddy.com
Sección:  Productos → Email → Correo de Titan
Buscar:   "Configuración SMTP" o "Configuración de servidor"

Datos que verás:
  Host:       mail.TUDOMINITO.com
  Puerto:     465 (SSL) o 587 (STARTTLS)
  Usuario:    admin@TUDOMINITO.com  (es tu correo Titan)
  Contraseña: la que estableciste al crear el correo


⚙️ EJEMPLO DE CONFIGURACIÓN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Archivo: includes/mail_config.php

<?php
define('SMTP_HOST', 'mail.miempresa.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USERNAME', 'admin@miempresa.com');
define('SMTP_PASSWORD', 'MiContraseña123!');
define('MAIL_FROM_ADDRESS', 'admin@miempresa.com');
define('MAIL_FROM_NAME', 'SGRH');
?>


💻 USAR EN TU CÓDIGO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Opción 1: Correo simple

  require_once 'includes/mailer.php';
  
  enviar_correo(
      'usuario@ejemplo.com',
      'Asunto del correo',
      '<p>Contenido en HTML</p>'
  );


Opción 2: Con plantilla (recomendado)

  require_once 'includes/mailer.php';
  require_once 'includes/mail_templates.php';
  
  $enlace = 'http://tudominio.com/resetear.php?token=abc123';
  $html = plantilla_recuperar_contrasena('Juan Pérez', $enlace);
  
  enviar_correo(
      'juan@ejemplo.com',
      'Recupera tu contraseña',
      $html
  );


Opción 3: Confirmación de cambio

  $html = plantilla_contrasena_cambiada('Juan Pérez');
  enviar_correo('juan@ejemplo.com', 'Contraseña actualizada', $html);


🧪 PRUEBA INICIAL
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. Abre en navegador:
   http://localhost/sgrh/public/test_mailer.php

2. Verás formulario con campos:
   • Correo destino (tu email)
   • Nombre del usuario
   • Asunto
   • Tipo de correo (plantilla)

3. Ingresa tu email real y haz clic "Enviar Prueba"

4. Revisa tu bandeja (incluida carpeta SPAM)

5. Si llega: ✅ ¡Todo está funcionando!
   Si no llega: 🔍 Usa diagnostico_correos.php


🔍 DIAGNÓSTICO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Si hay problemas, visita:
  http://localhost/sgrh/public/diagnostico_correos.php

Mostrará:
  ✓ Versión PHP
  ✓ Extensiones instaladas (OpenSSL, Sockets)
  ✓ PHPMailer instalado
  ✓ Credenciales configuradas
  ✓ Permisos de escritura
  ✓ Estado de cada componente


⚠️ ERRORES COMUNES Y SOLUCIONES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

❌ Error: "SMTP connect() failed"
   Solución:
   • Puerto 465 con SSL (predeterminado)
   • O Puerto 587 con STARTTLS
   • Verifica que no haya firewall bloqueando

❌ Error: "Authentication failed"
   Solución:
   • Revisa usuario exacto (ej: admin@miempresa.com)
   • Revisa contraseña sin espacios
   • Si tiene caracteres especiales, enciérrala en comillas

❌ Error: "Class PHPMailer not found"
   Solución:
   cd c:\MAMP\htdocs\sgrh
   composer update

❌ Error: "OpenSSL error"
   Solución:
   • Verifica en php.ini que OpenSSL esté habilitado
   • extension=openssl debe estar sin comentar

❌ El correo no llega (pero no hay error)
   Solución:
   • Revisa carpeta SPAM/Correo no deseado
   • GoDaddy: Configura SPF y DKIM
   • Cambia From address a tu dominio


📊 ARCHIVOS DOCUMENTACIÓN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📋 INICIO_CORREOS.txt
   ↳ Este archivo. Visión general completa.

📋 GUIA_RAPIDA_CORREOS.md
   ↳ 3 pasos para empezar. Lectura de 5 minutos.

📋 CONFIG_CORREOS_TITAN.md
   ↳ Documentación detallada. Contiene todo.

📋 RESUMEN_CONFIGURACION_CORREOS.md
   ↳ Qué cambió. Para referencia.

📋 INTEGRACION_RECUPERACION_CONTRASENA.txt
   ↳ Cómo integrar en tu flujo de recuperación.

📝 public/ejemplo_uso_correos.php
   ↳ Ejemplos de código listos para copiar/pegar.


🔒 SEGURIDAD
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✓ NO guardes contraseña en el código
  Usa: archivo .env o variables de entorno

✓ VALIDA tokens en servidor
  Nunca confíes solo en cliente

✓ EXPIRA tokens rápido
  Recomendado: 1 hora máximo

✓ REGISTRA intentos
  Log de recuperaciones fallidas

✓ USA HTTPS
  Todos los links en correos deben ser https://

✓ LIMITA intentos
  Máximo 3 recuperaciones por hora por usuario


📞 CONTACTO Y SOPORTE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PHPMailer (Documentación):
  https://github.com/PHPMailer/PHPMailer

GoDaddy (Soporte Titan):
  https://www.godaddy.com/help/email

Este proyecto:
  Archivos de documentación en la raíz del proyecto


✨ RESUMEN: TAREA COMPLETADA
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ Sistema de correos configurado
✅ PHPMailer integrado
✅ Plantillas HTML profesionales
✅ Interfaz de prueba lista
✅ Sistema de diagnóstico
✅ Documentación completa

SIGUIENTE PASO:
  1. Edita includes/mail_config.php con tus datos
  2. Ejecuta: composer update
  3. Prueba en: http://localhost/sgrh/public/test_mailer.php


🚀 ¡LISTO PARA USAR!

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Última actualización: 2026-01-16
Versión: 1.0
Estado: ✅ Completado

═══════════════════════════════════════════════════════════════════════════════
