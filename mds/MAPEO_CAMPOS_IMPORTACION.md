GUÍA: Mapeo de Campos Organizacionales en Importación de Nómina
================================================================

PROBLEMA RESUELTO
-----------------
Tu base de datos tenía conflictos de nombres:
- puesto_id NO se llenaba (se usaba puesto_nomina_id en su lugar)
- adscripcion_id NO se llenaba (se usaba departamento_id en su lugar)  
- unidad_id NO se llenaba (no había forma de derivarlo)
- jefe_no_emp NO se llenaba

SOLUCIÓN IMPLEMENTADA
---------------------

1. MIGRACIÓN REALIZADA (ya hecha)
   - 904 de 906 empleados existentes tienen ahora unidad_id
   - Mapeo de puesto_nombre → puesto_id
   - Derivación automática: puesto_id → unidad_id
   - Mapeo de departamento_id → adscripcion_id

2. IMPORTADOR ACTUALIZADO
   El importador ahora AUTOMÁTICAMENTE:
   
   a) PUESTO_ID (Columna P del Excel: nombre del puesto)
      ├─ Búsqueda exacta en org_puestos.nombre
      ├─ Si no encuentra, búsqueda LIKE (variaciones menores)
      └─ Asigna a empleados.puesto_id
   
   b) UNIDAD_ID (Derivado desde org_puestos)
      ├─ Si puesto_id se resolvió
      └─ Copia org_puestos.unidad_id → empleados.unidad_id
   
   c) ADSCRIPCION_ID (Columna M del Excel: clave del departamento)
      ├─ Búsqueda en org_adscripciones.clave + empresa_id
      └─ Asigna a empleados.adscripcion_id
   
   d) JEFE_NO_EMP (Columna T del Excel: nombre del jefe)
      ├─ Búsqueda exacta en empleados.nombre+apellidos
      ├─ Si no encuentra, búsqueda LIKE
      └─ Asigna no_emp del jefe a empleados.jefe_no_emp

COLUMNAS DEL EXCEL ESPERADAS
----------------------------
Columna | Contenido              | Mapea a
--------|------------------------|----------------------------------
A       | Nombre Empresa         | empleados.empresa_id (lookup)
B       | Número Empleado        | empleados.no_emp
C       | Apellido Paterno       | empleados.apellido_paterno
D       | Apellido Materno       | empleados.apellido_materno
E       | Nombre                 | empleados.nombre
F       | RFC                    | empleados.rfc_base (10 primeros)
G       | CURP                   | empleados.curp
H       | Activo?                | empleados.es_activo (1/0)
I       | Fecha Ingreso          | empleados.fecha_ingreso
J       | Fecha Baja             | empleados.fecha_baja
K       | Tipo Empleado ID       | empleados.tipo_empleado_id
L       | Tipo Empleado Nombre   | empleados.tipo_empleado_nombre
M       | Departamento/Clave     | empleados.adscripcion_id (lookup)
N       | Nombre Departamento    | empleados.departamento_nombre
O       | Puesto Código (OLD)    | ignorado (fallback solo)
P       | NOMBRE DEL PUESTO *    | empleados.puesto_id (LOOKUP PRINCIPAL)
Q       | Centro Trabajo ID      | empleados.centro_trabajo_id
R       | Centro Trabajo Nombre  | empleados.centro_trabajo_nombre
S       | (reservado)            | 
T       | NOMBRE DEL JEFE *      | empleados.jefe_no_emp (lookup)
U       | Salario Mensual        | empleados.salario_mensual
V       | Salario Diario         | empleados.salario_diario
W       | Correo (opcional)      | empleados_demograficos.correo
X       | Teléfono (opcional)    | empleados_demograficos.telefono
Y       | Periodo de Nómina      | empleados_demograficos.tipo_nomina
Z       | No. Afiliacional IMSS  | empleados_demograficos.nss
AA      | Calle Domicilio        | empleados_demograficos.domicilio_calle
AB      | Ciudad Domicilio       | empleados_demograficos.domicilio_municipio
AC      | Colonia Domicilio      | empleados_demograficos.domicilio_colonia
AD      | C.P. Domicilio         | empleados_demograficos.domicilio_cp
AE      | Estado Domicilio       | empleados_demograficos.domicilio_estado
AF      | Número Domicilio       | empleados_demograficos.domicilio_num_ext
AG      | CLABE Interbancaria    | empleados_demograficos.clabe
AH      | Crédito INFONAVIT      | empleados_demograficos.credito_infonavit
AI      | Unidad Médica Familiar | empleados_demograficos.unidad_medica_familiar
AJ      | Banco Depósito         | empleados_demograficos.banco

* = Critico para mapeo de unidad_id

PASOS PARA IMPORTAR
------------------

1. Prepara tu archivo Excel con:
   - Columna P: Nombre EXACTO del puesto (ej: "ANALISTA DE VALIDACION")
   - Columna M: Clave del departamento (ej: "HP6")
   - Columna T: Nombre del jefe (ej: "JUAN CARLOS REYES BARRETERO")

2. Asegúrate que org_puestos y org_adscripciones tengan registros:
   SELECT COUNT(*) FROM org_puestos;
   SELECT COUNT(*) FROM org_adscripciones;

3. Ve a http://localhost/sgrh/public/importar_nomina.php

4. Carga el Excel

5. Revisa el "Historial de Importación" para:
   - Empleados correctamente mapeados
   - Errores de puesto no encontrado (corrige en org_puestos)

CASOS DE ERROR
--------------

ERROR: puesto_id es NULL después de importar
CAUSA: Nombre del puesto NO coincide en org_puestos
SOLUCIÓN: 
  1. Verifica el nombre exacto en org_puestos
  2. Actualiza tu Excel con ese nombre
  3. Reimporta

ERROR: adscripcion_id es NULL
CAUSA: Código de departamento (col M) no existe en org_adscripciones.clave
SOLUCIÓN:
  1. Verifica org_adscripciones: SELECT clave, nombre FROM org_adscripciones;
  2. Actualiza Excel con claves correctas
  3. Reimporta

QUERIES ÚTILES
--------------

Verificar cuántos tienen unidad_id:
  SELECT COUNT(*) FROM empleados WHERE unidad_id IS NOT NULL;

Ver puestos faltantes:
  SELECT DISTINCT puesto_nombre FROM empleados WHERE puesto_id IS NULL;

Ver adscripciones sin mapear:
  SELECT DISTINCT departamento_id FROM empleados WHERE adscripcion_id IS NULL;

Ver empleados sin jefe:
  SELECT COUNT(*) FROM empleados WHERE jefe_no_emp IS NULL;

FLUJO ACTUAL (DESPUÉS DE ESTA ACTUALIZACIÓN)
---------------------------------------------

IMPORTACIÓN NUEVA
    ↓
Lee Excel (puesto_nombre en col P)
    ↓
Busca puesto_nombre en org_puestos
    ↓
    ├─ ENCUENTRA → Obtiene puesto_id + unidad_id
    └─ NO ENCUENTRA → puesto_id=NULL, unidad_id=NULL
    
    (Similar para adscripcion_id, jefe_no_emp)
    ↓
INSERT/UPDATE empleados con todos los valores
    ↓
INSERT/UPDATE usuarios
    ↓
INSERT/UPDATE empleados_demograficos (correo/telefono si vienen)
    ↓
Log detallado en nomina_import_detalle.payload_json
    ↓
LISTO ✓

PRÓXIMAS REINTERCIONES
---------------------
Cada vez que importes:
1. Los campos puesto_id, unidad_id, adscripcion_id, jefe_no_emp se RECALCULAN
   (Si cambió el nombre del puesto en org_puestos, se va a actualizar)

2. Campos que NO se tocan en UPDATE (protegidos):
   - password_hash (no se resetea)
   - debe_cambiar_pass (se mantiene)
   - pass_cambiada (se mantiene)

3. Demograficos se INSERT o UPDATE si hay correo/teléfono en Excel

NOTAS IMPORTANTES
-----------------
✓ Los 2 empleados sin puesto (JEFE DE NOMINAS, COORDINADOR OPERACIONES INTL)
  necesitan que PRIMERO se creen sus puestos en org_puestos.
  
✓ Una vez creado el puesto en org_puestos con unidad_id, puedes:
  - Crear un script de corrección: UPDATE empleados SET puesto_id=X, unidad_id=Y WHERE ...
  - O reimportar el Excel

✓ Después de importar, ejecuta generador de elegibles:
  http://localhost/sgrh/public/clima_generar_elegibles.php
  (Ahora debería funcionar sin error de columna)
