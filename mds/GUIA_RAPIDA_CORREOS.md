# 🚀 GUÍA RÁPIDA: Configurar Correos con Titan en GoDaddy

## 3 Pasos Simples

### 1️⃣ Obtén Credenciales de GoDaddy
```
URL: https://www.godaddy.com
Sección: Correo → Tu dominio → Configuración SMTP

Apunta estos datos:
• Host: mail.TUDOMINITO.com
• Puerto: 465 (SSL)
• Usuario: admin@TUDOMINITO.com
• Contraseña: xxxxxxxx
```

### 2️⃣ Configura en SGRH
**Archivo:** `includes/mail_config.php`

```php
<?php
define('SMTP_HOST', 'mail.TUDOMINITO.com');        // ← CAMBIAR
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USERNAME', 'admin@TUDOMINITO.com');   // ← CAMBIAR
define('SMTP_PASSWORD', 'tu_contraseña');          // ← CAMBIAR
define('MAIL_FROM_ADDRESS', 'admin@TUDOMINITO.com');// ← CAMBIAR
define('MAIL_FROM_NAME', 'SGRH');
?>
```

### 3️⃣ Instala PHPMailer
```bash
cd c:\MAMP\htdocs\sgrh
composer update
```

---

## ✅ Verificar que Funciona

1. Abre: `http://localhost/sgrh/public/test_mailer.php`
2. Ingresa tu email
3. Haz clic en "Enviar Prueba"
4. Verifica tu bandeja de entrada

---

## 📧 Usar en Tu Código

### Correo Sencillo
```php
require_once 'includes/mailer.php';

enviar_correo(
    'usuario@ejemplo.com',
    'Recupera tu contraseña',
    '<p>Haz clic <a href="#">aquí</a> para resetear</p>'
);
```

### Con Plantilla
```php
require_once 'includes/mailer.php';
require_once 'includes/mail_templates.php';

$enlace = 'http://tudominio.com/resetear.php?token=abc123';
$html = plantilla_recuperar_contrasena('Juan', $enlace);

enviar_correo('juan@ejemplo.com', 'Recuperar contraseña', $html);
```

---

## 🔍 Diagnóstico

¿Algo no funciona? Accede a:
```
http://localhost/sgrh/public/diagnostico_correos.php
```

Mostrará:
✓ Si PHP está correctamente configurado
✓ Si PHPMailer está instalado
✓ Si OpenSSL está habilitado
✓ Si tus credenciales SMTP son válidas

---

## 📁 Archivos Principales

| Archivo | Propósito |
|---------|-----------|
| `includes/mail_config.php` | Configuración SMTP (EDITA ESTO) |
| `includes/mailer.php` | Función para enviar correos |
| `includes/mail_templates.php` | Plantillas HTML profesionales |
| `public/test_mailer.php` | Interfaz para probar envíos |
| `public/diagnostico_correos.php` | Verificar configuración |

---

## ⚠️ Problemas Comunes

**Error: "SMTP connect() failed"**
- Intenta puerto 587 en lugar de 465
- Verifica que tu IP no esté bloqueada

**Error: "Authentication failed"**
- Revisa que el usuario y contraseña sean exactos
- Sin espacios al principio o final

**El correo no llega**
- Revisa carpeta de SPAM
- Configura SPF/DKIM en GoDaddy

---

## 📞 Soporte

Documentación detallada en:
- `CONFIG_CORREOS_TITAN.md` - Guía completa
- `RESUMEN_CONFIGURACION_CORREOS.md` - Cambios realizados

¡Listo! Tu sistema SGRH puede enviar correos ahora. 🎉
