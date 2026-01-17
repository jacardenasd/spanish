# 📧 Configuración Completada: Correos con Titan en GoDaddy

## ✅ Resumen de Cambios

Se han realizado las siguientes configuraciones para habilitar el envío de correos en tu sistema SGRH usando Titan en GoDaddy:

---

## 📁 Archivos Creados

### 1. **`includes/mail_config.php`** (NUEVO)
- Archivo de configuración SMTP
- Define credenciales de Titan
- Configurable para diferentes entornos

**Contenido:**
```php
define('SMTP_HOST', 'mail.tunombre.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'tu_correo@tunombre.com');
define('SMTP_PASSWORD', 'tu_contraseña');
```

### 2. **`includes/mail_templates.php`** (NUEVO)
- Plantillas HTML profesionales
- Funciones reutilizables para correos
- 2 plantillas incluidas:
  - `plantilla_recuperar_contrasena()`
  - `plantilla_contrasena_cambiada()`

### 3. **`public/test_mailer.php`** (MEJORADO)
- Interfaz visual para probar envíos
- Interfaz amigable y profesional
- Soporte para múltiples tipos de correos

### 4. **`public/ejemplo_uso_correos.php`** (NUEVO)
- Ejemplos de código para usar el sistema
- Guías de integración
- Funciones auxiliares

### 5. **`CONFIG_CORREOS_TITAN.md`** (NUEVO)
- Documentación completa
- Instrucciones paso a paso
- Solución de problemas

---

## 📝 Archivos Modificados

### **`composer.json`**
Agregada librería PHPMailer:
```json
"phpmailer/phpmailer": "^6.8"
```

### **`includes/mailer.php`**
Reescrito para usar PHPMailer en lugar de `mail()`:
- Soporte para SMTP
- Mejor manejo de errores
- Logging mejorado
- Modo DEV y PROD

---

## 🚀 Pasos para Activar

### 1. Instalar PHPMailer
```bash
cd c:\MAMP\htdocs\sgrh
composer update
```

### 2. Configurar Credenciales
Edita `includes/mail_config.php`:

```php
<?php
// Tu dominio de Titan
define('SMTP_HOST', 'mail.TUDOMINITO.com'); // ← CAMBIAR

// Credenciales
define('SMTP_USERNAME', 'admin@TUDOMINITO.com'); // ← CAMBIAR
define('SMTP_PASSWORD', 'tu_contraseña_aqui'); // ← CAMBIAR

define('MAIL_FROM_ADDRESS', 'admin@TUDOMINITO.com'); // ← CAMBIAR
define('MAIL_FROM_NAME', 'SGRH');
?>
```

### 3. Obtener Configuración de GoDaddy

**Donde encontrar los datos:**

1. Accede a: https://www.godaddy.com
2. Busca sección **"Correo"** o **"Email"**
3. Selecciona tu dominio
4. Busca **"Configuración SMTP"** o **"Configuración de servidor"**

**Datos que necesitas:**
- Servidor: `mail.tunombre.com`
- Puerto: 465 (SSL) o 587 (STARTTLS)
- Usuario: Tu email completo
- Contraseña: Tu contraseña Titan

---

## ✅ Verificar Configuración

### Accede a la página de prueba:
```
http://localhost/sgrh/public/test_mailer.php
```

### Prueba 1: Correo Simple
1. Ingresa tu email
2. Ingresa tu nombre
3. Haz clic en "🚀 Enviar Prueba"
4. Verifica el resultado

### Prueba 2: Ver Plantilla
1. Selecciona el tipo de correo
2. Haz clic en "📋 Ver Plantilla"
3. El archivo se guardará en `storage/mails/`

---

## 💻 Uso en Tu Código

### Envío Simple
```php
require_once 'includes/mailer.php';

enviar_correo(
    'usuario@ejemplo.com',
    'Asunto del correo',
    '<p>Contenido HTML</p>'
);
```

### Con Plantilla
```php
require_once 'includes/mailer.php';
require_once 'includes/mail_templates.php';

$enlace = 'http://tudominio.com/public/resetear.php?token=abc123';
$html = plantilla_recuperar_contrasena('Juan Pérez', $enlace);

enviar_correo('usuario@ejemplo.com', 'Recupera tu contraseña', $html);
```

---

## 🔐 Seguridad

### Variables de Entorno (Recomendado)
Para mayor seguridad, usa archivo `.env`:

1. Crea `.env` en la raíz del proyecto:
```
MAIL_HOST=mail.tunombre.com
MAIL_USERNAME=admin@tunombre.com
MAIL_PASSWORD=tu_contraseña
```

2. Actualiza `mail_config.php`:
```php
define('SMTP_HOST', $_ENV['MAIL_HOST'] ?? 'mail.tunombre.com');
define('SMTP_USERNAME', $_ENV['MAIL_USERNAME'] ?? '');
define('SMTP_PASSWORD', $_ENV['MAIL_PASSWORD'] ?? '');
```

3. Agrega `.env` a `.gitignore`:
```
.env
```

---

## 🐛 Si Hay Problemas

### Error: "SMTP connect() failed"
- Verifica puerto 465 abierto
- Intenta puerto 587 en `mail_config.php`

### Error: "Authentication failed"
- Revisa usuario/contraseña
- Verifica que no haya espacios en blanco

### El correo no llega
- Revisa carpeta SPAM
- Configura SPF/DKIM en GoDaddy
- Verifica que el `From` sea válido

### Ver logs detallados
En `includes/mailer.php`, cambia:
```php
$mail->SMTPDebug = 2; // 0=sin debug, 1=errores, 2=detallado
```

---

## 📂 Estructura Final

```
sgrh/
├── includes/
│   ├── config.php              (original)
│   ├── mail_config.php         ✨ NUEVO
│   ├── mailer.php              ✨ MODIFICADO
│   ├── mail_templates.php      ✨ NUEVO
│   └── guard.php               (original)
├── public/
│   ├── test_mailer.php         ✨ MEJORADO
│   ├── ejemplo_uso_correos.php ✨ NUEVO
│   └── ...
├── storage/
│   └── mails/                  (almacena correos en DEV)
├── composer.json               ✨ ACTUALIZADO
├── CONFIG_CORREOS_TITAN.md     ✨ NUEVO (este archivo)
└── ...
```

---

## 📚 Documentación Adicional

- **Documentación oficial PHPMailer**: https://github.com/PHPMailer/PHPMailer
- **Documentación Titan GoDaddy**: https://www.godaddy.com/help/email
- **RFC 5321 (SMTP)**: https://tools.ietf.org/html/rfc5321

---

## 🎯 Próximos Pasos

1. ✅ Instalar dependencias con `composer update`
2. ✅ Configurar `mail_config.php` con tus credenciales
3. ✅ Probar en `test_mailer.php`
4. ✅ Integrar en tu flujo de recuperación de contraseña
5. ✅ Cambiar `APP_ENV` a `prod` en `includes/config.php` cuando esté listo

---

**Última actualización**: 2026-01-16  
**Versión**: 1.0  
**Estado**: ✅ Listo para usar

¿Necesitas ayuda con la configuración específica?
