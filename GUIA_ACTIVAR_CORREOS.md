# 📧 Guía Rápida: Activar Envío de Correos en CRON

## Estado Actual
✅ **Correos DESACTIVADOS** - Las notificaciones se registran solo en la base de datos

## Cuándo activar los correos
- Cuando tengas configurado un servidor SMTP
- Cuando quieras que los jefes reciban emails automáticos
- Para ambientes de producción con correo corporativo

---

## Método 1: Gmail/Outlook (Recomendado para pruebas)

### Paso 1: Obtener credenciales de aplicación

**Gmail:**
1. Ir a https://myaccount.google.com/security
2. Activar "Verificación en 2 pasos"
3. Generar "Contraseña de aplicación"
4. Copiar la contraseña generada (16 caracteres)

**Outlook/Office365:**
1. Usar tu email y contraseña normal
2. Servidor: `smtp.office365.com`
3. Puerto: 587

### Paso 2: Instalar PHPMailer

```bash
cd C:\MAMP\htdocs\sgrh
composer require phpmailer/phpmailer
```

### Paso 3: Configurar credenciales

Editar `cron_contratos_temporales.php` líneas 266-273:

```php
// CONFIGURACIÓN GMAIL
$mail->Host = 'smtp.gmail.com';
$mail->Username = 'tu_email@gmail.com';
$mail->Password = 'abcd efgh ijkl mnop'; // Contraseña de aplicación
$mail->Port = 587;

// O CONFIGURACIÓN OUTLOOK
$mail->Host = 'smtp.office365.com';
$mail->Username = 'tu_email@empresa.com';
$mail->Password = 'tu_contraseña_normal';
$mail->Port = 587;
```

### Paso 4: Activar envío

Editar líneas 28-29:

```php
define('ENVIAR_CORREOS_REALES', true);  // Cambiar a true
define('METODO_CORREO', 'phpmailer');    // Cambiar a phpmailer
```

### Paso 5: Probar

```bash
# Ejecutar manualmente
curl http://localhost/sgrh/cron_contratos_temporales.php

# Revisar log
cat storage/logs/cron_contratos_2026-01.log
```

---

## Método 2: Servidor SMTP Corporativo

### Configuración típica

```php
$mail->Host = 'mail.empresa.com';
$mail->Username = 'sgrh@empresa.com';
$mail->Password = 'contraseña_segura';
$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587; // o 465 para SSL
```

### Activar

```php
define('ENVIAR_CORREOS_REALES', true);
define('METODO_CORREO', 'phpmailer');
```

---

## Método 3: mail() nativo (Solo si el servidor lo soporta)

### Verificar si funciona

```bash
php -r "mail('tu_email@test.com', 'Test', 'Prueba'); echo 'Enviado';"
```

### Activar

```php
define('ENVIAR_CORREOS_REALES', true);
define('METODO_CORREO', 'mail_nativo');
```

**Nota**: Este método suele fallar en MAMP/XAMPP. Usar PHPMailer en su lugar.

---

## Modo Híbrido (BD + Email)

Puedes mantener ambos: registrar en BD Y enviar correo.

En la función `enviarPorPHPMailer()` o `enviarPorMailNativo()`, el sistema automáticamente registra en BD si falla el envío de correo.

---

## Desactivar correos temporalmente

```php
define('ENVIAR_CORREOS_REALES', false); // Volver a false
```

Las notificaciones seguirán registrándose en la BD.

---

## Troubleshooting

### Error: "could not authenticate"
- Verificar usuario/contraseña
- Gmail: usar contraseña de aplicación, no contraseña normal
- Outlook: permitir "aplicaciones menos seguras"

### Error: "Connection refused"
- Verificar puerto (587 o 465)
- Verificar firewall del servidor
- Probar con `telnet smtp.gmail.com 587`

### Error: "PHPMailer not found"
- Instalar: `composer require phpmailer/phpmailer`
- Verificar que existe: `vendor/phpmailer/phpmailer/src/PHPMailer.php`

### Los correos llegan a spam
- Configurar SPF/DKIM en tu dominio
- Usar un dominio real, no localhost
- Agregar remitente a lista segura

### Ver errores detallados

Agregar en `enviarPorPHPMailer()`:

```php
$mail->SMTPDebug = 2; // 0=off, 1=client, 2=client+server
$mail->Debugoutput = function($str) {
    escribirLog("    DEBUG SMTP: " . $str);
};
```

---

## Plantilla de correo HTML (Opcional)

Para correos más profesionales, cambiar:

```php
$mail->isHTML(true);
$mail->Subject = $asunto;
$mail->Body = "
<html>
<body style='font-family: Arial, sans-serif;'>
    <div style='background: #f4f4f4; padding: 20px;'>
        <div style='background: white; padding: 30px; border-radius: 5px;'>
            <h2 style='color: #d9534f;'>⚠️ Contrato próximo a vencer</h2>
            <p>Estimado/a {$nombreJefe},</p>
            <p>El contrato del empleado <strong>{$nombreEmpleado}</strong> vence el <strong>{$fecha_fin}</strong>.</p>
            <a href='{$url}' style='display: inline-block; padding: 12px 24px; background: #5cb85c; color: white; text-decoration: none; border-radius: 4px;'>
                Ver en el Sistema
            </a>
        </div>
    </div>
</body>
</html>
";
$mail->AltBody = $mensaje; // Versión texto plano
```

---

## Recordatorio

**Antes de activar en producción:**
- [ ] Probar con 1-2 correos de prueba
- [ ] Verificar que los correos lleguen (revisar spam)
- [ ] Confirmar que el jefe correcto recibe la notificación
- [ ] Documentar las credenciales SMTP en lugar seguro
- [ ] Configurar límite de envíos si usas Gmail (500/día)

**Después de activar:**
- [ ] Monitorear logs durante la primera semana
- [ ] Revisar que `notificacion_enviada = 1` en tabla contratos
- [ ] Solicitar feedback de los jefes inmediatos
