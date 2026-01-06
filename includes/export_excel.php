<?php
// includes/export_excel.php

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

function export_xlsx($filename, array $headers, array $rows) {

    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        throw new RuntimeException('PhpSpreadsheet no está disponible.');
    }

    // Limpiar buffers
    if (ob_get_length()) {
        @ob_end_clean();
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // ---- ENCABEZADOS (fila 1) ----
    $sheet->fromArray($headers, null, 'A1');

    // ---- DATOS (desde fila 2) ----
    if (!empty($rows)) {
        $sheet->fromArray($rows, null, 'A2');
    }

    // ---- Auto size columnas ----
    $highestColumn = $sheet->getHighestColumn();
    foreach (range('A', $highestColumn) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // ---- Descarga ----
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
