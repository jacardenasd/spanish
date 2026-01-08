<?php
// public/clima_finalizar.php
// Cierra la encuesta de clima si está completa (Likert + abiertas obligatorias).
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

$empresa_id = 0;
if (isset($_SESSION['empresa_id'])) { $empresa_id = (int)$_SESSION['empresa_id']; }

$usuario_id = 0;
if (isset($_SESSION['usuario_id'])) { $usuario_id = (int)$_SESSION['usuario_id']; }

if ($empresa_id <= 0 || $usuario_id <= 0) out_json(false, array('error' => 'Sesión inválida.'));

if (!table_exists($pdo, 'clima_envios')) {
    out_json(false, array('error' => 'No existe la tabla clima_envios. Ejecuta clima_envios.sql'));
}

// Resolver empleado_id
$sqlEmp = "SELECT ue.empleado_id
           FROM usuario_empresas ue
           WHERE ue.usuario_id = :usuario_id
             AND ue.empresa_id = :empresa_id
             AND ue.estatus = 1
           LIMIT 1";
$stEmp = $pdo->prepare($sqlEmp);
$stEmp->execute(array(':usuario_id' => $usuario_id, ':empresa_id' => $empresa_id));
$empleado_id = (int)$stEmp->fetchColumn();
if ($empleado_id <= 0) out_json(false, array('error' => 'No se encontró empleado vinculado.'));

// Periodo activo
$sqlPer = "SELECT periodo_id
           FROM clima_periodos
           WHERE empresa_id = :empresa_id
             AND estatus IN ('borrador','publicado')
           ORDER BY anio DESC, periodo_id DESC
           LIMIT 1";
$stPer = $pdo->prepare($sqlPer);
$stPer->execute(array(':empresa_id' => $empresa_id));
$periodo_id = (int)$stPer->fetchColumn();
if ($periodo_id <= 0) out_json(false, array('error' => 'No hay periodo activo.'));

// Elegibilidad + unidad
$sqlEl = "SELECT ce.elegible, ce.unidad_id
          FROM clima_elegibles ce
          WHERE ce.periodo_id = :periodo_id
            AND ce.empleado_id = :empleado_id
            AND ce.empresa_id = :empresa_id
          LIMIT 1";
$stEl = $pdo->prepare($sqlEl);
$stEl->execute(array(':periodo_id' => $periodo_id, ':empleado_id' => $empleado_id, ':empresa_id' => $empresa_id));
$el = $stEl->fetch(PDO::FETCH_ASSOC);
if (!$el) out_json(false, array('error' => 'No estás en elegibles.'));
if ((int)$el['elegible'] !== 1) out_json(false, array('error' => 'No eres elegible.'));
$unidad_id = (int)$el['unidad_id'];
if ($unidad_id <= 0) out_json(false, array('error' => 'No se pudo determinar tu Dirección (unidad).'));

// Ya finalizada?
$stChk = $pdo->prepare("SELECT completado FROM clima_envios WHERE periodo_id = :p AND empleado_id = :e LIMIT 1");
$stChk->execute(array(':p' => $periodo_id, ':e' => $empleado_id));
$ya = $stChk->fetch(PDO::FETCH_ASSOC);
if ($ya && (int)$ya['completado'] === 1) {
    out_json(true, array('message' => 'Encuesta ya finalizada.'));
}

// Validación Likert: todas las preguntas activas deben estar contestadas
$total_reactivos = 0;
$stTR = $pdo->prepare("SELECT COUNT(*) FROM clima_reactivos WHERE activo = 1");
$stTR->execute();
$total_reactivos = (int)$stTR->fetchColumn();

$contestados = 0;
$stCR = $pdo->prepare("SELECT COUNT(DISTINCT reactivo_id) FROM clima_respuestas WHERE periodo_id = :p AND empleado_id = :e");
$stCR->execute(array(':p' => $periodo_id, ':e' => $empleado_id));
$contestados = (int)$stCR->fetchColumn();

if ($total_reactivos > 0 && $contestados < $total_reactivos) {
    out_json(false, array(
        'error' => 'Aún no has contestado todas las preguntas.',
        'total' => $total_reactivos,
        'contestados' => $contestados
    ));
}

// Validación abiertas obligatorias (si existen tablas)
if (table_exists($pdo, 'clima_preguntas_abiertas') && table_exists($pdo, 'clima_respuestas_abiertas')) {
    $ob_total = 0;
    $stOB = $pdo->prepare("SELECT COUNT(*) FROM clima_preguntas_abiertas WHERE activo = 1 AND obligatorio = 1");
    $stOB->execute();
    $ob_total = (int)$stOB->fetchColumn();

    if ($ob_total > 0) {
        $ob_cont = 0;
        $stOC = $pdo->prepare("
            SELECT COUNT(*)
            FROM clima_respuestas_abiertas ra
            INNER JOIN clima_preguntas_abiertas pa ON pa.pregunta_id = ra.pregunta_id
            WHERE ra.periodo_id = :p AND ra.empleado_id = :e
              AND pa.activo = 1 AND pa.obligatorio = 1
              AND TRIM(IFNULL(ra.respuesta,'')) <> ''
        ");
        $stOC->execute(array(':p' => $periodo_id, ':e' => $empleado_id));
        $ob_cont = (int)$stOC->fetchColumn();

        if ($ob_cont < $ob_total) {
            out_json(false, array('error' => 'Faltan preguntas abiertas obligatorias por contestar.'));
        }
    }
}

// Registrar cierre
$ip = '';
if (isset($_SERVER['REMOTE_ADDR'])) { $ip = (string)$_SERVER['REMOTE_ADDR']; }
$ua = '';
if (isset($_SERVER['HTTP_USER_AGENT'])) { $ua = substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255); }

$sqlIns = "INSERT INTO clima_envios (periodo_id, empleado_id, empresa_id, unidad_id, completado, completado_at, ip, user_agent)
           VALUES (:p, :e, :empresa, :unidad, 1, NOW(), :ip, :ua)
           ON DUPLICATE KEY UPDATE completado = 1, completado_at = NOW(), ip = VALUES(ip), user_agent = VALUES(user_agent)";
$stIns = $pdo->prepare($sqlIns);
$stIns->execute(array(
    ':p' => $periodo_id,
    ':e' => $empleado_id,
    ':empresa' => $empresa_id,
    ':unidad' => $unidad_id,
    ':ip' => $ip,
    ':ua' => $ua
));

out_json(true, array('message' => 'Gracias. Tu encuesta quedó finalizada.'));
