<?php
/**
 * importar_nomina_procesar.php (corregido - tolerante a esquema)
 * - Construye INSERT/UPDATE dinámicos según columnas reales en BD para evitar "Unknown column".
 * - Evita HY093 (parámetros de más/menos) alineando execute() a placeholders existentes.
 * - Guarda el renglón completo en nomina_import_detalle.payload_json para auditoría.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/permisos.php';

require_login();
require_password_change_redirect();
require_demograficos_redirect();
require_empresa();
// Permiso correcto según catálogo: permisos.clave = 'nomina.importar'
require_perm('nomina.importar');

ini_set('memory_limit', '768M');
set_time_limit(0);

require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

function rfc_base($rfc_raw) {
    $r = strtoupper(trim((string)$rfc_raw));
    $r = preg_replace('/[^A-Z0-9]/', '', $r);
    return substr($r, 0, 10);
}

function parse_date_any($v) {
    if ($v === null) return null;

    // Excel serial date (aprox)
    if (is_numeric($v)) {
        $ts = ((int)$v - 25569) * 86400;
        if ($ts > 0) return gmdate('Y-m-d', $ts);
    }

    $s = trim((string)$v);
    if ($s === '') return null;

    $p = explode('/', $s);
    if (count($p) === 3) {
        $d = str_pad(trim($p[0]), 2, '0', STR_PAD_LEFT);
        $m = str_pad(trim($p[1]), 2, '0', STR_PAD_LEFT);
        $y = trim($p[2]);
        if (strlen($y) === 2) $y = '20' . $y;
        return $y . '-' . $m . '-' . $d;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
    return null;
}

function parse_money_2($v) {
    $s = trim((string)$v);
    if ($s === '') return null;
    $s = str_replace([',', '$'], '', $s);
    if (!is_numeric($s)) return null;
    return round((float)$s, 2);
}

function normalize_es_activo($raw) {
    $s = strtoupper(trim((string)$raw));
    $s = preg_replace('/\s+/', ' ', $s);

    if ($s === 'ACTIVO' || $s === 'SI' || $s === 'SÍ' || $s === '1' || $s === 'TRUE') return 1;
    if ($s === 'INACTIVO' || $s === 'BAJA' || $s === 'NO' || $s === '0' || $s === 'FALSE') return 0;

    if ($s === '') return null;
    if (is_numeric($s)) return ((int)$s) ? 1 : 0;

    return null;
}

function table_columns(PDO $pdo, $table) {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $cols = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols[$r['Field']] = true;
    }
    return $cols;
}

/**
 * Construye un UPSERT dinámico.
 *
 * @param string $table
 * @param array  $data        col => placeholder (":x") o expresión ("NOW()")
 * @param array  $uniqueKeys  columnas que forman la llave única
 * @param array  $noUpdate    columnas que NO deben actualizarse en DUPLICATE KEY
 */
function build_upsert_sql($table, $data, $uniqueKeys, $noUpdate = []) {
    // $data: col=>placeholder OR 'NOW()'
    $cols = array_keys($data);

    $insertCols = [];
    $insertVals = [];
    foreach ($data as $c => $ph) {
        $insertCols[] = $c;
        $insertVals[] = $ph;
    }

    $updates = [];
    foreach ($cols as $c) {
        if (in_array($c, $uniqueKeys, true)) continue;
        // No intentamos actualizar created_at si existe
        if ($c === 'created_at') continue;
        if (in_array($c, $noUpdate, true)) continue;
        $updates[] = "$c=VALUES($c)";
    }

    $sql = "INSERT INTO `$table` (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $insertVals) . ")";
    if (!empty($updates)) {
        $sql .= " ON DUPLICATE KEY UPDATE " . implode(',', $updates);
    }
    return $sql;
}

/**
 * Asegura accesos mínimos:
 * 1) usuario_roles: rol "Empleado" (rol_id=1)
 * 2) usuario_empresas: relación usuario-empresa (y empleado_id si existe)
 *
 * Reglas:
 * - No pisa roles existentes; solo agrega el mínimo si el usuario no tiene ninguno.
 * - No crea permisos directos: se heredan del rol.
 */
function ensure_accesos_minimos(PDO $pdo, $usuario_id, $empresa_id, $empleado_id = null) {
    $usuario_id = (int)$usuario_id;
    $empresa_id = (int)$empresa_id;
    $empleado_id = $empleado_id !== null ? (int)$empleado_id : null;

    // 1) Rol mínimo: solo si NO tiene roles.
    $hasRoles = false;
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM usuario_roles WHERE usuario_id=? LIMIT 1");
        $stmt->execute([$usuario_id]);
        $hasRoles = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        // Si no existe la tabla o falla, no rompemos importación.
        $hasRoles = true;
    }

    if (!$hasRoles) {
        // Inserta rol_id=1 (Empleado)
        $pdo->prepare("INSERT IGNORE INTO usuario_roles (usuario_id, rol_id) VALUES (?, 1)")
            ->execute([$usuario_id]);
    }

    // 2) Relación usuario-empresa (si existe la tabla)
    try {
        $colsUE = [];
        $q = $pdo->query("SHOW COLUMNS FROM `usuario_empresas`");
        while ($r = $q->fetch(PDO::FETCH_ASSOC)) $colsUE[$r['Field']] = true;

        if (isset($colsUE['usuario_id']) && isset($colsUE['empresa_id'])) {
            if (isset($colsUE['empleado_id'])) {
                $sql = "
                    INSERT INTO usuario_empresas (usuario_id, empresa_id, empleado_id, es_admin, estatus)
                    VALUES (:uid, :eid, :empid, 0, 1)
                    ON DUPLICATE KEY UPDATE
                        estatus=1,
                        empleado_id=COALESCE(empleado_id, VALUES(empleado_id))
                ";
                $pdo->prepare($sql)->execute([
                    ':uid' => $usuario_id,
                    ':eid' => $empresa_id,
                    ':empid' => $empleado_id
                ]);
            } else {
                $sql = "
                    INSERT INTO usuario_empresas (usuario_id, empresa_id, es_admin, estatus)
                    VALUES (:uid, :eid, 0, 1)
                    ON DUPLICATE KEY UPDATE estatus=1
                ";
                $pdo->prepare($sql)->execute([
                    ':uid' => $usuario_id,
                    ':eid' => $empresa_id
                ]);
            }
        }
    } catch (Throwable $e) {
        // No romper importación.
    }
}

if (empty($_FILES['archivo']['tmp_name'])) {
    header('Location: importar_nomina.php?err=1');
    exit;
}

$archivoTmp = $_FILES['archivo']['tmp_name'];
$archivoNombre = $_FILES['archivo']['name'];

try {
    $pdo->beginTransaction();

    $usuarioSesionId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;

    // Cabecera importación
    $stmt = $pdo->prepare("
        INSERT INTO nomina_importaciones (empresa_id, usuario_id, archivo_nombre, status, mensaje)
        VALUES (NULL, :uid, :fn, 'cargado', NULL)
    ");
    $stmt->execute([':uid' => $usuarioSesionId, ':fn' => $archivoNombre]);
    $import_id = (int)$pdo->lastInsertId();

    // Cargar Excel
    $reader = IOFactory::createReaderForFile($archivoTmp);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($archivoTmp);

    // Hoja Reporte (con fallback)
    $sheet = $spreadsheet->getSheetByName('Reporte');
    if ($sheet === null) {
        foreach ($spreadsheet->getSheetNames() as $nm) {
            if (mb_strtolower(trim($nm), 'UTF-8') === 'reporte') {
                $sheet = $spreadsheet->getSheetByName($nm);
                break;
            }
        }
    }
    if ($sheet === null) $sheet = $spreadsheet->getSheet(0);

    $highestRow = (int)$sheet->getHighestRow();

    // Mapa empresas: nombre => id
    $empMap = [];
    $q = $pdo->query("SELECT empresa_id, nombre FROM empresas");
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $empMap[trim((string)$row['nombre'])] = (int)$row['empresa_id'];
    }

    // Columnas reales
    $colsEmp = table_columns($pdo, 'empleados');
    $colsUsr = table_columns($pdo, 'usuarios');

    // Detalle import
    $insDet = $pdo->prepare("
        INSERT INTO nomina_import_detalle
        (import_id, no_emp, rfc_base, payload_json, accion, mensaje)
        VALUES
        (:iid, :no, :rfc, :payload, :acc, :msg)
    ");

    // Selector empleado (preferimos por empresa_id + no_emp + rfc_base si existe)
    $selEmpSql = "SELECT empleado_id FROM empleados WHERE empresa_id=:eid AND no_emp=:no";
    $selEmpNeedsRfc = false;
    if (isset($colsEmp['rfc_base'])) { $selEmpSql .= " AND rfc_base=:rfc"; $selEmpNeedsRfc = true; }
    $selEmpSql .= " LIMIT 1";
    $selEmp = $pdo->prepare($selEmpSql);

    // Configurar UPSERT empleados con mapeo flexible
    $empColMap = [
        'empresa_id' => ':empresa_id',
        'no_emp' => ':no_emp',
        'rfc_base' => ':rfc_base',
        'curp' => ':curp',
        'nombre' => ':nombre',
        'apellido_paterno' => ':ap',
        'apellido_materno' => ':am',
        'es_activo' => ':es_activo',
        'correo' => ':correo',
        'telefono' => ':telefono',
        'fecha_ingreso' => ':fi',
        'fecha_baja' => ':fb',
        'tipo_empleado_id' => ':tipo_id',
        'tipo_empleado_nombre' => ':tipo_nom',

        'departamento_id' => ':dep_id',
        'departamento_nombre' => ':dep_nom',

        'unidad_id' => ':unidad_id',
        'adscripcion_id' => ':adscripcion_id',
        'puesto_id' => ':puesto_id',
        'jefe_no_emp' => ':jefe_no_emp',

        'puesto_nomina_id' => ':puesto_nomina_id',
        'puesto_nombre' => ':puesto_nomina',

        'centro_trabajo_id' => ':ct_id',
        'centro_trabajo_nombre' => ':ct_nom',

        'jefe_inmediato' => ':jefe_nom',

        'salario_diario' => ':sd',
        'salario_mensual' => ':sm',

        'empresa_nombre' => ':empresa_nombre',
        'estatus' => ':estatus',
        'created_at' => 'NOW()',
        'updated_at' => 'NOW()'
    ];

    $empDataForSql = [];
    foreach ($empColMap as $col => $ph) {
        if (!isset($colsEmp[$col])) continue;
        $empDataForSql[$col] = $ph;
    }

    $empUniqueKeys = ['empresa_id', 'no_emp'];
    if (isset($colsEmp['rfc_base'])) $empUniqueKeys[] = 'rfc_base';

    $insEmpSql = build_upsert_sql('empleados', $empDataForSql, $empUniqueKeys);
    $insEmp = $pdo->prepare($insEmpSql);
    
    // Debug: guardar SQL en log solo para la primera vez
    static $sql_logged = false;
    if (!$sql_logged) {
        error_log("SQL UPSERT empleados: " . $insEmpSql);
        error_log("Columnas mapeadas: " . implode(', ', array_keys($empDataForSql)));
        $sql_logged = true;
    }

    // Usuarios UPSERT flexible (por no_emp + rfc_base si existe)
    $usrColMap = [
        'no_emp' => ':no_emp',
        'rfc_base' => ':rfc_base',
        'nombre' => ':nombre',
        'nombres' => ':nombre',
        'apellido_paterno' => ':ap',
        'apellido_materno' => ':am',
        'estatus' => ':estatus',
        'empleado_id' => ':empleado_id',
        'password_hash' => ':pass_hash',
        'debe_cambiar_pass' => ':debe_cambiar',
        'pass_cambiada' => ':pass_cambiada',
        'created_at' => 'NOW()',
        'updated_at' => 'NOW()'
    ];

    $usrDataForSql = [];
    foreach ($usrColMap as $col => $ph) {
        if (!isset($colsUsr[$col])) continue;
        $usrDataForSql[$col] = $ph;
    }

    $usrUniqueKeys = ['no_emp'];
    if (isset($colsUsr['rfc_base'])) $usrUniqueKeys[] = 'rfc_base';

    // Importación NO debe resetear contraseñas ni flags de primer acceso.
    $noUpdateUsr = ['password_hash', 'debe_cambiar_pass', 'pass_cambiada'];
    $insUsrSql = build_upsert_sql('usuarios', $usrDataForSql, $usrUniqueKeys, $noUpdateUsr);
    $insUsr = $pdo->prepare($insUsrSql);

    // Para amarrar accesos mínimos necesitamos resolver usuario_id
    $selUsrIdSql = "SELECT usuario_id FROM usuarios WHERE no_emp=:no";
    $selUsrNeedsRfc = false;
    if (isset($colsUsr['rfc_base'])) { $selUsrIdSql .= " AND rfc_base=:rfc"; $selUsrNeedsRfc = true; }
    $selUsrIdSql .= " LIMIT 1";
    $selUsrId = $pdo->prepare($selUsrIdSql);

    $total = 0; $insertados = 0; $actualizados = 0; $errores = 0;
    
    // Recopilar catálogos faltantes
    $missing_puestos = [];
    $missing_adscripciones = [];
    $missing_jefes = [];

    for ($r = 2; $r <= $highestRow; $r++) {
        $empresaNombre = trim((string)$sheet->getCell("A{$r}")->getValue());
        $no_emp = trim((string)$sheet->getCell("B{$r}")->getValue());
        if ($empresaNombre === '' && $no_emp === '') continue;

        $total++;

        $empresa_id = $empMap[$empresaNombre] ?? null;

        $ap = trim((string)$sheet->getCell("C{$r}")->getValue());
        $am = trim((string)$sheet->getCell("D{$r}")->getValue());
        $nombre = trim((string)$sheet->getCell("E{$r}")->getValue());

        $rfc_raw = $sheet->getCell("F{$r}")->getValue();
        $rfc = rfc_base($rfc_raw);
        $curp = trim((string)$sheet->getCell("G{$r}")->getValue());

        $es_activo_raw = $sheet->getCell("H{$r}")->getValue();
        $es_activo = normalize_es_activo($es_activo_raw);

        $fi = parse_date_any($sheet->getCell("I{$r}")->getValue());
        $fb = parse_date_any($sheet->getCell("J{$r}")->getValue());

        $tipo_id = trim((string)$sheet->getCell("K{$r}")->getValue());
        $tipo_nom = trim((string)$sheet->getCell("L{$r}")->getValue());

        $dep_id = trim((string)$sheet->getCell("M{$r}")->getValue());
        $dep_nom = trim((string)$sheet->getCell("N{$r}")->getValue());

        $puesto_id_raw = trim((string)$sheet->getCell("O{$r}")->getValue()); // puede ser HP9
        $puesto_nom = trim((string)$sheet->getCell("P{$r}")->getValue());

        $ct_id_raw = trim((string)$sheet->getCell("Q{$r}")->getValue());
        $ct_id = ($ct_id_raw === '' ? null : (int)$ct_id_raw);
        $ct_nom = trim((string)$sheet->getCell("R{$r}")->getValue());

        $jefe_nom = trim((string)$sheet->getCell("T{$r}")->getValue());
        $salario_mensual_raw = $sheet->getCell("U{$r}")->getValue();
        $salario_diario_raw = $sheet->getCell("V{$r}")->getValue();
        $salario_mensual = parse_money_2($salario_mensual_raw);
        $salario_diario = parse_money_2($salario_diario_raw);

        // Columnas opcionales: Correo (W) y Teléfono (X)
        $correo_excel = '';
        $telefono_excel = '';
        try {
            $correo_excel = trim((string)$sheet->getCell("W{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }
        try {
            $telefono_excel = trim((string)$sheet->getCell("X{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }

        // Columnas adicionales demograficas (Y-AJ)
        $tipo_nomina_excel = '';
        $nss_excel = '';
        $domicilio_calle_excel = '';
        $domicilio_municipio_excel = '';
        $domicilio_colonia_excel = '';
        $domicilio_cp_excel = '';
        $domicilio_estado_excel = '';
        $domicilio_num_ext_excel = '';
        $clabe_excel = '';
        $credito_infonavit_excel = '';
        $unidad_medica_familiar_excel = '';
        $banco_excel = '';

        try {
            $tipo_nomina_excel = trim((string)$sheet->getCell("Y{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }
        try {
            $nss_excel = trim((string)$sheet->getCell("Z{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }
        try {
            $domicilio_calle_excel = trim((string)$sheet->getCell("AA{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }
        try {
            $domicilio_municipio_excel = trim((string)$sheet->getCell("AB{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }
        try {
            $domicilio_colonia_excel = trim((string)$sheet->getCell("AC{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }
        try {
            $domicilio_cp_excel = trim((string)$sheet->getCell("AD{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }
        try {
            $domicilio_estado_excel = trim((string)$sheet->getCell("AE{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }
        try {
            $domicilio_num_ext_excel = trim((string)$sheet->getCell("AF{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }
        try {
            $clabe_excel = trim((string)$sheet->getCell("AG{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }
        try {
            $credito_infonavit_excel = trim((string)$sheet->getCell("AH{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }
        try {
            $unidad_medica_familiar_excel = trim((string)$sheet->getCell("AI{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }
        try {
            $banco_excel = trim((string)$sheet->getCell("AJ{$r}")->getValue());
        } catch (Throwable $e) { /* columna no existe */ }

        $payloadArr = [
            'NOMBRE DE EMPRESA' => $empresaNombre,
            'NUMERO EMPLEADO' => $no_emp,
            'APELLIDO PATERNO EMPLEADO' => $ap,
            'APELLIDO MATERNO EMPLEADO' => $am,
            'NOMBRES DEL EMPLEADO' => $nombre,
            'RFC EMPLEADO_RAW' => (string)$rfc_raw,
            'RFC_BASE' => $rfc,
            'NO. CURP EMPLEADO' => $curp,
            'ES ACTIVO_RAW' => (string)$es_activo_raw,
            'ES ACTIVO' => $es_activo,
            'FECHA INGRESO DEL EMPLEADO' => $fi,
            'FECHA BAJA' => $fb,
            'TIPO EMPLEADO ID_RAW' => $tipo_id,
            'TIPO EMPLEADO' => $tipo_nom,
            'DEPARTAMENTO ID_RAW' => $dep_id,
            'DEPARTAMENTO EMPLEADO' => $dep_nom,
            'PUESTO DEL EMPLEADO ID_RAW' => $puesto_id_raw,
            'PUESTO DEL EMPLEADO' => $puesto_nom,
            'IDENTIFICADOR CENTRO TRABAJO EMPLEADO_RAW' => $ct_id_raw,
            'CENTRO TRABAJO EMPLEADO' => $ct_nom,
            'JEFE INMEDIATO EMPLEADO' => $jefe_nom,
            'SALARIO_DIARIO_RAW' => $salario_diario_raw,
            'SALARIO_MENSUAL_RAW' => $salario_mensual_raw,
            'SALARIO DIARIO EMPLEADO' => $salario_diario,
            'SALARIO MENSUAL EMPLEADO' => $salario_mensual,
            'CORREO' => $correo_excel,
            'TELEFONO' => $telefono_excel,
            'TIPO_NOMINA' => $tipo_nomina_excel,
            'NSS' => $nss_excel,
            'DOMICILIO_CALLE' => $domicilio_calle_excel,
            'DOMICILIO_MUNICIPIO' => $domicilio_municipio_excel,
            'DOMICILIO_COLONIA' => $domicilio_colonia_excel,
            'DOMICILIO_CP' => $domicilio_cp_excel,
            'DOMICILIO_ESTADO' => $domicilio_estado_excel,
            'DOMICILIO_NUM_EXT' => $domicilio_num_ext_excel,
            'CLABE' => $clabe_excel,
            'CREDITO_INFONAVIT' => $credito_infonavit_excel,
            'UNIDAD_MEDICA_FAMILIAR' => $unidad_medica_familiar_excel,
            'BANCO' => $banco_excel,
            '_BUSQUEDA_adscripcion_id' => $adscripcion_id_real,
            '_BUSQUEDA_puesto_id' => $puesto_id_real,
            '_BUSQUEDA_unidad_id' => $unidad_id_real,
            '_BUSQUEDA_jefe_no_emp' => $jefe_no_emp_real
        ];
        $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

        if (!$empresa_id) {
            $errores++;
            $insDet->execute([
                ':iid'=>$import_id,
                ':no'=>$no_emp,
                ':rfc'=>($rfc !== '' ? $rfc : 'SINRFCBASE'),
                ':payload'=>$payload,
                ':acc'=>'error',
                ':msg'=>'Empresa no existe en catálogo empresas'
            ]);
            continue;
        }

        // Existe empleado?
        $selParams = [':eid'=>$empresa_id, ':no'=>$no_emp];
        if ($selEmpNeedsRfc) $selParams[':rfc'] = $rfc;
        $selEmp->execute($selParams);
        $emp = $selEmp->fetch(PDO::FETCH_ASSOC);

        // Buscar adscripcion_id usando dep_id (columna M) + empresa_id
        $adscripcion_id_real = null;
        if ($dep_id !== '') {
            try {
                $stmtAdscripcion = $pdo->prepare("
                    SELECT adscripcion_id 
                    FROM org_adscripciones 
                    WHERE clave = ? AND empresa_id = ? AND estatus = 1 
                    LIMIT 1
                ");
                $stmtAdscripcion->execute([$dep_id, $empresa_id]);
                $rowAdsc = $stmtAdscripcion->fetch(PDO::FETCH_ASSOC);
                if ($rowAdsc) {
                    $adscripcion_id_real = (int)$rowAdsc['adscripcion_id'];
                } else if ($dep_id !== '') {
                    // Registrar adscripción faltante
                    $key = "{$dep_id} - {$dep_nom} (EMP: {$empresa_id})";
                    $missing_adscripciones[$key] = true;
                }
            } catch (Throwable $e) {
                // Tabla org_adscripciones no existe o error
            }
        }

        // Buscar puesto_id y unidad_id por nombre del puesto (columna P: puesto_nom)
        // Este es el mapeo más confiable después de la migración
        $puesto_id_real = null;
        $unidad_id_real = null;
        
        if ($puesto_nom !== '') {
            try {
                // Búsqueda exacta por nombre
                $stmtPuesto = $pdo->prepare("
                    SELECT puesto_id, unidad_id 
                    FROM org_puestos 
                    WHERE UPPER(TRIM(nombre)) = UPPER(TRIM(?)) AND empresa_id = ? AND estatus = 1 
                    LIMIT 1
                ");
                $stmtPuesto->execute([$puesto_nom, $empresa_id]);
                $rowPuesto = $stmtPuesto->fetch(PDO::FETCH_ASSOC);
                
                // Si no encuentra, intentar con LIKE (para variaciones menores)
                if (!$rowPuesto) {
                    $stmtPuesto = $pdo->prepare("
                        SELECT puesto_id, unidad_id 
                        FROM org_puestos 
                        WHERE nombre LIKE CONCAT('%', TRIM(?), '%') AND empresa_id = ? AND estatus = 1 
                        LIMIT 1
                    ");
                    $stmtPuesto->execute([$puesto_nom, $empresa_id]);
                    $rowPuesto = $stmtPuesto->fetch(PDO::FETCH_ASSOC);
                }
                
                if ($rowPuesto) {
                    $puesto_id_real = (int)$rowPuesto['puesto_id'];
                    $unidad_id_real = $rowPuesto['unidad_id'] ? (int)$rowPuesto['unidad_id'] : null;
                } else if ($puesto_nom !== '') {
                    // Registrar puesto faltante
                    $key = strtoupper(trim($puesto_nom)) . " (EMP: {$empresa_id})";
                    $missing_puestos[$key] = true;
                }
            } catch (Throwable $e) {
                // Tabla org_puestos no existe o error
            }
        }
        
        // Fallback: intentar por puesto_id_raw (columna O) si aún no tenemos puesto_id
        if ($puesto_id_real === null && $puesto_id_raw !== '') {
            try {
                $stmtPuesto = $pdo->prepare("
                    SELECT puesto_id, unidad_id 
                    FROM org_puestos 
                    WHERE codigo = ? AND empresa_id = ? AND estatus = 1 
                    LIMIT 1
                ");
                $stmtPuesto->execute([$puesto_id_raw, $empresa_id]);
                $rowPuesto = $stmtPuesto->fetch(PDO::FETCH_ASSOC);
                if ($rowPuesto) {
                    $puesto_id_real = (int)$rowPuesto['puesto_id'];
                    $unidad_id_real = $rowPuesto['unidad_id'] ? (int)$rowPuesto['unidad_id'] : null;
                }
            } catch (Throwable $e) {
                // Tabla org_puestos no existe o error
            }
        }

        // Buscar jefe_no_emp por nombre del jefe (columna T)
        $jefe_no_emp_real = null;
        if ($jefe_nom !== '') {
            try {
                // Limpiar y normalizar el nombre del jefe
                $jefe_nom_limpio = strtoupper(trim($jefe_nom));
                
                // Intentar búsqueda exacta primero
                $stmtJefe = $pdo->prepare("
                    SELECT no_emp 
                    FROM empleados 
                    WHERE empresa_id = ?
                    AND UPPER(CONCAT(nombre, ' ', apellido_paterno, ' ', COALESCE(apellido_materno, ''))) = ?
                    LIMIT 1
                ");
                $stmtJefe->execute([$empresa_id, $jefe_nom_limpio]);
                $rowJefe = $stmtJefe->fetch(PDO::FETCH_ASSOC);
                
                // Si no encuentra, intentar con LIKE
                if (!$rowJefe) {
                    $stmtJefe = $pdo->prepare("
                        SELECT no_emp 
                        FROM empleados 
                        WHERE empresa_id = ?
                        AND UPPER(CONCAT(nombre, ' ', apellido_paterno, ' ', COALESCE(apellido_materno, ''))) LIKE ?
                        LIMIT 1
                    ");
                    $stmtJefe->execute([$empresa_id, '%' . $jefe_nom_limpio . '%']);
                    $rowJefe = $stmtJefe->fetch(PDO::FETCH_ASSOC);
                }
                
                if ($rowJefe) {
                    $jefe_no_emp_real = $rowJefe['no_emp'];
                } else if ($jefe_nom !== '') {
                    // Registrar jefe no encontrado
                    $key = "{$jefe_nom} (EMP: {$empresa_id})";
                    $missing_jefes[$key] = true;
                }
            } catch (Throwable $e) {
                // Error en búsqueda de jefe
            }
        }

        // Params empleados (solo placeholders presentes)
        $empParams = [];
        foreach ($empDataForSql as $col => $ph) {
            if ($ph === 'NOW()') continue;

            switch ($ph) {
                case ':empresa_id': $empParams[':empresa_id'] = $empresa_id; break;
                case ':no_emp': $empParams[':no_emp'] = $no_emp; break;
                case ':rfc_base': $empParams[':rfc_base'] = $rfc; break;
                case ':curp': $empParams[':curp'] = ($curp !== '' ? $curp : null); break;
                case ':nombre': $empParams[':nombre'] = ($nombre !== '' ? $nombre : null); break;
                case ':ap': $empParams[':ap'] = ($ap !== '' ? $ap : null); break;
                case ':am': $empParams[':am'] = ($am !== '' ? $am : null); break;
                case ':es_activo': $empParams[':es_activo'] = $es_activo; break;
                case ':correo': $empParams[':correo'] = ($correo_excel !== '' ? $correo_excel : null); break;
                case ':telefono': $empParams[':telefono'] = ($telefono_excel !== '' ? $telefono_excel : null); break;
                case ':unidad_id': $empParams[':unidad_id'] = $unidad_id_real; break;
                case ':adscripcion_id': $empParams[':adscripcion_id'] = $adscripcion_id_real; break;
                case ':puesto_id': $empParams[':puesto_id'] = $puesto_id_real; break;
                case ':jefe_no_emp': $empParams[':jefe_no_emp'] = $jefe_no_emp_real; break;
                case ':fi': $empParams[':fi'] = $fi; break;
                case ':fb': $empParams[':fb'] = $fb; break;
                case ':tipo_id': $empParams[':tipo_id'] = ($tipo_id !== '' ? $tipo_id : null); break;
                case ':tipo_nom': $empParams[':tipo_nom'] = ($tipo_nom !== '' ? $tipo_nom : null); break;
                case ':dep_id': $empParams[':dep_id'] = ($dep_id !== '' ? $dep_id : null); break;
                case ':dep_nom': $empParams[':dep_nom'] = ($dep_nom !== '' ? $dep_nom : null); break;

                case ':puesto_nomina_id': $empParams[':puesto_nomina_id'] = ($puesto_id_raw !== '' ? $puesto_id_raw : null); break;
                case ':puesto_nomina': $empParams[':puesto_nomina'] = ($puesto_nom !== '' ? $puesto_nom : null); break;

                case ':puesto_del_id': $empParams[':puesto_del_id'] = ($puesto_id_raw !== '' ? $puesto_id_raw : null); break;
                case ':puesto_del': $empParams[':puesto_del'] = ($puesto_nom !== '' ? $puesto_nom : null); break;

                case ':ct_id': $empParams[':ct_id'] = $ct_id; break;
                case ':ct_nom': $empParams[':ct_nom'] = ($ct_nom !== '' ? $ct_nom : null); break;
                case ':jefe_nom': $empParams[':jefe_nom'] = ($jefe_nom !== '' ? $jefe_nom : null); break;
                case ':sd': $empParams[':sd'] = $salario_diario; break;
                case ':sm': $empParams[':sm'] = $salario_mensual; break;
                // empleados.estatus es ENUM ('activo','baja','suspendido')
                case ':empresa_nombre': $empParams[':empresa_nombre'] = ($empresaNombre !== '' ? $empresaNombre : null); break;
                case ':estatus': $empParams[':estatus'] = 'activo'; break;
            }
        }

        $insEmp->execute($empParams);

        // Re-lee empleado_id
        $selEmp->execute($selParams);
        $emp2 = $selEmp->fetch(PDO::FETCH_ASSOC);
        $empleado_id = $emp2 ? (int)$emp2['empleado_id'] : null;

        $accion = (!$emp ? 'insert' : 'update');
        if (!$emp) $insertados++; else $actualizados++;

        // Usuarios upsert (si existe)
        // Contraseña inicial: Número de empleado
        $pass_plain = $no_emp;
        $pass_hash = password_hash($pass_plain, PASSWORD_DEFAULT);

        $usrParams = [];
        foreach ($usrDataForSql as $col => $ph) {
            if ($ph === 'NOW()') continue;

            switch ($ph) {
                case ':no_emp': $usrParams[':no_emp'] = $no_emp; break;
                case ':rfc_base': $usrParams[':rfc_base'] = $rfc; break;
                case ':nombre': $usrParams[':nombre'] = ($nombre !== '' ? $nombre : null); break;
                case ':ap': $usrParams[':ap'] = ($ap !== '' ? $ap : null); break;
                case ':am': $usrParams[':am'] = ($am !== '' ? $am : null); break;
                // usuarios.estatus es ENUM ('activo','inactivo','baja')
                case ':estatus': $usrParams[':estatus'] = 'activo'; break;
                case ':empleado_id': $usrParams[':empleado_id'] = $empleado_id; break;
                case ':pass_hash': $usrParams[':pass_hash'] = $pass_hash; break;
                case ':debe_cambiar': $usrParams[':debe_cambiar'] = 1; break;
                case ':pass_cambiada': $usrParams[':pass_cambiada'] = 0; break;
            }
        }
        $insUsr->execute($usrParams);

        // Accesos mínimos por empleado (roles + vínculo empresa)
        if ($empresa_id !== null) {
            $selUsrParams = [':no'=>$no_emp];
            if ($selUsrNeedsRfc) $selUsrParams[':rfc'] = $rfc;
            $selUsrId->execute($selUsrParams);
            $u = $selUsrId->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                ensure_accesos_minimos($pdo, (int)$u['usuario_id'], (int)$empresa_id, $empleado_id);
            }
        }

        // Insertar/actualizar empleados_demograficos (validación dinámica de columnas)
        if ($empleado_id !== null) {
            try {
                // Verificar qué columnas existen realmente en empleados_demograficos
                static $colsDemograficos = null;
                if ($colsDemograficos === null) {
                    $colsDemograficos = table_columns($pdo, 'empleados_demograficos');
                }

                // Convertir credito_infonavit a 1/0
                $tiene_credito_infonavit = null;
                if ($credito_infonavit_excel !== '') {
                    $credito_upper = strtoupper($credito_infonavit_excel);
                    if (in_array($credito_upper, ['SI', 'SÍ', 'S', '1', 'YES'])) {
                        $tiene_credito_infonavit = 1;
                    } elseif (in_array($credito_upper, ['NO', 'N', '0'])) {
                        $tiene_credito_infonavit = 0;
                    }
                }

                // Buscar banco_id por nombre
                $banco_id_val = null;
                if ($banco_excel !== '') {
                    static $bancos_cache = null;
                    if ($bancos_cache === null) {
                        $bancos_cache = [];
                        $stmtBancos = $pdo->query("SELECT banco_id, nombre FROM cat_bancos");
                        while ($row = $stmtBancos->fetch(PDO::FETCH_ASSOC)) {
                            $bancos_cache[strtoupper($row['nombre'])] = $row['banco_id'];
                        }
                    }
                    $banco_upper = strtoupper(trim($banco_excel));
                    if (isset($bancos_cache[$banco_upper])) {
                        $banco_id_val = $bancos_cache[$banco_upper];
                    }
                }

                // Mapa de datos demograficos a insertar
                $demogData = [
                    'empleado_id' => $empleado_id,
                    'correo' => ($correo_excel !== '' ? $correo_excel : null),
                    'telefono' => ($telefono_excel !== '' ? $telefono_excel : null),
                    'tipo_nomina' => ($tipo_nomina_excel !== '' ? $tipo_nomina_excel : null),
                    'nss' => ($nss_excel !== '' ? $nss_excel : null),
                    'domicilio_calle' => ($domicilio_calle_excel !== '' ? $domicilio_calle_excel : null),
                    'domicilio_municipio' => ($domicilio_municipio_excel !== '' ? $domicilio_municipio_excel : null),
                    'domicilio_colonia' => ($domicilio_colonia_excel !== '' ? $domicilio_colonia_excel : null),
                    'domicilio_cp' => ($domicilio_cp_excel !== '' ? $domicilio_cp_excel : null),
                    'domicilio_estado' => ($domicilio_estado_excel !== '' ? $domicilio_estado_excel : null),
                    'domicilio_num_ext' => ($domicilio_num_ext_excel !== '' ? $domicilio_num_ext_excel : null),
                    'clabe' => ($clabe_excel !== '' ? $clabe_excel : null),
                    'tiene_credito_infonavit' => $tiene_credito_infonavit,
                    'unidad_medica_familiar' => ($unidad_medica_familiar_excel !== '' ? $unidad_medica_familiar_excel : null),
                    'banco_id' => $banco_id_val
                ];

                // Filtrar solo columnas que existen
                $insertCols = [];
                $insertVals = [];
                $insertParams = [];
                foreach ($demogData as $col => $val) {
                    if (isset($colsDemograficos[$col])) {
                        $insertCols[] = $col;
                        $insertVals[] = ':' . $col;
                        $insertParams[':' . $col] = $val;
                    }
                }

                if (count($insertCols) > 0) {
                    // Construir UPDATE dinámico (solo columnas que no sean PK)
                    $updates = [];
                    foreach ($insertCols as $col) {
                        if ($col === 'empleado_id') continue;
                        $updates[] = "$col = COALESCE(VALUES($col), $col)";
                    }

                    $sql = "INSERT INTO empleados_demograficos (" . implode(', ', $insertCols) . ") 
                            VALUES (" . implode(', ', $insertVals) . ")";
                    
                    if (count($updates) > 0) {
                        $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
                    }

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($insertParams);
                }
            } catch (Throwable $e) {
                // Log detallado del error
                $msg = "Error demograficos empleado {$empleado_id} (no_emp={$no_emp}): " . $e->getMessage();
                error_log($msg);
                // Agregar al detalle de importación para visibilidad
                $insDet->execute([
                    ':iid'=>$import_id,
                    ':no'=>$no_emp,
                    ':rfc'=>($rfc !== '' ? $rfc : 'SINRFCBASE'),
                    ':payload'=>json_encode(['error_demograficos'=>$msg], JSON_UNESCAPED_UNICODE),
                    ':acc'=>'warning',
                    ':msg'=>'Datos básicos OK, error en demograficos'
                ]);
            }
        }

        // detalle OK
        $insDet->execute([
            ':iid'=>$import_id,
            ':no'=>$no_emp,
            ':rfc'=>($rfc !== '' ? $rfc : 'SINRFCBASE'),
            ':payload'=>$payload,
            ':acc'=>$accion,
            ':msg'=>'OK'
        ]);
    }

    // Construir resumen de advertencias
    $warnings_detail = [];
    
    if (!empty($missing_puestos)) {
        $warnings_detail[] = "PUESTOS FALTANTES (" . count($missing_puestos) . "): " . implode("; ", array_keys($missing_puestos));
    }
    if (!empty($missing_adscripciones)) {
        $warnings_detail[] = "ADSCRIPCIONES FALTANTES (" . count($missing_adscripciones) . "): " . implode("; ", array_keys($missing_adscripciones));
    }
    if (!empty($missing_jefes)) {
        $warnings_detail[] = "JEFES NO ENCONTRADOS (" . count($missing_jefes) . "): " . implode("; ", array_keys($missing_jefes));
    }
    
    // Cierre importación
    $msgResumen = "Total={$total}; Insertados={$insertados}; Actualizados={$actualizados}; Errores={$errores}";
    if (!empty($warnings_detail)) {
        $msgResumen .= " | ADVERTENCIAS: " . implode(" | ", $warnings_detail);
    }
    
    $stmt = $pdo->prepare("
        UPDATE nomina_importaciones
        SET total_registros=:t, status='procesado', mensaje=:msg
        WHERE import_id=:iid
    ");
    $stmt->execute([':t'=>$total, ':msg'=>$msgResumen, ':iid'=>$import_id]);

    $pdo->commit();
    // Guardar warnings en sesión para mostrar en página de éxito
    $_SESSION['import_warnings'] = [
        'puestos' => $missing_puestos,
        'adscripciones' => $missing_adscripciones,
        'jefes' => $missing_jefes
    ];
    header('Location: importar_nomina.php?ok=1');
    exit;

} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();

    $log = __DIR__ . '/../storage/logs/import_nomina_' . date('Ymd') . '.log';
    error_log(date('[Y-m-d H:i:s] ') . $ex->getMessage() . PHP_EOL, 3, $log);

    die("ERROR IMPORTACIÓN: " . $ex->getMessage() . " " . $ex->getFile() . ":" . $ex->getLine() . " Log: " . $log);
}
