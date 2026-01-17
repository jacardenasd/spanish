<?php
// public/contratos_gestionar.php
// Gestión de empleado en proceso de contratación: captura de datos + generación de PDFs
// Usa tabla: empleados_nuevo_ingreso

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();
require_empresa();

$empresa_id = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;
$usuario_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
$nuevo_ingreso_id = isset($_GET['nuevo_ingreso_id']) ? (int)$_GET['nuevo_ingreso_id'] : 0;
$empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;

// Validar que tenga uno de los dos parámetros
if ($nuevo_ingreso_id <= 0 && $empleado_id <= 0) {
    die('Error: ID de nuevo ingreso o empleado requerido. Accede desde la lista de empleados.');
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ========== POST ACTIONS ==========
$accion = isset($_POST['accion']) ? (string)$_POST['accion'] : '';

if ($accion === 'guardar_datos_empleado') {
    header('Content-Type: application/json; charset=utf-8');
    
    $campos = array(
        'nombre' => '', 'apellido_paterno' => '', 'apellido_materno' => '', 'sexo' => '',
        'fecha_nacimiento' => '', 'nacionalidad' => '', 'lugar_nacimiento' => '',
        'rfc' => '', 'curp' => '', 'nss' => '',
        'domicilio_calle' => '', 'domicilio_num_ext' => '', 'domicilio_num_int' => '',
        'domicilio_cp' => '', 'domicilio_estado' => '', 'domicilio_municipio' => '', 'domicilio_colonia' => '',
        'apoderado_legal_id' => 0, 'fecha_alta' => '', 'tipo_nomina' => '',
        'sueldo_diario' => 0.0, 'sueldo_mensual' => 0.0, 'sueldo_integrado' => 0.0,
        'banco_id' => 0, 'numero_cuenta' => '', 'clabe' => '',
        'correo_empresa' => '', 'correo_personal' => '', 'escolaridad_id' => 0,
        'telefono_personal' => '', 'telefono_empresa' => '',
        'unidad_medica_familiar' => '', 'tiene_credito_infonavit' => ''
    );
    
    foreach ($campos as $campo => &$valor) {
        if (isset($_POST[$campo])) {
            if (in_array($campo, array('apoderado_legal_id', 'banco_id', 'escolaridad_id'), true)) {
                $valor = (int)$_POST[$campo];
            } elseif (in_array($campo, array('sueldo_diario', 'sueldo_mensual', 'sueldo_integrado'), true)) {
                $valor = (float)$_POST[$campo];
            } else {
                $valor = (string)$_POST[$campo];
            }
        }
    }
    
    $datosCompletos = (!empty($campos['rfc']) && !empty($campos['curp']) && !empty($campos['nss']) && 
                       $campos['sueldo_mensual'] > 0 && $campos['banco_id'] > 0) ? 1 : 0;
    
    // Determinar tabla y WHERE según el tipo de empleado
    if ($nuevo_ingreso_id > 0) {
        // Nuevo ingreso
        $sqlUpsert = "UPDATE empleados_nuevo_ingreso SET
                        nombre = :nombre, apellido_paterno = :ap, apellido_materno = :am, sexo = :sexo, 
                        fecha_nacimiento = :fn, nacionalidad = :nac, lugar_nacimiento = :ln, rfc = :rfc, curp = :curp, nss = :nss,
                        domicilio_calle = :dcalle, domicilio_num_ext = :dnext, domicilio_num_int = :dnint,
                        domicilio_cp = :dcp, domicilio_estado = :dest, domicilio_municipio = :dmun, domicilio_colonia = :dcol,
                        apoderado_legal_id = :apod, fecha_alta = :falta, tipo_nomina = :tnomina,
                        sueldo_diario = :sdiario, sueldo_mensual = :smensual, sueldo_integrado = :sintegrado,
                        banco_id = :banco, numero_cuenta = :ncta, clabe = :clabe, correo_empresa = :cemp,
                        correo_personal = :cpers, escolaridad_id = :esc, telefono_personal = :tpers,
                        telefono_empresa = :temp, unidad_medica_familiar = :umf, tiene_credito_infonavit = :infonavit,
                        datos_completos = :completo, estatus = 'en_proceso', fecha_actualizacion = NOW()
                      WHERE nuevo_ingreso_id = :nid AND empresa_id = :emp";
        $bindParams = array(':nid' => $nuevo_ingreso_id, ':emp' => $empresa_id);
    } else {
        // Empleado activo -> actualizar empleados_demograficos
        $sqlUpsert = "UPDATE empleados_demograficos SET
                        nombre = :nombre, apellido_paterno = :ap, apellido_materno = :am, sexo = :sexo, 
                        fecha_nacimiento = :fn, nacionalidad = :nac, lugar_nacimiento = :ln, rfc = :rfc, curp = :curp, nss = :nss,
                        domicilio_calle = :dcalle, domicilio_num_ext = :dnext, domicilio_num_int = :dnint,
                        domicilio_cp = :dcp, domicilio_estado = :dest, domicilio_municipio = :dmun, domicilio_colonia = :dcol,
                        apoderado_legal_id = :apod, fecha_alta = :falta, tipo_nomina = :tnomina,
                        sueldo_diario = :sdiario, sueldo_mensual = :smensual, sueldo_integrado = :sintegrado,
                        banco_id = :banco, numero_cuenta = :ncta, clabe = :clabe, correo_empresa = :cemp,
                        correo_personal = :cpers, escolaridad_id = :esc, telefono_personal = :tpers,
                        telefono_empresa = :temp, unidad_medica_familiar = :umf, tiene_credito_infonavit = :infonavit,
                        datos_completos = :completo, fecha_actualizacion = NOW()
                      WHERE empleado_id = :eid";
        $bindParams = array(':eid' => $empleado_id);
    }
    
    $bindParams = array_merge($bindParams, array(
        ':nombre' => $campos['nombre'], ':ap' => $campos['apellido_paterno'],
        ':am' => $campos['apellido_materno'], ':sexo' => $campos['sexo'], ':fn' => $campos['fecha_nacimiento'],
        ':nac' => $campos['nacionalidad'], ':ln' => $campos['lugar_nacimiento'], ':rfc' => $campos['rfc'],
        ':curp' => $campos['curp'], ':nss' => $campos['nss'], ':dcalle' => $campos['domicilio_calle'],
        ':dnext' => $campos['domicilio_num_ext'], ':dnint' => $campos['domicilio_num_int'],
        ':dcp' => $campos['domicilio_cp'], ':dest' => $campos['domicilio_estado'],
        ':dmun' => $campos['domicilio_municipio'], ':dcol' => $campos['domicilio_colonia'],
        ':apod' => $campos['apoderado_legal_id'], ':falta' => $campos['fecha_alta'],
        ':tnomina' => $campos['tipo_nomina'], ':sdiario' => $campos['sueldo_diario'],
        ':smensual' => $campos['sueldo_mensual'], ':sintegrado' => $campos['sueldo_integrado'],
        ':banco' => $campos['banco_id'], ':ncta' => $campos['numero_cuenta'], ':clabe' => $campos['clabe'],
        ':cemp' => $campos['correo_empresa'], ':cpers' => $campos['correo_personal'],
        ':esc' => $campos['escolaridad_id'], ':tpers' => $campos['telefono_personal'],
        ':temp' => $campos['telefono_empresa'], ':umf' => $campos['unidad_medica_familiar'],
        ':infonavit' => $campos['tiene_credito_infonavit'], ':completo' => $datosCompletos
    ));
    
    $stUpsert = $pdo->prepare($sqlUpsert);
    $result = $stUpsert->execute($bindParams);
    
    if ($result) {
        echo json_encode(array('ok' => true, 'mensaje' => 'Datos guardados', 'datos_completos' => $datosCompletos));
    } else {
        echo json_encode(array('ok' => false, 'error' => 'Error al guardar'));
    }
    exit;
}

// ========== ELIMINAR DOCUMENTO ==========
if ($accion === 'eliminar_documento') {
    header('Content-Type: application/json; charset=utf-8');
    
    $documento_id = isset($_POST['documento_id']) ? (int)$_POST['documento_id'] : 0;
    
    if ($documento_id <= 0) {
        echo json_encode(array('ok' => false, 'error' => 'ID de documento inválido'));
        exit;
    }
    
    // Validar que el documento pertenece a esta empresa
    $sqlCheck = "SELECT documento_id FROM contratos_documentos 
                 WHERE documento_id = :doc_id AND empresa_id = :emp_id LIMIT 1";
    $stCheck = $pdo->prepare($sqlCheck);
    $stCheck->execute(array(':doc_id' => $documento_id, ':emp_id' => $empresa_id));
    
    if (!$stCheck->fetch()) {
        echo json_encode(array('ok' => false, 'error' => 'Documento no encontrado o no tiene permiso'));
        exit;
    }
    
    // Eliminar el documento
    $sqlDelete = "DELETE FROM contratos_documentos WHERE documento_id = :doc_id AND empresa_id = :emp_id";
    $stDelete = $pdo->prepare($sqlDelete);
    $result = $stDelete->execute(array(':doc_id' => $documento_id, ':emp_id' => $empresa_id));
    
    if ($result) {
        echo json_encode(array('ok' => true, 'mensaje' => 'Documento eliminado'));
    } else {
        echo json_encode(array('ok' => false, 'error' => 'Error al eliminar documento'));
    }
    exit;
}

// ========== CARGAR DATOS ==========
$empDatos = null;
$es_nuevo_ingreso = false;

if ($nuevo_ingreso_id > 0) {
    // Cargar desde empleados_nuevo_ingreso
    $sqlDatos = "SELECT * FROM empleados_nuevo_ingreso WHERE nuevo_ingreso_id = :n AND empresa_id = :e LIMIT 1";
    $stDatos = $pdo->prepare($sqlDatos);
    $stDatos->execute(array(':n' => $nuevo_ingreso_id, ':e' => $empresa_id));
    $empDatos = $stDatos->fetch(PDO::FETCH_ASSOC);
    
    if (!$empDatos) {
        die('Error: Empleado de nuevo ingreso no encontrado');
    }
    $es_nuevo_ingreso = true;
} elseif ($empleado_id > 0) {
    // Cargar desde empleados para importar
    $sqlEmp = "SELECT * FROM empleados WHERE empleado_id = :e AND empresa_id = :emp AND es_activo = 1 LIMIT 1";
    $stEmp = $pdo->prepare($sqlEmp);
    $stEmp->execute(array(':e' => $empleado_id, ':emp' => $empresa_id));
    $empBase = $stEmp->fetch(PDO::FETCH_ASSOC);
    
    if (!$empBase) {
        die('Error: Empleado no encontrado');
    }
    
    // Intentar cargar datos demográficos existentes
    $sqlDemo = "SELECT * FROM empleados_demograficos WHERE empleado_id = :e LIMIT 1";
    $stDemo = $pdo->prepare($sqlDemo);
    $stDemo->execute(array(':e' => $empleado_id));
    $empDemo = $stDemo->fetch(PDO::FETCH_ASSOC);
    
    // Mapear datos de empleados + demograficos a formato de empleados_nuevo_ingreso
    $empDatos = array(
        'empleado_id' => $empBase['empleado_id'],
        'empresa_id' => $empBase['empresa_id'],
        'rfc' => $empBase['rfc_base'],
        'curp' => $empBase['curp'] ?: '',
        'nss' => $empDemo ? ($empDemo['nss'] ?: '') : '',
        'nombre' => $empBase['nombre'],
        'apellido_paterno' => $empBase['apellido_paterno'] ?: '',
        'apellido_materno' => $empBase['apellido_materno'] ?: '',
        'fecha_alta' => $empBase['fecha_ingreso'] ?: date('Y-m-d'),
        'sueldo_diario' => $empDemo ? ($empDemo['sueldo_diario'] ?: $empBase['salario_diario']) : ($empBase['salario_diario'] ?: 0),
        'sueldo_mensual' => $empDemo ? ($empDemo['sueldo_mensual'] ?: $empBase['salario_mensual']) : ($empBase['salario_mensual'] ?: 0),
        'puesto_nombre' => $empBase['puesto_nombre'] ?: '',
        'datos_completos' => $empDemo ? ($empDemo['datos_completos'] ?: 0) : 0,
        'sexo' => $empDemo ? ($empDemo['sexo'] ?: '') : '',
        'fecha_nacimiento' => $empDemo ? ($empDemo['fecha_nacimiento'] ?: '') : '',
        'nacionalidad' => $empDemo ? ($empDemo['nacionalidad'] ?: '') : '',
        'lugar_nacimiento' => $empDemo ? ($empDemo['lugar_nacimiento'] ?: '') : '',
        'domicilio_calle' => $empDemo ? ($empDemo['domicilio_calle'] ?: '') : '',
        'domicilio_num_ext' => $empDemo ? ($empDemo['domicilio_num_ext'] ?: '') : '',
        'domicilio_num_int' => $empDemo ? ($empDemo['domicilio_num_int'] ?: '') : '',
        'domicilio_cp' => $empDemo ? ($empDemo['domicilio_cp'] ?: '') : '',
        'domicilio_estado' => $empDemo ? ($empDemo['domicilio_estado'] ?: '') : '',
        'domicilio_municipio' => $empDemo ? ($empDemo['domicilio_municipio'] ?: '') : '',
        'domicilio_colonia' => $empDemo ? ($empDemo['domicilio_colonia'] ?: '') : '',
        'apoderado_legal_id' => $empDemo ? ($empDemo['apoderado_legal_id'] ?: 0) : 0,
        'tipo_nomina' => $empDemo ? ($empDemo['tipo_nomina'] ?: '') : '',
        'sueldo_integrado' => $empDemo ? ($empDemo['sueldo_integrado'] ?: 0) : 0,
        'banco_id' => $empDemo ? ($empDemo['banco_id'] ?: 0) : 0,
        'numero_cuenta' => $empDemo ? ($empDemo['numero_cuenta'] ?: '') : '',
        'clabe' => $empDemo ? ($empDemo['clabe'] ?: '') : '',
        'correo_empresa' => $empDemo ? ($empDemo['correo_empresa'] ?: ($empBase['correo'] ?? '')) : ($empBase['correo'] ?? ''),
        'correo_personal' => $empDemo ? ($empDemo['correo_personal'] ?: '') : '',
        'escolaridad_id' => $empDemo ? ($empDemo['escolaridad_id'] ?: 0) : 0,
        'telefono_personal' => $empDemo ? ($empDemo['telefono_personal'] ?: ($empBase['telefono'] ?? '')) : ($empBase['telefono'] ?? ''),
        'telefono_empresa' => $empDemo ? ($empDemo['telefono_empresa'] ?: '') : '',
        'unidad_medica_familiar' => $empDemo ? ($empDemo['unidad_medica_familiar'] ?: '') : '',
        'tiene_credito_infonavit' => $empDemo ? ($empDemo['tiene_credito_infonavit'] ?: '') : ''
    );
    $es_nuevo_ingreso = false;
}

if (!$empDatos) {
    die('Error: No se encontraron datos del empleado');
}

$nombre_empleado = trim(($empDatos['nombre'] . ' ' . $empDatos['apellido_paterno'] . ' ' . $empDatos['apellido_materno']));

// Para generación de PDFs, cargar posibles contratos activos de empleados existentes
$contratos = array();
if ($empleado_id > 0) {
    $sqlCons = "SELECT * FROM contratos WHERE empleado_id = :e AND empresa_id = :emp ORDER BY fecha_inicio DESC";
    $stCons = $pdo->prepare($sqlCons);
    $stCons->execute(array(':e' => $empleado_id, ':emp' => $empresa_id));
    $contratos = $stCons->fetchAll(PDO::FETCH_ASSOC);
}

$active_menu = 'contratos_generar';
$page_title = 'Gestionar - ' . $nombre_empleado;
require_once __DIR__ . '/../includes/layout/head.php';
require_once __DIR__ . '/../includes/layout/navbar.php';
require_once __DIR__ . '/../includes/layout/sidebar.php';
require_once __DIR__ . '/../includes/layout/content_open.php';
?>

<div class="page-header page-header-light">
  <div class="page-header-content">
    <div class="page-title d-flex">
      <h4><i class="icon-user mr-2"></i> <?php echo h($nombre_empleado ?: 'Empleado'); ?></h4>
      <a href="contratos_generar.php" class="btn btn-light btn-sm ml-auto">
        <i class="icon-arrow-left13"></i> Volver a Lista
      </a>
    </div>
  </div>
</div>

<div class="content">
  
  <!-- Resumen -->
  <div class="card">
    <div class="card-header">
      <h6 class="card-title">Resumen</h6>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-3"><p class="mb-0 text-muted">RFC</p><p class="font-weight-semibold"><?php echo h($empDatos['rfc']) ?: '-'; ?></p></div>
        <div class="col-md-3"><p class="mb-0 text-muted">CURP</p><p class="font-weight-semibold"><?php echo h($empDatos['curp']) ?: '-'; ?></p></div>
        <div class="col-md-2"><p class="mb-0 text-muted">NSS</p><p class="font-weight-semibold"><?php echo h($empDatos['nss']) ?: '-'; ?></p></div>
        <div class="col-md-2"><p class="mb-0 text-muted">Estado</p>
          <?php if ($empDatos['datos_completos'] == 1): ?>
            <span class="badge badge-success">Completo</span>
          <?php else: ?>
            <span class="badge badge-warning">Incompleto</span>
          <?php endif; ?>
        </div>
        <div class="col-md-2 text-right">
          <button class="btn btn-sm btn-light" onclick="$('#formCapturaDatos').slideToggle();"><i class="icon-pencil5"></i> Editar</button>
          <?php if ($empDatos['datos_completos'] == 1): ?>
            <button class="btn btn-sm btn-success" onclick="abrirModalGenerarDoc();"><i class="icon-file-pdf"></i> Generar Documentos</button>
          <?php else: ?>
            <button class="btn btn-sm btn-secondary" disabled title="Complete los datos primero"><i class="icon-file-pdf"></i> Generar Documentos</button>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!empty($empDatos['fecha_inicio_contrato'])): ?>
        <div class="row mt-3">
          <div class="col-md-3"><p class="mb-0 text-muted">Fecha Inicio Contrato</p><p class="font-weight-semibold"><?php echo date('d/m/Y', strtotime($empDatos['fecha_inicio_contrato'])); ?></p></div>
          <div class="col-md-3"><p class="mb-0 text-muted">Fecha Término Contrato</p><p class="font-weight-semibold"><?php echo !empty($empDatos['fecha_termino_contrato']) ? date('d/m/Y', strtotime($empDatos['fecha_termino_contrato'])) : 'No especificada'; ?></p></div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tabla de Documentos Generados -->
  <div class="card">
    <div class="card-header">
      <h6 class="card-title">Historial de Documentos Generados</h6>
    </div>
    <div class="card-body">
      <?php
      // Obtener documentos generados
      $sqlDocs = "SELECT documento_id, tipo_documento, nombre_archivo, fecha_generacion, tamanio 
                  FROM contratos_documentos 
                  WHERE empresa_id = :emp";
      
      if ($nuevo_ingreso_id > 0) {
          // Para nuevos ingresos, buscar por RFC (ya que empleado_id aún es 0)
          $rfcEmpleado = $empDatos['rfc'] ?? '';
          $sqlDocs .= " AND (empleado_id IS NULL OR empleado_id = 0 OR empleado_id IN (
                  SELECT empleado_id FROM empleados_demograficos WHERE rfc = :rfc
                ))";
          $stDocs = $pdo->prepare($sqlDocs);
          $stDocs->execute([':emp' => $empresa_id, ':rfc' => $rfcEmpleado]);
      } else {
          $sqlDocs .= " AND empleado_id = :eid";
          $stDocs = $pdo->prepare($sqlDocs);
          $stDocs->execute([':emp' => $empresa_id, ':eid' => $empleado_id]);
      }
      
      $documentos = $stDocs->fetchAll(PDO::FETCH_ASSOC);
      ?>
      
      <?php if (count($documentos) > 0): ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead>
              <tr>
                <th>Tipo de Documento</th>
                <th>Nombre Archivo</th>
                <th>Fecha Generación</th>
                <th>Tamaño</th>
                <th width="100" class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($documentos as $doc): 
                $tipoLabel = [
                  'contrato_temporal' => 'Contrato Determinado',
                  'contrato_permanente' => 'Contrato Indeterminado',
                  'poliza_fh' => 'Póliza FH-250',
                  'carta_patronal' => 'Carta Patronal FH',
                  'otro' => 'Otro Documento'
                ];
                $label = $tipoLabel[$doc['tipo_documento']] ?? ucfirst($doc['tipo_documento']);
              ?>
              <tr>
                <td><i class="icon-file-pdf text-danger mr-1"></i><?php echo h($label); ?></td>
                <td><?php echo h($doc['nombre_archivo']); ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($doc['fecha_generacion'])); ?></td>
                <td><?php echo $doc['tamanio'] ? number_format($doc['tamanio'] / 1024, 1) . ' KB' : '-'; ?></td>
                <td class="text-center">
                  <a href="contratos_descargar_pdf.php?id=<?php echo $doc['documento_id']; ?>" class="btn btn-sm btn-light" title="Descargar" target="_blank">
                    <i class="icon-download"></i>
                  </a>
                  <button type="button" class="btn btn-sm btn-danger" title="Eliminar" onclick="eliminarDocumento(<?php echo $doc['documento_id']; ?>)">
                    <i class="icon-trash"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="alert alert-light border mb-0">
          <i class="icon-info22 mr-2"></i>No se han generado documentos todavía.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Formulario de Captura -->
  <div class="card" id="formCapturaDatos">
    <div class="card-header bg-primary text-white">
      <h6 class="card-title">Captura de Datos</h6>
    </div>
    <div class="card-body">
      <form id="formGuardar">
        <input type="hidden" name="accion" value="guardar_datos_empleado">
        
        <div class="alert alert-info mb-3">
          <i class="icon-info22 mr-2"></i>
          <strong>Para generar documentos:</strong> 
          Complete los datos básicos y laborales. Las fechas de inicio/término se capturan directamente en el modal al generar el documento.
        </div>
        
        <!-- Sección 1: Básicos -->
        <div class="card card-body bg-light mb-3">
          <h6 class="font-weight-bold mb-3">DATOS BÁSICOS</h6>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group"><label>Nombre *</label>
                <input type="text" name="nombre" class="form-control" value="<?php echo h($empDatos['nombre'] ?? ''); ?>" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group"><label>Apellido Paterno</label>
                <input type="text" name="apellido_paterno" class="form-control" value="<?php echo h($empDatos['apellido_paterno'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group"><label>Apellido Materno</label>
                <input type="text" name="apellido_materno" class="form-control" value="<?php echo h($empDatos['apellido_materno'] ?? ''); ?>">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-2">
              <div class="form-group"><label>Sexo</label>
                <select id="sexo" name="sexo" class="form-control">
                  <option value="">-</option>
                  <option value="M" <?php echo ($empDatos['sexo'] ?? '') === 'M' ? 'selected' : ''; ?>>M</option>
                  <option value="F" <?php echo ($empDatos['sexo'] ?? '') === 'F' ? 'selected' : ''; ?>>F</option>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Fecha Nacimiento</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control" value="<?php echo h($empDatos['fecha_nacimiento'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Nacionalidad</label>
                <input type="text" id="nacionalidad" name="nacionalidad" class="form-control" value="<?php echo h($empDatos['nacionalidad'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group"><label>Lugar Nacimiento</label>
                <input type="text" id="lugar_nacimiento" name="lugar_nacimiento" class="form-control" value="<?php echo h($empDatos['lugar_nacimiento'] ?? ''); ?>">
                <small class="form-text text-muted">Se extraerá del CURP</small>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group"><label>RFC</label>
                <input type="text" name="rfc" class="form-control" value="<?php echo h($empDatos['rfc'] ?? ''); ?>" maxlength="13">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>CURP</label>
                <input type="text" id="curp" name="curp" class="form-control text-uppercase" value="<?php echo h($empDatos['curp'] ?? ''); ?>" maxlength="18">
                <small class="form-text text-muted">Se extraerán datos automáticamente</small>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>NSS</label>
                <input type="text" name="nss" class="form-control" value="<?php echo h($empDatos['nss'] ?? ''); ?>" maxlength="20">
              </div>
            </div>
          </div>
          
          <!-- Domicilio -->
          <h6 class="font-weight-bold mt-3 mb-2">Domicilio</h6>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group"><label>Calle</label>
                <input type="text" name="domicilio_calle" class="form-control" value="<?php echo h($empDatos['domicilio_calle'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group"><label>Núm. Ext</label>
                <input type="text" name="domicilio_num_ext" class="form-control" value="<?php echo h($empDatos['domicilio_num_ext'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group"><label>Núm. Int</label>
                <input type="text" name="domicilio_num_int" class="form-control" value="<?php echo h($empDatos['domicilio_num_int'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group"><label>CP *</label>
                <input type="text" id="domicilio_cp" name="domicilio_cp" class="form-control" value="<?php echo h($empDatos['domicilio_cp'] ?? ''); ?>" maxlength="5">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group"><label>Estado</label>
                <input type="text" id="domicilio_estado" name="domicilio_estado" class="form-control" value="<?php echo h($empDatos['domicilio_estado'] ?? ''); ?>" readonly>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Municipio</label>
                <select id="domicilio_municipio" name="domicilio_municipio" class="form-control">
                  <option value="">Selecciona</option>
                  <?php if (!empty($empDatos['domicilio_municipio'])): ?>
                    <option value="<?php echo h($empDatos['domicilio_municipio']); ?>" selected><?php echo h($empDatos['domicilio_municipio']); ?></option>
                  <?php endif; ?>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Colonia *</label>
                <select id="domicilio_colonia" name="domicilio_colonia" class="form-control">
                  <option value="">Selecciona</option>
                  <?php if (!empty($empDatos['domicilio_colonia'])): ?>
                    <option value="<?php echo h($empDatos['domicilio_colonia']); ?>" selected><?php echo h($empDatos['domicilio_colonia']); ?></option>
                  <?php endif; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Sección 2: Pago -->
        <div class="card card-body bg-light mb-3">
          <h6 class="font-weight-bold mb-3">DATOS DE PAGO</h6>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group"><label>Apoderado Legal</label>
                <select id="apoderado_legal_id" name="apoderado_legal_id" class="form-control">
                  <option value="">-</option>
                  <?php
                    $sqlApo = "SELECT * FROM org_apoderados WHERE empresa_id = :e AND activo = 1";
                    $stApo = $pdo->prepare($sqlApo);
                    $stApo->execute(array(':e' => $empresa_id));
                    foreach ($stApo->fetchAll(PDO::FETCH_ASSOC) as $apo):
                  ?>
                    <option value="<?php echo $apo['apoderado_id']; ?>" <?php echo ($empDatos['apoderado_legal_id'] ?? 0) == $apo['apoderado_id'] ? 'selected' : ''; ?>><?php echo h($apo['nombre']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Fecha Alta</label>
                <input type="date" name="fecha_alta" class="form-control" value="<?php echo h($empDatos['fecha_alta'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Tipo Nómina</label>
                <select name="tipo_nomina" class="form-control">
                  <option value="">-</option>
                  <option value="Semanal" <?php echo ($empDatos['tipo_nomina'] ?? '') === 'Semanal' ? 'selected' : ''; ?>>Semanal</option>
                  <option value="Quincenal" <?php echo ($empDatos['tipo_nomina'] ?? '') === 'Quincenal' ? 'selected' : ''; ?>>Quincenal</option>
                  <option value="Mensual" <?php echo ($empDatos['tipo_nomina'] ?? '') === 'Mensual' ? 'selected' : ''; ?>>Mensual</option>
                </select>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-2">
              <div class="form-group"><label>Sueldo Diario</label>
                <input type="number" id="sueldo_diario" name="sueldo_diario" class="form-control" step="0.01" value="<?php echo h($empDatos['sueldo_diario'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group"><label>Sueldo Mensual *</label>
                <input type="number" id="sueldo_mensual" name="sueldo_mensual" class="form-control" step="0.01" value="<?php echo h($empDatos['sueldo_mensual'] ?? ''); ?>" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Banco *</label>
                <select id="banco_id" name="banco_id" class="form-control" required>
                  <option value="">Selecciona</option>
                  <?php
                    // Santander primero, luego resto ordenado por nombre
                    $sqlBancos = "SELECT * FROM cat_bancos WHERE activo = 1 ORDER BY (nombre = 'Santander') DESC, nombre";
                    $stBancos = $pdo->prepare($sqlBancos);
                    $stBancos->execute();
                    foreach ($stBancos->fetchAll(PDO::FETCH_ASSOC) as $banco):
                  ?>
                    <option value="<?php echo $banco['banco_id']; ?>" <?php echo ($empDatos['banco_id'] ?? 0) == $banco['banco_id'] ? 'selected' : ''; ?>><?php echo h($banco['nombre']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Núm. Cuenta</label>
                <input type="text" name="numero_cuenta" class="form-control" value="<?php echo h($empDatos['numero_cuenta'] ?? ''); ?>">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group"><label>CLABE</label>
                <input type="text" name="clabe" class="form-control" value="<?php echo h($empDatos['clabe'] ?? ''); ?>" maxlength="18">
              </div>
            </div>
          </div>
        </div>

        <!-- Sección 3: Adicionales -->
        <div class="card card-body bg-light mb-3">
          <h6 class="font-weight-bold mb-3">DATOS ADICIONALES</h6>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group"><label>Correo Empresa</label>
                <input type="email" name="correo_empresa" class="form-control" value="<?php echo h($empDatos['correo_empresa'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Correo Personal</label>
                <input type="email" name="correo_personal" class="form-control" value="<?php echo h($empDatos['correo_personal'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Escolaridad</label>
                <select id="escolaridad_id" name="escolaridad_id" class="form-control">
                  <option value="">-</option>
                  <?php
                    $sqlEsc = "SELECT * FROM cat_escolaridades WHERE activo = 1 ORDER BY orden";
                    $stEsc = $pdo->prepare($sqlEsc);
                    $stEsc->execute();
                    foreach ($stEsc->fetchAll(PDO::FETCH_ASSOC) as $esc):
                  ?>
                    <option value="<?php echo $esc['escolaridad_id']; ?>" <?php echo ($empDatos['escolaridad_id'] ?? 0) == $esc['escolaridad_id'] ? 'selected' : ''; ?>><?php echo h($esc['nombre']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>UMF (IMSS)</label>
                <input type="text" name="unidad_medica_familiar" class="form-control" value="<?php echo h($empDatos['unidad_medica_familiar'] ?? ''); ?>">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group"><label>Tel. Personal</label>
                <input type="tel" name="telefono_personal" class="form-control" value="<?php echo h($empDatos['telefono_personal'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Tel. Empresa</label>
                <input type="tel" name="telefono_empresa" class="form-control" value="<?php echo h($empDatos['telefono_empresa'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Crédito INFONAVIT</label>
                <select name="tiene_credito_infonavit" class="form-control">
                  <option value="">-</option>
                  <option value="S" <?php echo ($empDatos['tiene_credito_infonavit'] ?? '') === 'S' ? 'selected' : ''; ?>>Sí</option>
                  <option value="N" <?php echo ($empDatos['tiene_credito_infonavit'] ?? '') === 'N' ? 'selected' : ''; ?>>No</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-success btn-labeled btn-labeled-left" id="btnGuardar">
          <b><i class="icon-checkmark3"></i></b> Guardar Datos
        </button>
        <button type="button" class="btn btn-light" onclick="$('#formCapturaDatos').slideToggle();">Cancelar</button>
        
        <div id="alertaFormulario" style="display:none; margin-top:16px;"></div>
      </form>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/layout/footer.php'; ?>

<script src="<?php echo ASSET_BASE; ?>global_assets/js/plugins/forms/styling/uniform.min.js"></script>

<script>
$(function() {
  if ($.fn.uniform) {
    $('.form-check-input-styled').uniform();
  }

  // Código Postal autocomplete
  $(document).on('blur', '#domicilio_cp', function() {
    var cp = $(this).val().trim();
    if (cp.length < 4) return;
    
    $.ajax({
      url: '<?php echo ASSET_BASE; ?>public/api_codigo_postal.php',
      data: { cp: cp },
      dataType: 'json'
    }).done(function(resp) {
      if (resp.ok) {
        $('#domicilio_estado').val(resp.estado);
        var $selMun = $('#domicilio_municipio');
        $selMun.find('option:not(:first)').remove();
        $.each(resp.municipios, function(i, mun) {
          $selMun.append('<option value="' + mun + '">' + mun + '</option>');
        });
        if (resp.municipios.length === 1) $selMun.val(resp.municipios[0]);
        
        var $selCol = $('#domicilio_colonia');
        $selCol.find('option:not(:first)').remove();
        $.each(resp.colonias, function(i, col) {
          $selCol.append('<option value="' + col + '">' + col + '</option>');
        });
      }
    });
  });

  // Mapeo de códigos de entidad CURP a estados de nacimiento
  var entidadMap = {
    'AS': 'Aguascalientes', 'BC': 'Baja California', 'BS': 'Baja California Sur',
    'CC': 'Campeche', 'CS': 'Chiapas', 'CH': 'Chihuahua', 'CD': 'Ciudad de México',
    'CL': 'Coahuila', 'CM': 'Colima', 'DG': 'Durango', 'GT': 'Guanajuato',
    'GR': 'Guerrero', 'HG': 'Hidalgo', 'JC': 'Jalisco', 'MC': 'Estado de México',
    'MN': 'Michoacán', 'MS': 'Morelos', 'NT': 'Nayarit', 'NL': 'Nuevo León',
    'OC': 'Oaxaca', 'PL': 'Puebla', 'QT': 'Querétaro', 'QR': 'Quintana Roo',
    'SL': 'San Luis Potosí', 'SN': 'Sonora', 'SP': 'Sinaloa', 'SR': 'Sinaloa',
    'TC': 'Tabasco', 'TL': 'Tlaxcala', 'VZ': 'Veracruz', 'YN': 'Yucatán',
    'ZS': 'Zacatecas', 'NE': 'Extranjero'
  };

  // Extraer datos del CURP
  function extractCURPData() {
    var curp = $('#curp').val().trim().toUpperCase();
    
    // Validar que tenga 18 caracteres
    if (curp.length !== 18) {
      return;
    }
    
    try {
      // Posiciones 5-10 (índice 4-9): YYMMDD
      var yy = curp.substring(4, 6);
      var mm = curp.substring(6, 8);
      var dd = curp.substring(8, 10);
      
      // Posición 17 (índice 16): Indicador de siglo (0-9 = 19xx, A-Z = 20xx)
      var centuryChar = curp.charAt(16);
      var century = /^[0-9]$/.test(centuryChar) ? '19' : '20';
      var yyyy = century + yy;
      
      // Validar que sea una fecha válida
      var fechaNac = new Date(yyyy + '-' + mm + '-' + dd);
      if (isNaN(fechaNac.getTime())) {
        return;
      }
      
      // Actualizar fecha de nacimiento (format: YYYY-MM-DD para input type="date")
      var fechaFormato = yyyy + '-' + mm + '-' + dd;
      $('#fecha_nacimiento').val(fechaFormato);
      
      // Posición 11 (índice 10): Sexo (H = Masculino, otro = Femenino)
      var sexoChar = curp.charAt(10);
      var sexo = sexoChar === 'H' ? 'M' : 'F';
      $('#sexo').val(sexo);
      
      // Posiciones 12-13 (índice 11-12): Entidad (código de 2 letras)
      var entidadCod = curp.substring(11, 13);
      var lugarNacimiento = entidadMap[entidadCod] || entidadCod;
      $('#lugar_nacimiento').val(lugarNacimiento);
      
      // Determinar nacionalidad
      var nacionalidad = entidadCod === 'NE' ? 'Extranjero' : 'Mexicano';
      $('#nacionalidad').val(nacionalidad);
      
    } catch (e) {
      console.error('Error al extraer datos del CURP:', e);
    }
  }

  // Calcular sueldo mensual automáticamente
  function calcularSueldoMensual() {
    var sueldoDiario = parseFloat($('#sueldo_diario').val()) || 0;
    if (sueldoDiario > 0) {
      var sueldoMensual = (sueldoDiario * 30).toFixed(2);
      $('#sueldo_mensual').val(sueldoMensual);
    }
  }

  // Event listeners
  $('#curp').on('blur', function() {
    extractCURPData();
  });

  $('#sueldo_diario').on('change', function() {
    calcularSueldoMensual();
  });

  // Inicializar al cargar la página: ejecutar extracción si CURP ya tiene valor
  $(document).ready(function() {
    if ($('#curp').val().length === 18) {
      extractCURPData();
    }
    // Calcular sueldo si hay valor de sueldo_diario
    calcularSueldoMensual();
  });

  // Guardar datos
  $('#formGuardar').on('submit', function(e) {
    e.preventDefault();
    var $btn = $('#btnGuardar');
    $btn.prop('disabled', true);
    
    $.ajax({
      url: '',
      method: 'POST',
      data: $(this).serialize(),
      dataType: 'json'
    }).done(function(resp) {
      var $alerta = $('#alertaFormulario');
      if (resp.ok) {
        $alerta.html('<div class="alert alert-success">' + resp.mensaje + '</div>').show();
        setTimeout(function() { location.reload(); }, 1500);
      } else {
        $alerta.html('<div class="alert alert-danger">' + resp.error + '</div>').show();
        $btn.prop('disabled', false);
      }
    }).fail(function() {
      $('#alertaFormulario').html('<div class="alert alert-danger">Error de comunicación</div>').show();
      $btn.prop('disabled', false);
    });
  });
});

// Generar Documentos
function abrirModalGenerarDoc() {
  $('#modalGenerarDoc').modal('show');
}

function generarDocumento(tipoDoc) {
  var $btn = $('#btnGenerar_' + tipoDoc);
  var fechaInicio = $('#modal_fecha_inicio').val();
  var fechaTermino = $('#modal_fecha_termino').val();
  
  // Validar fecha de inicio
  if (!fechaInicio) {
    alert('Debe capturar la fecha de inicio del contrato');
    return false;
  }
  
  // Validar fecha de término para contratos determinados
  if (tipoDoc === 'contrato_determinado' && !fechaTermino) {
    alert('Los contratos determinados requieren fecha de término');
    return false;
  }
  
  $btn.prop('disabled', true).html('<i class="icon-spinner2 spinner"></i> Generando...');
  
  // Preparar datos
  var formData = new FormData();
  formData.append('tipo_documento', tipoDoc);
  formData.append('fecha_inicio_contrato', fechaInicio);
  formData.append('fecha_termino_contrato', fechaTermino);
  <?php if ($nuevo_ingreso_id > 0): ?>
  formData.append('nuevo_ingreso_id', '<?php echo $nuevo_ingreso_id; ?>');
  <?php else: ?>
  formData.append('empleado_id', '<?php echo $empleado_id; ?>');
  <?php endif; ?>
  
  // Generar PDF vía AJAX
  $.ajax({
    url: 'contratos_generar_pdf.php',
    method: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json'
  }).done(function(resp) {
    if (resp.ok && resp.documento_id) {
      var url = 'contratos_descargar_pdf.php?id=' + resp.documento_id;
      window.location.href = url; // navegación directa sin alertas
      setTimeout(function() { location.reload(); }, 2000);
      return;
    }

    var msg = resp.error || 'No se pudo registrar el documento';
    alert('Error: ' + msg);
    $btn.prop('disabled', false).html('Descargar');
  }).fail(function(jqXHR, textStatus, errorThrown) {
    var msg = jqXHR.responseText || textStatus;
    alert('Error de comunicación: ' + msg);
    $btn.prop('disabled', false).html('Descargar');
  });
}

// Eliminar Documento
var documentoIdAEliminar = null;

function eliminarDocumento(documentoId) {
  documentoIdAEliminar = documentoId;
  $('#modalConfirmarEliminar').modal('show');
}

function confirmarEliminarDocumento() {
  if (!documentoIdAEliminar) {
    return;
  }
  
  var documentoId = documentoIdAEliminar;
  $('#modalConfirmarEliminar').modal('hide');
  
  $.ajax({
    url: '',
    method: 'POST',
    data: {
      accion: 'eliminar_documento',
      documento_id: documentoId
    },
    dataType: 'json'
  }).done(function(resp) {
    if (resp.ok) {
      // Eliminar la fila de la tabla
      $('button[onclick="eliminarDocumento(' + documentoId + ')"]').closest('tr').fadeOut(300, function() {
        $(this).remove();
        // Si la tabla está vacía, recargar la página para mostrar el mensaje "No se han generado documentos"
        if ($('table tbody tr').length === 0) {
          location.reload();
        }
      });
    } else {
      alert('Error: ' + resp.error);
    }
  }).fail(function(jqXHR, textStatus, errorThrown) {
    alert('Error de comunicación: ' + textStatus);
  }).always(function() {
    documentoIdAEliminar = null;
  });
}
</script>

<!-- Modal Confirmar Eliminar Documento -->
<div class="modal fade" id="modalConfirmarEliminar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="icon-warning22 mr-2"></i>Confirmar Eliminación</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p class="mb-0"><i class="icon-alert text-warning mr-2"></i>¿Está seguro de que desea eliminar este documento?</p>
        <p class="text-muted mt-2 mb-0 small">Esta acción no se puede deshacer.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" onclick="confirmarEliminarDocumento()">Eliminar Documento</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Generar Documentos -->
<div class="modal fade" id="modalGenerarDoc" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="icon-file-pdf mr-2"></i>Generar Documentos</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p class="mb-3"><strong>Capture las fechas del contrato:</strong></p>
        
        <div class="row mb-4">
          <div class="col-md-6">
            <div class="form-group">
              <label>Fecha Inicio Contrato <span class="text-danger">*</span></label>
              <input type="date" id="modal_fecha_inicio" class="form-control">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label>Fecha Término Contrato</label>
              <input type="date" id="modal_fecha_termino" class="form-control">
              <small class="form-text text-muted">Solo para contratos determinados</small>
            </div>
          </div>
        </div>

        <hr>
        <p class="mb-3"><strong>Seleccione el documento que desea generar:</strong></p>
        
        <div class="list-group">
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <i class="icon-file-text2 text-primary mr-2"></i>
              <strong>Contrato por Tiempo Determinado</strong>
              <p class="text-muted mb-0 small">Contrato temporal con fecha de término</p>
            </div>
            <button id="btnGenerar_contrato_determinado" class="btn btn-sm btn-primary" onclick="generarDocumento('contrato_determinado');">Descargar</button>
          </div>
          
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <i class="icon-file-text2 text-success mr-2"></i>
              <strong>Contrato por Tiempo Indeterminado</strong>
              <p class="text-muted mb-0 small">Contrato indefinido sin fecha de término</p>
            </div>
            <button id="btnGenerar_contrato_indeterminado" class="btn btn-sm btn-success" onclick="generarDocumento('contrato_indeterminado');">Descargar</button>
          </div>
          
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <i class="icon-shield-check text-warning mr-2"></i>
              <strong>Póliza Fonacot FH-250</strong>
              <p class="text-muted mb-0 small">Póliza de seguro Fonacot</p>
            </div>
            <button id="btnGenerar_poliza_fh_250" class="btn btn-sm btn-warning" onclick="generarDocumento('poliza_fh_250');">Descargar</button>
          </div>
          
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <i class="icon-certificate text-info mr-2"></i>
              <strong>Carta Patronal FH</strong>
              <p class="text-muted mb-0 small">Carta del empleador para Fonacot</p>
            </div>
            <button id="btnGenerar_carta_patronal_fh" class="btn btn-sm btn-info" onclick="generarDocumento('carta_patronal_fh');">Descargar</button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

</body>
</html>
