# Configuración de Correos con Titan en GoDaddy

## Resumen

Este documento explica cómo configurar el envío de correos en SGRH usando una cuenta Titan en GoDaddy a través de SMTP.

---

## 📋 Pasos de Configuración

### 1. **Obtener Credenciales de Titan en GoDaddy**

1. Inicia sesión en tu cuenta de GoDaddy: https://www.godaddy.com
2. Dirígete a la sección de **Correo de Titan**
3. Busca los datos de acceso SMTP:
   - **Servidor SMTP**: `mail.tunombre.com` (reemplaza con tu dominio)
   - **Puerto**: 465 (SSL) o 587 (STARTTLS)
   - **Usuario**: Tu correo completo (ej: admin@tunombre.com)
   - **Contraseña**: Tu contraseña de Titan

> **Nota**: GoDaddy también proporciona estos datos en la documentación del producto Titan.

---

### 2. **Actualizar Configuración en SGRH**

Edita el archivo `includes/mail_config.php` con tus credenciales:

```php
define('SMTP_HOST', 'mail.tunombre.com');      // Tu dominio
define('SMTP_PORT', 465);                      // Puerto SSL
define('SMTP_SECURE', 'ssl');                  // Tipo de conexión
define('SMTP_USERNAME', 'admin@tunombre.com'); // Tu correo Titan
define('SMTP_PASSWORD', 'tu_contraseña');      // Tu contraseña Titan
define('MAIL_FROM_ADDRESS', 'admin@tunombre.com');
define('MAIL_FROM_NAME', 'SGRH');
```

---

### 3. **Instalar PHPMailer**

Ejecuta Composer para instalar la librería PHPMailer:

```bash
cd c:\MAMP\htdocs\sgrh
composer update
```

Si no tienes Composer instalado, descárgalo de https://getcomposer.org/

---

### 4. **Prueba de Conexión**

Ejecuta el archivo de prueba:

```
http://localhost/sgrh/public/test_mailer.php
```

Deberías ver:
- En **DEV**: El archivo guardado en `storage/mails/`
- En **PROD**: El correo enviado exitosamente

---

## 🔧 Uso en Recuperación de Contraseña

### Ejemplo 1: Enviar enlace de recuperación

```php
require_once 'includes/mailer.php';
require_once 'includes/mail_templates.php';

// Generar token y guardar en BD
$token = bin2hex(random_bytes(32));

// Construir enlace
$enlace = 'http://tudominio.com/sgrh/public/resetear.php?token=' . $token;

// Obtener plantilla
$html = plantilla_recuperar_contrasena('Juan Pérez', $enlace);

// Enviar
enviar_correo('usuario@ejemplo.com', 'Recupera tu contraseña', $html);
```

### Ejemplo 2: Confirmar cambio de contraseña

```php
$html = plantilla_contrasena_cambiada('Juan Pérez');
enviar_correo('usuario@ejemplo.com', 'Contraseña actualizada', $html);
```

---

## 📧 Plantillas Disponibles

Se incluyen dos plantillas HTML profesionales:

1. **plantilla_recuperar_contrasena()** - Solicitud de restablecimiento
2. **plantilla_contrasena_cambiada()** - Confirmación de cambio exitoso

Puedes crear más en `includes/mail_templates.php`

---

## 🐛 Depuración

### En Modo DEV (desarrollo)

Los correos se guardan en `storage/mails/` como archivos HTML para inspección.

### Revisar logs

Los errores aparecen en:
- `error_log` del servidor
- Consola de PHP (si está configurado)

Para ver logs detallados, editaPHP en `includes/mailer.php`:

```php
$mail->SMTPDebug = 2; // 0=sin debug, 1=errores, 2=detallado
```

---

## ⚠️ Problemas Comunes

### Error: "SMTP connect() failed"
- Verifica que el puerto 465 esté abierto en tu servidor
- Intenta con puerto 587 y STARTTLS en lugar de SSL

### Error: "Authentication failed"
- Revisa que el correo y contraseña sean correctos
- Asegúrate que la contraseña no contiene caracteres especiales sin escapar

### Error: "SSL certificate problem"
- En desarrollo, puedes desactivar la verificación de certificado en mail_config.php
- En producción, asegúrate que OpenSSL esté habilitado en PHP

### El correo no llega a la bandeja
- Revisa la carpeta de SPAM
- Configura SPF y DKIM en GoDaddy para mejorar deliverability

---

## 🔒 Seguridad

### Buenas prácticas:

1. **No guardes contraseñas en el código** - Usa variables de entorno o archivo `.env`
2. **Valida siempre los tokens** - Verifica expiración y unicidad en BD
3. **Usa HTTPS** - Los enlaces en correos deben ser seguros
4. **Rate limiting** - Limita intentos de recuperación por IP/usuario
5. **Logs** - Registra intentos fallidos de recuperación

---

## 📝 Archivos Creados/Modificados

```
includes/
  ├── mail_config.php          (NUEVO - Configuración SMTP)
  ├── mailer.php               (MODIFICADO - Ahora usa PHPMailer)
  └── mail_templates.php       (NUEVO - Plantillas HTML)

public/
  └── ejemplo_uso_correos.php  (NUEVO - Ejemplos de uso)

composer.json                   (MODIFICADO - Agregó PHPMailer)
```

---

## 🚀 Próximos Pasos

1. Configura tus credenciales en `mail_config.php`
2. Ejecuta `composer update`
3. Prueba con `test_mailer.php`
4. Integra el envío de correos en tu lógica de recuperación de contraseña

¿Necesitas ayuda con la integración específica en tu código? 😊
