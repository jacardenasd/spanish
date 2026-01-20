<?php
/**
 * CRON: Gestión de Contratos Temporales
 * 
 * Funciones:
 * 1. Detectar contratos temporales que vencen en 5 días
 * 2. Enviar notificación al jefe inmediato para confirmar renovación/conversión
 * 3. Sincronizar empleados nuevos sin datos demográficos
 * 4. Cambiar estatus de contratos según fechas
 * 
 * Ejecutar diariamente: 0 8 * * * php /path/to/sgrh/cron_contratos_temporales.php
 */

// Configuración para ejecución por CRON (sin sesión web)
define('CRON_EXEC', true);

// Conexión directa a BD para evitar dependencias de sesión
$host = 'localhost:3306'; // Puerto explícito para MAMP
$db = 'sgrh';
$user = 'root';
$pass = 'root';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Error de conexión BD: ' . $e->getMessage() . "\n");
}

// Cargar config solo para constantes
if (file_exists(__DIR__ . '/includes/config.php')) {
    require_once __DIR__ . '/includes/config.php';
} else {
    define('ASSET_BASE', 'http://localhost/sgrh/');
}

// Configuración de zona horaria
date_default_timezone_set('America/Mexico_City');

// ========== CONFIGURACIÓN ==========
// Cambiar a true cuando se desee activar envío de correos
define('ENVIAR_CORREOS_REALES', false);
define('METODO_CORREO', 'notificaciones_bd'); // opciones: 'mail_nativo', 'phpmailer', 'notificaciones_bd'

// Log de ejecución
$logFile = __DIR__ . '/storage/logs/cron_contratos_' . date('Y-m') . '.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function escribirLog($mensaje) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $linea = "[{$timestamp}] {$mensaje}\n";
    file_put_contents($logFile, $linea, FILE_APPEND);
    echo $linea; // También mostrar en consola
}

escribirLog("========== INICIO CRON CONTRATOS TEMPORALES ==========");

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ========================================
    // 1. SINCRONIZAR EMPLEADOS NUEVOS
    // ========================================
    escribirLog("--- Sincronizando empleados nuevos sin datos demográficos ---");
    
    $sqlEmpleadosNuevos = "
        SELECT e.empleado_id, e.empresa_id, e.nombre, e.apellido_paterno, e.apellido_materno,
               e.rfc_base, e.curp, e.fecha_ingreso, e.salario_diario, e.salario_mensual,
               e.puesto_nombre, e.no_emp
        FROM empleados e
        LEFT JOIN empleados_demograficos ed ON e.empleado_id = ed.empleado_id
        WHERE e.es_activo = 1 
          AND ed.empleado_id IS NULL
          AND e.fecha_ingreso >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";
    
    $stNuevos = $pdo->query($sqlEmpleadosNuevos);
    $empleadosNuevos = $stNuevos->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($empleadosNuevos) > 0) {
        escribirLog("Encontrados " . count($empleadosNuevos) . " empleados sin datos demográficos");
        
        $sqlInsertDemo = "
            INSERT INTO empleados_demograficos (
                empleado_id, empresa_id, nombre, apellido_paterno, apellido_materno,
                rfc, curp, fecha_alta, sueldo_diario, sueldo_mensual,
                datos_completos, created_at
            ) VALUES (
                :emp_id, :empresa_id, :nombre, :ap, :am,
                :rfc, :curp, :fecha_alta, :sdiario, :smensual,
                0, NOW()
            )
        ";
        
        $stInsert = $pdo->prepare($sqlInsertDemo);
        
        foreach ($empleadosNuevos as $emp) {
            try {
                $stInsert->execute([
                    ':emp_id' => $emp['empleado_id'],
                    ':empresa_id' => $emp['empresa_id'],
                    ':nombre' => $emp['nombre'],
                    ':ap' => $emp['apellido_paterno'] ?: '',
                    ':am' => $emp['apellido_materno'] ?: '',
                    ':rfc' => $emp['rfc_base'] ?: '',
                    ':curp' => $emp['curp'] ?: '',
                    ':fecha_alta' => $emp['fecha_ingreso'] ?: date('Y-m-d'),
                    ':sdiario' => $emp['salario_diario'] ?: 0,
                    ':smensual' => $emp['salario_mensual'] ?: 0
                ]);
                
                escribirLog("  ✓ Creado registro demográfico: " . $emp['nombre'] . " " . $emp['apellido_paterno'] . " (ID: {$emp['empleado_id']})");
            } catch (Exception $e) {
                escribirLog("  ✗ Error al crear demográfico para empleado {$emp['empleado_id']}: " . $e->getMessage());
            }
        }
    } else {
        escribirLog("No hay empleados nuevos sin datos demográficos");
    }
    
    // ========================================
    // 2. DETECTAR CONTRATOS PRÓXIMOS A VENCER
    // ========================================
    escribirLog("--- Buscando contratos temporales próximos a vencer (5 días) ---");
    
    $fechaNotificacion = date('Y-m-d', strtotime('+5 days'));
    
    $sqlContratosPorVencer = "
        SELECT c.contrato_id, c.empleado_id, c.empresa_id, c.tipo_contrato, c.numero_contrato,
               c.fecha_inicio, c.fecha_fin, c.jefe_inmediato_id, c.estatus,
               e.nombre, e.apellido_paterno, e.apellido_materno, e.no_emp,
               ed.correo_empresa, ed.telefono_personal,
               jefe.nombre AS jefe_nombre, jefe.apellido_paterno AS jefe_ap,
               jefe_ed.correo_empresa AS jefe_correo,
               emp.nombre AS empresa_nombre
        FROM contratos c
        INNER JOIN empleados e ON c.empleado_id = e.empleado_id
        LEFT JOIN empleados_demograficos ed ON e.empleado_id = ed.empleado_id
        LEFT JOIN empleados jefe ON c.jefe_inmediato_id = jefe.empleado_id
        LEFT JOIN empleados_demograficos jefe_ed ON jefe.empleado_id = jefe_ed.empleado_id
        INNER JOIN empresas emp ON c.empresa_id = emp.empresa_id
        WHERE c.tipo_contrato = 'temporal'
          AND c.fecha_fin = :fecha_notif
          AND c.notificacion_enviada = 0
          AND c.estatus IN ('activo', 'por_vencer')
          AND e.es_activo = 1
    ";
    
    $stVencer = $pdo->prepare($sqlContratosPorVencer);
    $stVencer->execute([':fecha_notif' => $fechaNotificacion]);
    $contratosPorVencer = $stVencer->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($contratosPorVencer) > 0) {
        escribirLog("Encontrados " . count($contratosPorVencer) . " contratos por vencer el {$fechaNotificacion}");
        
        foreach ($contratosPorVencer as $contrato) {
            $nombreEmpleado = trim($contrato['nombre'] . ' ' . $contrato['apellido_paterno'] . ' ' . $contrato['apellido_materno']);
            $nombreJefe = trim($contrato['jefe_nombre'] . ' ' . $contrato['jefe_ap']);
            
            escribirLog("  → Contrato #{$contrato['contrato_id']}: {$nombreEmpleado} (vence: {$contrato['fecha_fin']})");
            
            // Actualizar estatus a 'por_vencer'
            if ($contrato['estatus'] === 'activo') {
                $sqlUpdateEstatus = "UPDATE contratos SET estatus = 'por_vencer' WHERE contrato_id = :cid";
                $stUpdate = $pdo->prepare($sqlUpdateEstatus);
                $stUpdate->execute([':cid' => $contrato['contrato_id']]);
                escribirLog("    Estatus actualizado: activo → por_vencer");
            }
            
            // Enviar notificación al jefe inmediato
            if ($contrato['jefe_inmediato_id'] && $contrato['jefe_correo']) {
                if (ENVIAR_CORREOS_REALES) {
                    $emailEnviado = enviarNotificacionJefe($contrato);
                    
                    if ($emailEnviado) {
                        // Marcar como notificado
                        $sqlNotificado = "UPDATE contratos 
                                         SET notificacion_enviada = 1, fecha_notificacion = NOW() 
                                         WHERE contrato_id = :cid";
                        $stNotif = $pdo->prepare($sqlNotificado);
                        $stNotif->execute([':cid' => $contrato['contrato_id']]);
                        
                        escribirLog("    ✓ Notificación enviada a: {$nombreJefe} ({$contrato['jefe_correo']})");
                    } else {
                        escribirLog("    ✗ Error al enviar notificación a {$contrato['jefe_correo']}");
                    }
                } else {
                    // Modo simulación: registrar notificación sin enviar correo
                    registrarNotificacionBD($contrato);
                    
                    // Marcar como notificado
                    $sqlNotificado = "UPDATE contratos 
                                     SET notificacion_enviada = 1, fecha_notificacion = NOW() 
                                     WHERE contrato_id = :cid";
                    $stNotif = $pdo->prepare($sqlNotificado);
                    $stNotif->execute([':cid' => $contrato['contrato_id']]);
                    
                    escribirLog("    📧 Notificación registrada (email desactivado): {$nombreJefe} ({$contrato['jefe_correo']})");
                }
            } else {
                escribirLog("    ⚠ Sin jefe inmediato o correo para notificar");
            }
        }
    } else {
        escribirLog("No hay contratos temporales por vencer en 5 días");
    }
    
    // ========================================
    // 3. ACTUALIZAR CONTRATOS VENCIDOS
    // ========================================
    escribirLog("--- Actualizando contratos vencidos ---");
    
    $sqlVencidos = "
        UPDATE contratos 
        SET estatus = 'finalizado', updated_at = NOW()
        WHERE tipo_contrato = 'temporal'
          AND fecha_fin < CURDATE()
          AND estatus NOT IN ('finalizado', 'convertido_permanente')
    ";
    
    $stVencidos = $pdo->exec($sqlVencidos);
    
    if ($stVencidos > 0) {
        escribirLog("Finalizados {$stVencidos} contratos vencidos");
    } else {
        escribirLog("No hay contratos vencidos para actualizar");
    }
    
    // ========================================
    // 4. ESTADÍSTICAS FINALES
    // ========================================
    escribirLog("--- Estadísticas ---");
    
    $sqlStats = "
        SELECT 
            tipo_contrato,
            estatus,
            COUNT(*) as total
        FROM contratos
        WHERE estatus NOT IN ('finalizado', 'convertido_permanente')
        GROUP BY tipo_contrato, estatus
        ORDER BY tipo_contrato, estatus
    ";
    
    $stStats = $pdo->query($sqlStats);
    $stats = $stStats->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stats as $stat) {
        escribirLog("  {$stat['tipo_contrato']} - {$stat['estatus']}: {$stat['total']}");
    }
    
    escribirLog("========== FIN CRON - EJECUCIÓN EXITOSA ==========\n");
    
} catch (Exception $e) {
    escribirLog("ERROR CRÍTICO: " . $e->getMessage());
    escribirLog("Stack trace: " . $e->getTraceAsString());
    escribirLog("========== FIN CRON - CON ERRORES ==========\n");
    exit(1);
}

/**
 * Registrar notificación en base de datos (sin enviar correo)
 */
function registrarNotificacionBD($contrato) {
    global $pdo;
    
    $nombreEmpleado = trim($contrato['nombre'] . ' ' . $contrato['apellido_paterno'] . ' ' . $contrato['apellido_materno']);
    $nombreJefe = trim($contrato['jefe_nombre'] . ' ' . $contrato['jefe_ap']);
    
    $asunto = "Contrato próximo a vencer - {$nombreEmpleado}";
    $url = ASSET_BASE . "public/contratos_gestionar.php?empleado_id={$contrato['empleado_id']}";
    
    $mensaje = "El contrato temporal del empleado {$nombreEmpleado} (No. Empleado: {$contrato['no_emp']}) está próximo a vencer el {$contrato['fecha_fin']}. Por favor, evalúe y confirme si desea renovar o convertir a contrato permanente.";
    
    try {
        // Buscar usuario_id del jefe
        $sqlUsuarioJefe = "SELECT usuario_id FROM usuarios WHERE empleado_id = :jefe_id LIMIT 1";
        $stUsuario = $pdo->prepare($sqlUsuarioJefe);
        $stUsuario->execute([':jefe_id' => $contrato['jefe_inmediato_id']]);
        $usuario = $stUsuario->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            $sqlNotif = "INSERT INTO notificaciones 
                        (usuario_destino_id, tipo, asunto, mensaje, url, prioridad, created_at)
                        VALUES (:dest, 'contrato_vencimiento', :asunto, :mensaje, :url, 'alta', NOW())";
            
            $stNotif = $pdo->prepare($sqlNotif);
            $stNotif->execute([
                ':dest' => $usuario['usuario_id'],
                ':asunto' => $asunto,
                ':mensaje' => $mensaje,
                ':url' => $url
            ]);
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        escribirLog("    Error al registrar notificación en BD: " . $e->getMessage());
        return false;
    }
}

/**
 * Enviar notificación por correo al jefe inmediato
 * Esta función está lista para usar pero requiere ENVIAR_CORREOS_REALES = true
 */
function enviarNotificacionJefe($contrato) {
    global $pdo;
    
    // Aquí puedes usar PHPMailer o mail() de PHP
    // Por ahora lo simulo con un registro en BD
    
    $nombreEmpleado = trim($contrato['nombre'] . ' ' . $contrato['apellido_paterno'] . ' ' . $contrato['apellido_materno']);
    $nombreJefe = trim($contrato['jefe_nombre'] . ' ' . $contrato['jefe_ap']);
    
    $asunto = "Contrato próximo a vencer - {$nombreEmpleado}";
    
    $mensaje = "
    Estimado/a {$nombreJefe},
    
    Le informamos que el contrato temporal del empleado {$nombreEmpleado} (No. Empleado: {$contrato['no_emp']})
    está próximo a vencer:
    
    • Tipo de contrato: {$contrato['tipo_contrato']}
    • Número de contrato: {$contrato['numero_contrato']}
    • Fecha de inicio: {$contrato['fecha_inicio']}
    • Fecha de vencimiento: {$contrato['fecha_fin']}
    • Empresa: {$contrato['empresa_nombre']}
    
    Por favor, ingrese al sistema SGRH para:
    - Evaluar el desempeño del empleado
    - Confirmar si desea renovar el contrato temporal
    - O bien, convertir a contrato permanente
    
    Enlace: " . ASSET_BASE . "public/contratos_gestionar.php?empleado_id={$contrato['empleado_id']}
    
    Atentamente,
    Sistema de Gestión de Recursos Humanos
    ";
    
    // Determinar método de envío según configuración
    $metodo = METODO_CORREO;
    
    switch ($metodo) {
        case 'mail_nativo':
            return enviarPorMailNativo($contrato['jefe_correo'], $asunto, $mensaje, $nombreJefe);
            
        case 'phpmailer':
            return enviarPorPHPMailer($contrato['jefe_correo'], $asunto, $mensaje, $nombreJefe);
            
        case 'notificaciones_bd':
        default:
            return registrarNotificacionBD($contrato);
    }
}

/**
 * OPCIÓN 1: Enviar correo usando mail() nativo de PHP
 */
function enviarPorMailNativo($destinatario, $asunto, $mensaje, $nombreDestinatario) {
    $headers = "From: SGRH <noreply@sgrh.local>\r\n";
    $headers .= "Reply-To: rh@empresa.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    $resultado = mail($destinatario, $asunto, $mensaje, $headers);
    
    if ($resultado) {
        escribirLog("    ✓ Correo enviado (mail nativo) a: {$nombreDestinatario} ({$destinatario})");
    } else {
        escribirLog("    ✗ Error al enviar correo (mail nativo) a: {$destinatario}");
    }
    
    return $resultado;
}

/**
 * OPCIÓN 2: Enviar correo usando PHPMailer con SMTP
 * Requiere: composer require phpmailer/phpmailer
 */
function enviarPorPHPMailer($destinatario, $asunto, $mensaje, $nombreDestinatario) {
    // Verificar si PHPMailer está instalado
    if (!file_exists(__DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
        escribirLog("    ⚠ PHPMailer no está instalado. Usar: composer require phpmailer/phpmailer");
        return registrarNotificacionBD(['jefe_correo' => $destinatario]);
    }
    
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configuración SMTP - AJUSTAR CON TUS CREDENCIALES
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Servidor SMTP
        $mail->SMTPAuth = true;
        $mail->Username = 'tu_email@gmail.com'; // Tu email
        $mail->Password = 'tu_password_app'; // Contraseña de aplicación
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        
        // Remitente y destinatario
        $mail->setFrom('noreply@sgrh.local', 'Sistema SGRH');
        $mail->addAddress($destinatario, $nombreDestinatario);
        $mail->addReplyTo('rh@empresa.com', 'Recursos Humanos');
        
        // Contenido
        $mail->isHTML(false);
        $mail->Subject = $asunto;
        $mail->Body = $mensaje;
        
        $mail->send();
        escribirLog("    ✓ Correo enviado (PHPMailer SMTP) a: {$nombreDestinatario} ({$destinatario})");
        return true;
        
    } catch (Exception $e) {
        escribirLog("    ✗ Error PHPMailer: {$mail->ErrorInfo}");
        // Fallback: registrar en BD si falla el envío
        return registrarNotificacionBD(['jefe_correo' => $destinatario, 'jefe_inmediato_id' => null]);
    }
}

exit(0);
