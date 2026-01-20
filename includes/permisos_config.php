<?php
/**
 * includes/permisos_config.php
 * Configuración central del sistema de permisos granulares SGRH
 * Compatible PHP 5.7
 */

// Definición de todos los permisos del sistema
$PERMISOS_SISTEMA = array(
    // === USUARIOS ===
    'usuarios' => array(
        'usuarios.admin' => 'Administrar usuarios (crear, editar, eliminar)',
        'usuarios.ver' => 'Ver lista de usuarios',
        'usuarios.roles' => 'Asignar roles a usuarios',
        'usuarios.permisos' => 'Asignar permisos específicos a usuarios'
    ),
    
    // === ORGANIZACIÓN ===
    'organizacion' => array(
        'organizacion.admin' => 'Administración completa de organización',
        'organizacion.unidades.admin' => 'Administrar unidades',
        'organizacion.unidades.ver' => 'Ver unidades',
        'organizacion.adscripciones.admin' => 'Administrar departamentos',
        'organizacion.adscripciones.ver' => 'Ver departamentos',
        'organizacion.puestos.admin' => 'Administrar puestos',
        'organizacion.puestos.ver' => 'Ver puestos',
        'organizacion.centros.admin' => 'Administrar centros de trabajo',
        'organizacion.centros.ver' => 'Ver centros de trabajo'
    ),
    
    // === PLANTILLA AUTORIZADA ===
    'plantilla' => array(
        'plantilla.admin' => 'Administración completa de plantilla autorizada',
        'plantilla.crear' => 'Crear nuevas plazas',
        'plantilla.editar' => 'Editar plazas existentes',
        'organizacion.plantilla.edit' => 'Editar plazas (alias legacy)',
        'plantilla.eliminar' => 'Cancelar/eliminar plazas',
        'plantilla.congelar' => 'Congelar/descongelar plazas',
        'plantilla.asignar' => 'Asignar empleados a plazas',
        'plantilla.ver' => 'Ver plantilla autorizada (solo lectura)',
        'plantilla.exportar' => 'Exportar datos de plantilla'
    ),
    
    // === EMPLEADOS ===
    'empleados' => array(
        'empleados.admin' => 'Administración completa de empleados',
        'empleados.crear' => 'Crear nuevos empleados',
        'empleados.editar' => 'Editar datos de empleados',
        'empleados.eliminar' => 'Eliminar empleados',
        'empleados.ver' => 'Ver información de empleados',
        'empleados.foto' => 'Gestionar fotos de empleados',
        'empleados.expediente' => 'Acceso a expedientes completos'
    ),
    
    // === NÓMINA ===
    'nomina' => array(
        'nomina.admin' => 'Administración completa de nómina',
        'nomina.importar' => 'Importar datos de nómina',
        'nomina.procesar' => 'Procesar nómina',
        'nomina.ver' => 'Ver datos de nómina',
        'nomina.exportar' => 'Exportar datos de nómina'
    ),
    
    // === CONTRATOS ===
    'contratos' => array(
        'contratos.admin' => 'Administración completa de contratos',
        'contratos.generar' => 'Generar contratos',
        'contratos.editar' => 'Editar contratos',
        'contratos.firmar' => 'Firmar contratos',
        'contratos.ver' => 'Ver contratos',
        'contratos.exportar' => 'Exportar contratos'
    ),
    
    // === CLIMA LABORAL ===
    'clima' => array(
        'clima.admin' => 'Administración completa de clima laboral',
        'clima.periodos' => 'Gestionar periodos de evaluación',
        'clima.dimensiones' => 'Gestionar dimensiones y preguntas',
        'clima.contestar' => 'Contestar encuestas',
        'clima.resultados' => 'Ver resultados globales',
        'clima.resultados_unidad' => 'Ver resultados de mi unidad',
        'clima.planes' => 'Gestionar planes de acción',
        'clima.planes_unidad' => 'Gestionar planes de mi unidad'
    ),
    
    // === DOCUMENTOS ===
    'documentos' => array(
        'documentos.admin' => 'Administración completa de documentos',
        'documentos.crear' => 'Crear plantillas de documentos',
        'documentos.editar' => 'Editar plantillas',
        'documentos.eliminar' => 'Eliminar plantillas',
        'documentos.ver' => 'Ver documentos',
        'documentos.generar' => 'Generar documentos desde plantillas'
    ),
    
    // === REPORTES ===
    'reportes' => array(
        'reportes.ver' => 'Ver reportes',
        'reportes.exportar' => 'Exportar reportes',
        'reportes.avanzados' => 'Acceso a reportes avanzados'
    ),
    
    // === CONFIGURACIÓN ===
    'config' => array(
        'config.empresa' => 'Configurar datos de empresa',
        'config.sistema' => 'Configuración del sistema',
        'config.permisos' => 'Gestionar permisos y roles'
    )
);

/**
 * Obtiene todos los permisos del sistema organizados por módulo
 * @return array
 */
function get_permisos_disponibles() {
    global $PERMISOS_SISTEMA;
    return $PERMISOS_SISTEMA;
}

/**
 * Obtiene la lista plana de todos los permisos
 * @return array ['clave' => 'descripcion']
 */
function get_permisos_planos() {
    global $PERMISOS_SISTEMA;
    $planos = array();
    foreach ($PERMISOS_SISTEMA as $modulo => $permisos) {
        foreach ($permisos as $clave => $desc) {
            $planos[$clave] = $desc;
        }
    }
    return $planos;
}

/**
 * Verifica si un permiso existe en el sistema
 * @param string $permiso
 * @return bool
 */
function permiso_existe($permiso) {
    $planos = get_permisos_planos();
    return isset($planos[$permiso]);
}

/**
 * Obtiene permisos por módulo
 * @param string $modulo
 * @return array|null
 */
function get_permisos_modulo($modulo) {
    global $PERMISOS_SISTEMA;
    return isset($PERMISOS_SISTEMA[$modulo]) ? $PERMISOS_SISTEMA[$modulo] : null;
}
