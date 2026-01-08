<?php
// public/clima_guardar_abierta.php
// Guarda respuestas a preguntas abiertas de Clima Laboral (JSON endpoint)
// Reglas: respeta empresa_id, periodo_id, unidad_id; valida elegibilidad en clima_elegibles
// Compatible MySQL 5.7 - NO usar operador ??

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guard.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

function out_json($ok, $arr) {
    $resp = array('ok' => $ok);
    if (is_array($arr)) {
        foreach ($arr as $k => $v) { $resp[$k] = $v; }
    }
    echo json_encode($resp);
    exit;
}

function table_exists($pdo, $table_name) {
    $sql = "SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = :t";
    $st = $pdo->prepare($sql);
    $st->execute(array(':t' => $table_name));
    return ((int)$st->fetchColumn() > 0);
}

// ===== Sesión =====
$empresa_id = 0;
if (isset($_SESSION['empresa_id'])) { $empresa_id = (int)$_SESSION['empresa_id']; }

$usuario_id = 0;
if (isset($_SESSION['usuario_id'])) { $usuario_id = (int)$_SESSION['usuario_id']; }

if ($empresa_id <= 0 || $usuario_id <= 0) {
    out_json(false, array('error' => 'Sesión inválida.'));
}

// ===== Validar tablas necesarias =====
if (!table_exists($pdo, 'clima_preguntas_abiertas') || !table_exists($pdo, 'clima_respuestas_abiertas')) {
    out_json(false, array('error' => 'No existen tablas de preguntas abiertas. Ejecuta el SQL de clima_preguntas_abiertas.sql'));
}

// ===== Input =====
$pregunta_id = 0;
if (isset($_POST['pregunta_id'])) { $pregunta_id = (int)$_POST['pregunta_id']; }

$respuesta = '';
if (isset($_POST['respuesta'])) { $respuesta = trim((string)$_POST['respuesta']); }

if ($pregunta_id <= 0) out_json(false, array('error' => 'Pregunta inválida.'));

// Sanitización básica: limitar longitud
if (strlen($respuesta) > 3000) {
    $respuesta = substr($respuesta, 0, 3000);
}

// Validar pregunta
$stQ = $pdo->prepare("SELECT pregunta_id, obligatorio, activo FROM clima_preguntas_abiertas WHERE pregunta_id = :id LIMIT 1");
$stQ->execute(array(':id' => $pregunta_id));
$q = $stQ->fetch(PDO::FETCH_ASSOC);
if (!$q) out_json(false, array('error' => 'Pregunta no encontrada.'));
if ((int)$q['activo'] !== 1) out_json(false, array('error' => 'Pregunta no disponible.'));

$obligatorio = (int)$q['obligatorio'];
if ($obligatorio === 1 && $respuesta === '') {
    out_json(false, array('error' => 'Esta pregunta es obligatoria.'));
}

// ===== Resolver empleado_id =====
$empleado_id = 0;
$sqlEmp = "SELECT ue.empleado_id
           FROM usuario_empresas ue
           WHERE ue.usuario_id = :usuario_id
             AND ue.empresa_id = :empresa_id
             AND ue.estatus = 1
           LIMIT 1";
$stEmp = $pdo->prepare($sqlEmp);
$stEmp->execute(array(':usuario_id' => $usuario_id, ':empresa_id' => $empresa_id));
$empleado_id = (int)$stEmp->fetchColumn();
if ($empleado_id <= 0) out_json(false, array('error' => 'No se encontró empleado vinculado al usuario.'));

// ===== Periodo activo =====
$periodo_id = 0;
$sqlPer = "SELECT periodo_id
           FROM clima_periodos
           WHERE empresa_id = :empresa_id
             AND estatus IN ('borrador','publicado')
           ORDER BY anio DESC, periodo_id DESC
           LIMIT 1";
$stPer = $pdo->prepare($sqlPer);
$stPer->execute(array(':empresa_id' => $empresa_id));
$periodo_id = (int)$stPer->fetchColumn();
if ($periodo_id <= 0) out_json(false, array('error' => 'No hay periodo activo de clima para la empresa.'));

// Bloqueo si ya finalizó
if (table_exists($pdo, 'clima_envios')) {
    $stF = $pdo->prepare("SELECT completado FROM clima_envios WHERE periodo_id = :p AND empleado_id = :e LIMIT 1");
    $stF->execute(array(':p' => $periodo_id, ':e' => $empleado_id));
    $c = $stF->fetch(PDO::FETCH_ASSOC);
    if ($c && (int)$c['completado'] === 1) {
        out_json(false, array('error' => 'La encuesta ya fue finalizada.'));
    }
}

// ===== Elegibilidad + unidad_id =====
$sqlEl = "SELECT ce.elegible, ce.unidad_id
          FROM clima_elegibles ce
          WHERE ce.periodo_id = :periodo_id
            AND ce.empleado_id = :empleado_id
            AND ce.empresa_id = :empresa_id
          LIMIT 1";
$stEl = $pdo->prepare($sqlEl);
$stEl->execute(array(':periodo_id' => $periodo_id, ':empleado_id' => $empleado_id, ':empresa_id' => $empresa_id));
$el = $stEl->fetch(PDO::FETCH_ASSOC);

if (!$el) out_json(false, array('error' => 'No estás en la lista de elegibles para este periodo.'));
if ((int)$el['elegible'] !== 1) out_json(false, array('error' => 'No eres elegible para contestar en este periodo.'));

$unidad_id = (int)$el['unidad_id'];
if ($unidad_id <= 0) {
    out_json(false, array('error' => 'No se pudo determinar tu Dirección (unidad).'));
}

// ===== Guardado (UPSERT) =====
$sqlUp = "INSERT INTO clima_respuestas_abiertas (periodo_id, empleado_id, empresa_id, unidad_id, pregunta_id, respuesta, fecha_respuesta)
          VALUES (:periodo_id, :empleado_id, :empresa_id, :unidad_id, :pregunta_id, :respuesta, NOW())
          ON DUPLICATE KEY UPDATE respuesta = VALUES(respuesta), fecha_respuesta = NOW()";
$stUp = $pdo->prepare($sqlUp);
$stUp->execute(array(
    ':periodo_id' => $periodo_id,
    ':empleado_id' => $empleado_id,
    ':empresa_id' => $empresa_id,
    ':unidad_id' => $unidad_id,
    ':pregunta_id' => $pregunta_id,
    ':respuesta' => $respuesta
));

out_json(true, array('saved' => 1));
