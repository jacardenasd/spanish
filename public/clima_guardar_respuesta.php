<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guard.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

function out($ok, $data) {
    $resp = array('ok' => $ok);
    if (is_array($data)) {
        foreach ($data as $k => $v) { $resp[$k] = $v; }
    }
    echo json_encode($resp);
    exit;
}

$empresa_id = 0;
if (isset($_SESSION['empresa_id'])) { $empresa_id = (int)$_SESSION['empresa_id']; }

$usuario_id = 0;
if (isset($_SESSION['usuario_id'])) { $usuario_id = (int)$_SESSION['usuario_id']; }

$reactivo_id = 0;
if (isset($_POST['reactivo_id'])) { $reactivo_id = (int)$_POST['reactivo_id']; }

$valor = 0;
if (isset($_POST['valor'])) { $valor = (int)$_POST['valor']; }

if ($empresa_id <= 0 || $usuario_id <= 0) out(false, array('error' => 'Sesión inválida.'));
if ($reactivo_id <= 0) out(false, array('error' => 'Reactivo inválido.'));
if ($valor < 1 || $valor > 5) out(false, array('error' => 'Valor fuera de rango (1-5).'));

// Resolver empleado_id
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
if ($empleado_id <= 0) out(false, array('error' => 'No se encontró empleado vinculado al usuario.'));

// Periodo activo (último borrador/publicado)
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
if ($periodo_id <= 0) out(false, array('error' => 'No hay periodo activo.'));

// Validar elegibilidad
$sqlEl = "SELECT ce.elegible
          FROM clima_elegibles ce
          WHERE ce.periodo_id = :periodo_id
            AND ce.empleado_id = :empleado_id
            AND ce.empresa_id = :empresa_id
          LIMIT 1";
$stEl = $pdo->prepare($sqlEl);
$stEl->execute(array(':periodo_id' => $periodo_id, ':empleado_id' => $empleado_id, ':empresa_id' => $empresa_id));
$elegible = (int)$stEl->fetchColumn();
if ($elegible !== 1) out(false, array('error' => 'No eres elegible para contestar.'));

// Validar que el reactivo exista y esté activo
$sqlRx = "SELECT COUNT(*)
          FROM clima_reactivos
          WHERE reactivo_id = :rid AND activo = 1";
$stRx = $pdo->prepare($sqlRx);
$stRx->execute(array(':rid' => $reactivo_id));
if ((int)$stRx->fetchColumn() <= 0) out(false, array('error' => 'Reactivo no disponible.'));

// UPSERT (MySQL 5.7 ok)
$sqlUp = "INSERT INTO clima_respuestas (periodo_id, empleado_id, reactivo_id, valor, fecha_respuesta)
          VALUES (:periodo_id, :empleado_id, :reactivo_id, :valor, NOW())
          ON DUPLICATE KEY UPDATE valor = VALUES(valor), fecha_respuesta = NOW()";
$stUp = $pdo->prepare($sqlUp);
$stUp->execute(array(
    ':periodo_id' => $periodo_id,
    ':empleado_id' => $empleado_id,
    ':reactivo_id' => $reactivo_id,
    ':valor' => $valor
));

// Calcular avance actualizado (contestados / total reactivos)
$sqlTot = "SELECT COUNT(*) FROM clima_reactivos WHERE activo = 1";
$total = (int)$pdo->query($sqlTot)->fetchColumn();

$sqlCon = "SELECT COUNT(DISTINCT reactivo_id)
           FROM clima_respuestas
           WHERE periodo_id = :periodo_id AND empleado_id = :empleado_id";
$stCon = $pdo->prepare($sqlCon);
$stCon->execute(array(':periodo_id' => $periodo_id, ':empleado_id' => $empleado_id));
$contestados = (int)$stCon->fetchColumn();

$avance = 0.0;
if ($total > 0) $avance = round(($contestados / $total) * 100, 2);

out(true, array('avance' => $avance));
