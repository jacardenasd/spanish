<?php
// includes/contratos/pdf_generator.php
// Helper para generar PDFs de contratos usando Dompdf
// Requiere: composer require dompdf/dompdf

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../conexion.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!class_exists(Dompdf::class)) {
    throw new RuntimeException('Dompdf no está instalado. Ejecuta "composer require dompdf/dompdf".');
}

/**
 * Convierte una ruta relativa del proyecto a una absoluta legible por Dompdf.
 */
function contrato_abs_path($path) {
    if (!$path) return '';
    // Si ya es absoluta, regresa
    if (preg_match('#^[a-zA-Z]:\\\\#', $path) || strpos($path, '/') === 0) {
        return $path;
    }
    return realpath(__DIR__ . '/../' . ltrim($path, '/')) ?: $path;
}

/**
 * Genera un PDF a partir de un template de texto plano con placeholders «CAMPO» o {{CAMPO}}.
 *
 * @param string $templateFile Ruta al archivo .txt/.md/.html con placeholders
 * @param array  $vars         Diccionario campo => valor
 * @param array  $opts         Opciones: title, logo_path, output_path, paper (legal/oficio), orientation, is_html
 *
 * @return array { ok: bool, path: string, bytes: int, error?: string }
 */
function contratos_render_pdf($templateFile, array $vars, array $opts = array()) {
    $templateFile = contrato_abs_path($templateFile);
    if (!is_file($templateFile)) {
        return array('ok' => false, 'error' => 'No se encontró la plantilla: ' . $templateFile);
    }

    $raw = file_get_contents($templateFile);
    if ($raw === false) {
        return array('ok' => false, 'error' => 'No se pudo leer la plantilla.');
    }

    // Reemplazos de placeholders
    foreach ($vars as $k => $v) {
        $needle1 = '«' . $k . '»';
        $needle2 = '{{' . $k . '}}';
        $raw = str_replace($needle1, (string)$v, $raw);
        $raw = str_replace($needle2, (string)$v, $raw);
    }

    $isHtml = !empty($opts['is_html']);

    // Escapar HTML y respetar saltos sólo si no es HTML puro
    $body = $isHtml
        ? $raw
        : nl2br(htmlspecialchars($raw, ENT_QUOTES, 'UTF-8'));

    $title = isset($opts['title']) ? (string)$opts['title'] : 'Contrato';
    $logoPath = isset($opts['logo_path']) ? contrato_abs_path($opts['logo_path']) : '';
    // Oficio = legal en Dompdf
    $paper = isset($opts['paper']) ? $opts['paper'] : 'legal';
    $orientation = isset($opts['orientation']) ? $opts['orientation'] : 'portrait';

    $logoHtml = '';
    if ($logoPath && is_file($logoPath)) {
        // Dompdf requiere ruta de archivo accesible
        $logoPathHtml = $logoPath;
        $logoHtml = '<div id="logo"><img src="' . $logoPathHtml . '" alt="logo"/></div>';
    }

        $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 110px 50px 60px 50px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; line-height: 1.35; }
    #header { position: fixed; top: -90px; left: 0; right: 0; height: 80px; }
    #logo img { height: 70px; }
    #footer { position: fixed; bottom: -40px; left: 0; right: 0; height: 30px; font-size: 9pt; color: #666; }
    .content { width: 100%; }
    p { margin: 0 0 8px 0; }
    h1, h2, h3 { margin: 8px 0; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 4px; }
</style>
</head>
<body>
    <div id="header">' . $logoHtml . '</div>
    <div id="footer">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>
    <div class="content">' . $body . '</div>
</body>
</html>';

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->setPaper($paper, $orientation);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();

    $outputPath = isset($opts['output_path']) ? $opts['output_path'] : null;
    if (!$outputPath) {
        $tmp = sys_get_temp_dir();
        $outputPath = $tmp . DIRECTORY_SEPARATOR . uniqid('contrato_', true) . '.pdf';
    }

    $dir = dirname($outputPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $bytes = file_put_contents($outputPath, $dompdf->output());
    if ($bytes === false) {
        return array('ok' => false, 'error' => 'No se pudo escribir el PDF.');
    }

    return array('ok' => true, 'path' => $outputPath, 'bytes' => $bytes);
}

/**
 * Registra el documento en contratos_documentos (si existe la tabla).
 */
function contratos_registrar_documento($pdo, array $data) {
    // contrato_id y empleado_id pueden ser NULL cuando aún no existen registros definitivos
    $required = array('empresa_id','tipo_documento','nombre_archivo','ruta_archivo');
    foreach ($required as $r) {
        if (!isset($data[$r])) {
            return array('ok' => false, 'error' => 'Falta campo requerido: ' . $r);
        }
    }

    $sqlCheck = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'contratos_documentos'";
    $st = $pdo->query($sqlCheck);
    if ((int)$st->fetchColumn() === 0) {
        return array('ok' => false, 'error' => 'Tabla contratos_documentos no existe. Ejecuta contratos_estructura.sql');
    }

        $sql = "INSERT INTO contratos_documentos
            (contrato_id, empleado_id, empresa_id, tipo_documento, nombre_archivo, ruta_archivo, extension, tamanio, fecha_generacion, generado_por)
            VALUES (:c, :e, :emp, :t, :nom, :ruta, :ext, :tam, NOW(), :user)";

    try {
        $st = $pdo->prepare($sql);
        $st->execute(array(
            ':c' => isset($data['contrato_id']) ? $data['contrato_id'] : null,
            ':e' => isset($data['empleado_id']) ? $data['empleado_id'] : null,
            ':emp' => $data['empresa_id'],
            ':t' => $data['tipo_documento'],
            ':nom' => $data['nombre_archivo'],
            ':ruta' => $data['ruta_archivo'],
            ':ext' => isset($data['extension']) ? $data['extension'] : 'pdf',
            ':tam' => isset($data['tamanio']) ? $data['tamanio'] : null,
            ':user' => isset($data['generado_por']) ? $data['generado_por'] : null,
        ));
    } catch (Exception $e) {
        return array('ok' => false, 'error' => 'Error al registrar documento: ' . $e->getMessage());
    }

    return array('ok' => true, 'documento_id' => $pdo->lastInsertId());
}

/**
 * Helper de alto nivel: genera PDF, guarda en storage y registra en DB.
 */
function contratos_generar_y_guardar($templateFile, array $vars, array $meta) {
    global $pdo;

    $empresaId = isset($meta['empresa_id']) ? (int)$meta['empresa_id'] : 0;
    $empleadoId = isset($meta['empleado_id']) ? (int)$meta['empleado_id'] : 0;
    $contratoId = isset($meta['contrato_id']) ? (int)$meta['contrato_id'] : 0;

    // Normalizar a null cuando no existen referencias reales
    if ($empleadoId <= 0) {
        $empleadoId = null;
    }
    if ($contratoId <= 0) {
        $contratoId = null;
    }
    $tipoDoc = isset($meta['tipo_documento']) ? $meta['tipo_documento'] : 'contrato_temporal';
    $nombreArchivo = isset($meta['nombre_archivo']) ? $meta['nombre_archivo'] : ($tipoDoc . '.pdf');
    $logo = isset($meta['logo_path']) ? $meta['logo_path'] : '';
    $userId = isset($meta['generado_por']) ? (int)$meta['generado_por'] : null;

    $storageBase = contrato_abs_path('storage/contratos');
    $destDir = $storageBase . DIRECTORY_SEPARATOR . $empresaId . DIRECTORY_SEPARATOR . $empleadoId;
    $destPath = $destDir . DIRECTORY_SEPARATOR . $nombreArchivo;

    // Si la plantilla es HTML, activar is_html
    $isHtml = false;
    if (preg_match('/\.html?$/i', $templateFile)) {
        $isHtml = true;
    }

    $render = contratos_render_pdf($templateFile, $vars, array(
        'title' => $tipoDoc,
        'logo_path' => $logo,
        'output_path' => $destPath,
        'is_html' => $isHtml,
        // Por defecto todos los contratos van en tamaño oficio
        'paper' => isset($meta['paper']) ? $meta['paper'] : 'legal',
    ));

    if (!$render['ok']) return $render;

    // Registrar documento (empleado_id puede ser 0 para nuevos ingresos)
    if ($empresaId > 0) {
        $registroResult = contratos_registrar_documento($pdo, array(
            'contrato_id' => $contratoId,
            'empleado_id' => $empleadoId,
            'empresa_id' => $empresaId,
            'tipo_documento' => $tipoDoc,
            'nombre_archivo' => $nombreArchivo,
            'ruta_archivo' => $destPath,
            'tamanio' => $render['bytes'],
            'generado_por' => $userId,
        ));

        if (!$registroResult['ok']) {
            return $registroResult; // Propagar error de registro (FK, permisos, etc.)
        }

        $render['documento_id'] = $registroResult['documento_id'];
    }

    return $render;
}

?>
