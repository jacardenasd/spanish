# Configuración del CRON para Gestión de Contratos Temporales

## 📋 Descripción
Este script automatiza la gestión de contratos temporales y empleados nuevos.

## 🔄 Funcionalidades

### 1. Sincronización de Empleados Nuevos
- Detecta empleados en tabla `empleados` sin registro en `empleados_demograficos`
- Crea automáticamente el registro demográfico con datos básicos
- Solo procesa empleados activos con menos de 30 días de antigüedad

### 2. Notificación de Contratos por Vencer
- Busca contratos temporales que vencen exactamente en 5 días
- Envía notificación al jefe inmediato (campo `jefe_no_emp` en demograficos)
- Actualiza estatus del contrato a `por_vencer`
- Marca la notificación como enviada para evitar duplicados

### 3. Finalización de Contratos Vencidos
- Actualiza automáticamente contratos vencidos a estatus `finalizado`
- Solo afecta contratos temporales pasada su fecha de término

### 4. Registro de Actividad
- Log mensual en `/storage/logs/cron_contratos_YYYY-MM.log`
- Estadísticas de contratos activos por tipo y estatus

## 🚀 Instalación

### Paso 1: Crear tabla de notificaciones
```bash
mysql -u root -p sgrh < migrations/create_notificaciones.sql
```

### Paso 2: Dar permisos de ejecución
```bash
chmod +x cron_contratos_temporales.php
```

### Paso 3: Configurar CRON en el servidor

#### Windows (MAMP - Programador de Tareas)

**IMPORTANTE**: En MAMP, es más fácil ejecutar el CRON vía HTTP usando curl o wget.

1. Abrir "Programador de tareas" (Task Scheduler)
2. Crear tarea básica:
   - Nombre: SGRH Contratos Temporales
   - Desencadenador: Diariamente a las 8:00 AM
   - Acción: Iniciar programa
   - Programa: `C:\Windows\System32\curl.exe`
   - Argumentos: `-s http://localhost/sgrh/cron_contratos_temporales.php`
   - Iniciar en: `C:\MAMP\htdocs\sgrh`

**Alternativa usando script BAT**:
- Programa: `C:\MAMP\htdocs\sgrh\ejecutar_cron.bat`
- (Sin argumentos)

#### Linux/macOS
```bash
crontab -e
```

Agregar línea:
```bash
# Ejecutar todos los días a las 8:00 AM
0 8 * * * /usr/bin/php /ruta/completa/sgrh/cron_contratos_temporales.php >> /ruta/completa/sgrh/storage/logs/cron_output.log 2>&1
```

#### cPanel (Hosting compartido)
1. Ir a "Tareas Cron" (Cron Jobs)
2. Configurar:
   - Minuto: 0
   - Hora: 8
   - Día: *
   - Mes: *
   - Día semana: *
   - Comando: `/usr/bin/php /home/usuario/public_html/sgrh/cron_contratos_temporales.php`

## ✉️ Configuración de Email

**IMPORTANTE**: Por defecto, el envío de correos está **DESACTIVADO**. Las notificaciones se registran en la tabla `notificaciones` de la base de datos.

### Activar envío de correos

Editar las primeras líneas de configuración en `cron_contratos_temporales.php`:

```php
// Cambiar false a true para activar envío real de correos
define('ENVIAR_CORREOS_REALES', false);

// Seleccionar método: 'mail_nativo', 'phpmailer', 'notificaciones_bd'
define('METODO_CORREO', 'notificaciones_bd');
```

### Opción 1: mail() nativo de PHP

Más simple pero menos confiable. Requiere que el servidor tenga configurado sendmail/postfix.

```php
define('ENVIAR_CORREOS_REALES', true);
define('METODO_CORREO', 'mail_nativo');
```

### Opción 2: PHPMailer (SMTP - Recomendado)

Más profesional y confiable. Funciona con Gmail, Outlook, etc.

**Instalar PHPMailer:**
```bash
composer require phpmailer/phpmailer
```

**Configurar credenciales** en la función `enviarPorPHPMailer()`:
```php
$mail->Host = 'smtp.gmail.com';
$mail->Username = 'tu_email@gmail.com';
$mail->Password = 'tu_password_app'; // Usar contraseña de aplicación
$mail->Port = 587;
```

**Activar:**
```php
define('ENVIAR_CORREOS_REALES', true);
define('METODO_CORREO', 'phpmailer');
```

### Opción 3: Notificaciones internas (Actual)

Las notificaciones se guardan en la tabla `notificaciones` y se pueden mostrar en un panel dentro del sistema.

```php
define('ENVIAR_CORREOS_REALES', false); // o true con 'notificaciones_bd'
define('METODO_CORREO', 'notificaciones_bd');
```

**Ventajas**:
- No depende de configuración SMTP
- Historial completo de notificaciones
- Control de lectura/no lectura
- Puede combinarse con correos reales

## 🧪 Probar el CRON manualmente

### Windows (MAMP) - Método HTTP (Recomendado)
```bash
# PowerShell
curl http://localhost/sgrh/cron_contratos_temporales.php

# O ejecutar el script BAT
.\ejecutar_cron.bat
```

### Windows (MAMP) - Método CLI (Requiere PDO MySQL en PHP)
```bash
cd C:\MAMP\htdocs\sgrh
C:\MAMP\bin\php\php8.2.14\php.exe cron_contratos_temporales.php
```
**Nota**: Si falla con "could not find driver", usar el método HTTP.

### Linux/macOS
```bash
cd /ruta/a/sgrh
php cron_contratos_temporales.php
```

## 📊 Monitoreo

### Ver logs
```bash
# Windows
type storage\logs\cron_contratos_2026-01.log

# Linux/macOS
tail -f storage/logs/cron_contratos_2026-01.log
```

### Verificar última ejecución
```sql
SELECT * FROM contratos 
WHERE notificacion_enviada = 1 
ORDER BY fecha_notificacion DESC 
LIMIT 10;
```

## 🔧 Personalización

### Cambiar días de anticipación (de 5 a otro valor)
Editar línea 107:
```php
$fechaNotificacion = date('Y-m-d', strtotime('+5 days')); // Cambiar 5 por el número deseado
```

### Cambiar hora de ejecución
Modificar el CRON:
```bash
0 6 * * * ...  # 6:00 AM
0 20 * * * ... # 8:00 PM
```

### Filtrar empresas específicas
Agregar en línea 112 (WHERE):
```sql
AND c.empresa_id IN (1, 2, 3)
```

## ⚠️ Consideraciones

1. **Zona horaria**: Configurada en `America/Mexico_City`
2. **Empleados nuevos**: Solo últimos 30 días
3. **Email del jefe**: Se obtiene de `empleados_demograficos.correo_empresa` del jefe
4. **Relación jefe-empleado**: Usar `contratos.jefe_inmediato_id` o `empleados_demograficos.jefe_no_emp`
5. **Ejecución HTTP**: El script se puede ejecutar vía HTTP (requiere Apache corriendo)
6. **Seguridad**: Considerar agregar autenticación por token si el servidor es público

## 📝 TODO

- [ ] Implementar panel de administración de notificaciones
- [ ] Agregar plantillas HTML para emails
- [ ] Configurar recordatorios múltiples (5 días, 2 días, día de vencimiento)
- [ ] Dashboard con gráficas de contratos por vencer
- [ ] Permitir que jefe responda desde el email (aprobar/rechazar)

## 🐛 Solución de Problemas

### El CRON no se ejecuta
- Verificar permisos del archivo
- Revisar ruta absoluta de PHP en el CRON
- Comprobar logs del sistema: `/var/log/syslog` (Linux) o Visor de Eventos (Windows)

### No se envían emails
- Verificar configuración SMTP
- Comprobar que el servidor permite envío de correos
- Revisar carpeta de spam del destinatario
- Ver errores en el log del CRON

### Errores de conexión a BD
- Validar credenciales en `includes/conexion.php`
- Verificar que MySQL esté corriendo
- Comprobar permisos de usuario de BD

## 📞 Soporte

Para reportar problemas o sugerencias, contactar al equipo de desarrollo.
