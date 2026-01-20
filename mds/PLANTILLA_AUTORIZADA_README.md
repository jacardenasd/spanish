# Módulo de Plazas Autorizadas - SGRH

## Descripción General

El módulo de **Plazas Autorizadas** permite gestionar cada plaza organizacional de forma **individual**, con su historial completo desde la creación hasta su eventual cancelación. Cada registro en la base de datos representa **una plaza única** con su propio ciclo de vida.

### Características Principales

- ✅ **Registro Individual**: Cada plaza es un registro único con su código identificador
- ✅ **Historial Completo**: Fecha de creación, congelación y cancelación
- ✅ **Justificaciones Obligatorias**: Cada acción requiere su justificación documentada
- ✅ **Estados de Ciclo de Vida**: Activa, Congelada, Cancelada
- ✅ **Trazabilidad**: Auditoría completa de quién creó y modificó cada plaza
- ✅ **Ocupación en Tiempo Real**: Seguimiento de qué empleado ocupa cada plaza

---

## Conceptos Clave

### ¿Qué es una Plaza?

Una **plaza** es una posición autorizada dentro de la estructura organizacional que:
- Tiene un código único identificador
- Puede o no estar ocupada por un empleado
- Tiene un historial documentado de su ciclo de vida
- Está asignada a una unidad organizacional específica
- Opcionalmente puede estar asociada a un departamento y/o puesto específico

### Estados de una Plaza

1. **Activa** 🟢
   - Plaza autorizada y disponible
   - Puede estar vacante u ocupada
   - Puede ser asignada a empleados

2. **Congelada** 🟡
   - Plaza temporalmente suspendida
   - No puede asignarse a empleados
   - Puede ser reactivada posteriormente
   - Útil para restricciones presupuestales temporales

3. **Cancelada** 🔴
   - Plaza eliminada de forma permanente
   - No puede ser reactivada
   - Se mantiene el registro histórico
   - Solo se pueden cancelar plazas vacantes

---

## Campos de la Plaza

### Información Básica
- **plaza_id**: ID único autogenerado
- **codigo_plaza**: Código identificador (ej: PLZ-001-0001)
- **empresa_id**: Empresa a la que pertenece
- **unidad_id**: Unidad organizacional (obligatorio)
- **adscripcion_id**: Departamento (opcional)
- **puesto_id**: Puesto específico (opcional)

### Fechas del Ciclo de Vida
- **fecha_creacion**: Fecha de autorización de la plaza *(obligatorio)*
- **fecha_cancelacion**: Fecha de cancelación definitiva
- **fecha_congelacion**: Fecha de suspensión temporal

### Justificaciones
- **justificacion_creacion**: Fundamento para crear la plaza *(obligatorio)*
  - Ejemplo: "Plaza autorizada según presupuesto 2026, oficio DRH-123/2026"
- **justificacion_cancelacion**: Motivo de cancelación
  - Ejemplo: "Reestructuración organizacional, acuerdo de junta directiva"
- **justificacion_congelacion**: Motivo de congelación temporal
  - Ejemplo: "Restricción presupuestal temporal Q1 2026"

### Ocupación
- **empleado_id**: ID del empleado asignado (NULL = vacante)
- **fecha_asignacion**: Fecha en que se asignó al empleado actual

### Auditoría
- **created_by**: Usuario que creó la plaza
- **created_at**: Timestamp de creación
- **updated_at**: Timestamp de última modificación

---

## Funcionalidades Principales

### 1. Crear Plaza

**Requisitos obligatorios:**
- Unidad organizacional
- Fecha de creación
- Justificación de creación

**Campos opcionales:**
- Código de plaza (se genera automáticamente si no se proporciona)
- Departamento específico
- Puesto específico
- Observaciones adicionales

**Ejemplo de uso:**
```
Unidad: Dirección de Tecnología
Departamento: Desarrollo de Software
Puesto: Analista Programador Senior
Fecha: 2026-01-15
Justificación: "Plaza autorizada según presupuesto 2026 para proyecto de modernización tecnológica, oficio DG-001/2026"
```

### 2. Congelar Plaza

Suspende temporalmente una plaza sin eliminarla.

**Requisitos:**
- Plaza debe estar en estado "activa"
- Fecha de congelación
- Justificación de la congelación

**Casos de uso:**
- Restricciones presupuestales temporales
- Reorganizaciones en proceso
- Auditorías o revisiones de plantilla
- Periodos de austeridad

**Acciones disponibles:**
- ❄️ **Congelar**: Suspende la plaza
- ▶️ **Descongelar**: Reactiva la plaza (se puede hacer sin justificación)

### 3. Cancelar Plaza

Elimina permanentemente una plaza de la plantilla autorizada.

**Requisitos:**
- Plaza debe estar vacante (sin empleado asignado)
- Fecha de cancelación
- Justificación detallada

**Restricciones:**
- ⚠️ No se pueden cancelar plazas ocupadas
- ⚠️ La cancelación es irreversible
- ✅ Se mantiene el registro histórico

**Casos de uso:**
- Reducción de plantilla
- Reestructuraciones organizacionales
- Cambios en presupuesto permanentes
- Fusión de departamentos

### 4. Asignar/Desasignar Empleado

Gestiona la relación entre plazas y empleados.

**Asignar Empleado:**
- Requiere: Plaza activa y vacante, Empleado activo, Fecha de asignación
- El empleado debe estar en estatus ACTIVO
- Se registra la fecha de asignación
- Se mantiene histórico de cambios

**Desasignar Empleado:**
- Libera la plaza (pasa de Ocupada a Vacante)
- No requiere justificación
- El empleado sigue activo en el sistema
- Puede ser reasignado a otra plaza

**Buscador de Empleados:**
- Búsqueda por número de empleado
- Búsqueda por nombre
- Solo muestra empleados activos
- Interfaz de búsqueda rápida con Select2

### 5. Ver Detalle Completo

Muestra toda la información e historial de una plaza:
- Datos básicos (código, unidad, departamento, puesto)
- Estado actual
- Fechas y justificaciones de todas las acciones
- Información del empleado asignado (si aplica)
- Observaciones adicionales

### 5. Editar Observaciones

Permite actualizar las notas adicionales de cualquier plaza sin afectar su estado o justificaciones originales.

---

## Dashboard y Estadísticas

El módulo incluye 6 tarjetas estadísticas:

1. **Total Plazas**: Cantidad total de plazas registradas (todas las estatuses)
2. **Activas**: Plazas disponibles y utilizables
3. **Ocupadas**: Plazas activas con empleado asignado
4. **Vacantes**: Plazas activas sin empleado
5. **Congeladas**: Plazas temporalmente suspendidas
6. **Canceladas**: Plazas definitivamente eliminadas

---

## Filtros y Búsqueda

### Filtros Disponibles

- **Por Estado**: Todas / Activas / Congeladas / Canceladas
- **Por Unidad**: Filtrar por dirección específica

### Búsqueda en DataTable

La tabla incluye búsqueda en tiempo real que filtra por:
- Código de plaza
- Nombre de unidad
- Nombre de departamento
- Nombre de puesto
- Nombre de empleado asignado

---

## Flujo de Trabajo Típico

### Escenario 1: Expansión de Personal

1. **Autorización**: Se aprueba presupuesto para 5 nuevas plazas en TI
2. **Registro**: Se crean 5 plazas individuales con su justificación
   ```
   PLZ-005-0021: Analista de Datos - Justificación: Proyecto Big Data 2026
   PLZ-005-0022: Desarrollador Backend - Justificación: Modernización sistemas
   PLZ-005-0023: Arquitecto Cloud - Justificación: Migración a nube
   PLZ-005-0024: QA Tester - Justificación: Mejora calidad software
   PLZ-005-0025: DevOps Engineer - Justificación: Automatización despliegues
   ```
3. **Contratación**: Al contratar, se asigna el empleado a la plaza correspondiente
4. **Seguimiento**: Se puede ver en tiempo real qué plazas están ocupadas y cuáles vacantes

### Escenario 2: Restricción Presupuestal

1. **Decisión**: Por recorte presupuestal, se congelan temporalmente 10 plazas vacantes
2. **Acción**: Se congelan las plazas con justificación "Recorte presupuestal Q1 2026"
3. **Estado**: Las plazas quedan marcadas como congeladas, no pueden asignarse
4. **Reactivación**: Al recuperar presupuesto, se descongelan selectivamente

### Escenario 3: Reestructuración

1. **Análisis**: Se decide eliminar un departamento completo
2. **Limpieza**: Primero se deben liberar todas las plazas ocupadas (reubicar empleados)
3. **Cancelación**: Se cancelan todas las plazas con justificación "Eliminación de departamento X por reestructuración 2026"
4. **Historial**: Las plazas canceladas quedan registradas para auditorías futuras

---

## Estructura de la Base de Datos

### Tabla: org_plantilla_autorizada

```sql
CREATE TABLE org_plantilla_autorizada (
  plaza_id INT PRIMARY KEY AUTO_INCREMENT,
  codigo_plaza VARCHAR(50),
  empresa_id INT NOT NULL,
  unidad_id INT NOT NULL,
  adscripcion_id INT NULL,
  puesto_id INT NULL,
  fecha_creacion DATE NOT NULL,
  fecha_cancelacion DATE NULL,
  fecha_congelacion DATE NULL,
  justificacion_creacion TEXT NOT NULL,
  justificacion_cancelacion TEXT NULL,
  justificacion_congelacion TEXT NULL,
  observaciones TEXT NULL,
  estado ENUM('activa','congelada','cancelada') DEFAULT 'activa',
  empleado_id INT NULL,
  fecha_asignacion DATE NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Vista: v_plantilla_autorizada

Proporciona información enriquecida con:
- Nombres legibles de unidades, departamentos, puestos
- Información completa del empleado asignado
- Nombre del usuario que creó la plaza
- Estado de ocupación calculado

---

## Casos de Uso Detallados

### Caso 1: Creación de Plaza para Puesto Nuevo

**Contexto**: Se aprueba la creación de un puesto de "Director de Innovación"

**Proceso:**
1. Crear plaza:
   - Código: DIR-GEN-001
   - Unidad: Dirección General
   - Puesto: Director de Innovación
   - Justificación: "Plaza autorizada en sesión de consejo 2026-01-10, acta 001/2026, para liderar iniciativas de transformación digital"

2. Resultado: Plaza creada en estado "activa" y "vacante"

3. Contratación: Al contratar, se asigna el empleado y automáticamente la plaza queda "ocupada"

### Caso 2: Gestión de Plaza Temporal

**Contexto**: Se requiere personal por proyecto específico de 6 meses

**Proceso:**
1. Crear plaza:
   - Justificación: "Plaza temporal para proyecto X, vigencia 6 meses"
   - Observaciones: "Revisar para cancelación en julio 2026"

2. Durante el proyecto: Plaza activa y ocupada

3. Fin del proyecto:
   - Desasignar empleado (finalizar contrato)
   - Cancelar plaza con justificación: "Fin de proyecto X según lo programado"

### Caso 3: Reorganización de Departamento

**Contexto**: Fusión de dos departamentos

**Proceso:**
1. Identificar plazas de ambos departamentos
2. Evaluar qué plazas se mantienen
3. Plazas a eliminar:
   - Verificar que estén vacantes (reubicar empleados si es necesario)
   - Cancelar con justificación: "Fusión departamento A y B, acuerdo gerencial"
4. Plazas a mantener:
   - Actualizar observaciones para reflejar nuevo organigrama

---

## Reportes y Consultas Útiles

### Reporte de Plazas Vacantes Activas

```sql
SELECT codigo_plaza, unidad_nombre, puesto_nombre, fecha_creacion
FROM v_plantilla_autorizada
WHERE estado = 'activa' AND empleado_id IS NULL
ORDER BY unidad_nombre, fecha_creacion;
```

### Plazas Creadas en un Periodo

```sql
SELECT COUNT(*) AS total, unidad_nombre
FROM v_plantilla_autorizada
WHERE fecha_creacion BETWEEN '2026-01-01' AND '2026-12-31'
GROUP BY unidad_id, unidad_nombre
ORDER BY total DESC;
```

### Historial de Cancelaciones

```sql
SELECT codigo_plaza, unidad_nombre, fecha_cancelacion, justificacion_cancelacion
FROM v_plantilla_autorizada
WHERE estado = 'cancelada'
ORDER BY fecha_cancelacion DESC;
```

### Ocupación por Unidad

```sql
SELECT 
  unidad_nombre,
  COUNT(*) AS total_plazas,
  SUM(CASE WHEN estado = 'activa' THEN 1 ELSE 0 END) AS activas,
  SUM(CASE WHEN empleado_id IS NOT NULL THEN 1 ELSE 0 END) AS ocupadas,
  SUM(CASE WHEN estado = 'activa' AND empleado_id IS NULL THEN 1 ELSE 0 END) AS vacantes
FROM v_plantilla_autorizada
WHERE empresa_id = 1
GROUP BY unidad_id, unidad_nombre
ORDER BY unidad_nombre;
```

---

## Permisos y Seguridad

**Permiso requerido**: `organizacion.admin`

Los usuarios con este permiso pueden:
- ✅ Crear nuevas plazas
- ✅ Congelar/descongelar plazas
- ✅ Cancelar plazas vacantes
- ✅ Ver historial completo de todas las plazas
- ✅ Editar observaciones

**Restricciones de seguridad:**
- ❌ No se pueden cancelar plazas ocupadas
- ❌ Las cancelaciones son permanentes (no reversibles)
- ✅ Todas las acciones quedan registradas en bitácora
- ✅ Validación CSRF en todos los formularios

---

## Mejores Prácticas

### ✅ Recomendaciones

1. **Códigos de Plaza Claros**
   - Usa códigos que identifiquen fácilmente la unidad
   - Ejemplo: DIR-TI-001, DIR-FIN-025, DIR-RH-010

2. **Justificaciones Detalladas**
   - Incluye referencias a oficios, acuerdos, sesiones de consejo
   - Menciona presupuesto o número de partida
   - Proporciona contexto suficiente para auditorías

3. **Congelar en lugar de Cancelar**
   - Si hay incertidumbre, congela temporalmente
   - Permite reactivar sin perder el registro

4. **Mantener Observaciones Actualizadas**
   - Usa el campo de observaciones para notas administrativas
   - Documenta cambios organizacionales relevantes

5. **Revisión Periódica**
   - Revisa mensualmente plazas vacantes
   - Identifica plazas congeladas que puedan reactivarse
   - Detecta plazas obsoletas para cancelar

### ⚠️ Advertencias

1. **No Cancelar Precipitadamente**
   - La cancelación es irreversible
   - Evalúa congelar primero si hay dudas

2. **Liberar Plazas Antes de Cancelar**
   - Las plazas ocupadas no se pueden cancelar
   - Proceso: finalizar relación laboral → desasignar empleado → cancelar plaza

3. **Documentar Bien las Justificaciones**
   - Las justificaciones son críticas para auditorías
   - Incluye toda la información relevante desde el inicio

4. **Respetar el Proceso de Autorización**
   - Solo crear plazas con autorización formal
   - Guardar documentos de respaldo

---

## Integración con Otros Módulos

### Importación de Nómina
- Al importar empleados, se puede asignar automáticamente a plazas vacantes
- Útil para mantener sincronizada la plantilla real vs. autorizada

### Kit de Contratación
- Verificar plazas vacantes antes de generar contratos
- Asignar automáticamente nueva contratación a plaza disponible

### Reportes de Clima Laboral
- Comparar participación vs. plazas ocupadas
- Análisis de cobertura por unidad

---

## Soporte Técnico

### Archivos Relacionados

- **Backend**: `public/admin_org_plantilla.php`
- **Migración SQL**: `migrations/01_crear_tabla_plantilla_autorizada.sql`
- **Menú**: `includes/layout/sidebar.php`
- **Documentación**: `mds/PLANTILLA_AUTORIZADA_README.md`

### Preguntas Frecuentes

**P: ¿Puedo reactivar una plaza cancelada?**  
R: No, las cancelaciones son permanentes. Debes crear una nueva plaza.

**P: ¿Qué pasa si necesito cambiar la justificación de creación?**  
R: Las justificaciones no son editables para mantener integridad de auditoría. Usa el campo "observaciones" para agregar contexto adicional.

**P: ¿Puedo congelar una plaza ocupada?**  
R: Sí, pero el empleado permanece asignado. La plaza no podrá reasignarse mientras esté congelada.

**P: ¿Cómo asigno un empleado a una plaza?**  
R: Usa el botón "Asignar Empleado" en la interfaz administrativa. Selecciona el empleado del buscador (por número o nombre), confirma la fecha de asignación y guarda. El empleado debe estar activo.

**P: ¿Qué pasa si desasigno un empleado?**  
R: La plaza pasa de "Ocupada" a "Vacante" pero sigue activa. El empleado no se elimina del sistema, solo se desvincula de esa plaza específica.

**P: ¿Puedo tener múltiples plazas con el mismo puesto en la misma unidad?**  
R: Sí, cada plaza es independiente. Por ejemplo, puedes tener 5 plazas de "Analista" en TI, cada una con su código único.

---

## Roles y Permisos

El módulo utiliza un sistema de permisos granular:

### Permisos Disponibles

1. **plantilla.admin**
   - Acceso: `public/admin_org_plantilla.php` (interfaz completa)
   - Acciones: CRUD completo (crear, congelar, cancelar, asignar, desasignar)
   - Cuándo asignar: Administradores de organización
   - Rol recomendado: Admin Organización, RH Manager

2. **plantilla.ver**
   - Acceso: `public/plantilla.php` (interfaz de solo lectura)
   - Acciones: Solo consulta y visualización
   - Cuándo asignar: Empleados que necesitan ver plantilla
   - Rol recomendado: Empleado, Supervisor

### Configuración de Permisos

#### SQL para insertar permisos:
```sql
-- Crear permisos si no existen
INSERT INTO permisos (clave, descripcion, modulo, created_at)
VALUES 
    ('plantilla.admin', 'Administración completa de Plantilla Autorizada (CRUD)', 'Organización', NOW()),
    ('plantilla.ver', 'Ver/consultar Plantilla Autorizada (solo lectura)', 'Organización', NOW())
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- Asignar a rol Admin Organización
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT r.rol_id, p.permiso_id
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre LIKE '%Organizaci%n%' AND p.clave = 'plantilla.admin';
```

#### Cómo asignar a usuarios:
1. Ir a **Administración → Usuarios**
2. Seleccionar usuario
3. Clic en botón de roles (ícono lista)
4. Asignar el rol que contenga `plantilla.admin` o `plantilla.ver`
5. Guardar

### Interfaz del Usuario

**Admin Interface** (`admin_org_plantilla.php`)
- Requiere: `plantilla.admin` o `organizacion.admin`
- Menú: Organización → Plantilla Autorizada (Admin)
- Features:
  - Crear nuevas plazas
  - Congelar/Descongelar
  - Asignar/Desasignar empleados
  - Cancelar plazas
  - Filtrar por estado, unidad, departamento
  - Ver detalles completos
  - Editar observaciones
  - Búsqueda y buscador de puestos

**User Interface** (`plantilla.php`)
- Requiere: `plantilla.ver`
- Menú: Organización → Plantilla Autorizada (solo si no es admin)
- Features:
  - Ver plazas
  - Filtrar por estado, unidad, departamento
  - Ver detalles completos
  - Sin acciones de modificación

---

## Roadmap / Mejoras Futuras

- [x] Asignación de empleados a plazas
- [x] Interfaz de solo lectura para usuarios
- [x] Permisos granulares (admin/ver)
- [x] Buscador de puestos y empleados
- [x] Filtrado Ajax de departamentos
- [ ] Asignación automática de empleados desde importación de nómina
- [ ] Workflow de aprobación para creación de plazas
- [ ] Generación de oficios de autorización
- [ ] Reporte de costos por plaza (integrando con datos de nómina)
- [ ] Dashboard visual con gráficas de evolución histórica
- [ ] Exportación a Excel/PDF
- [ ] Alertas de plazas vacantes por mucho tiempo
- [ ] Historial de reasignaciones (cuando un empleado cambia de plaza)
- [ ] API REST para integración con otros sistemas

---

## Changelog

### v2.1 - 2026-01-22
- ✨ **Asignación de empleados**: Sistema completo de asignar/desasignar empleados a plazas
- ✨ **Interfaz de solo lectura**: Nueva interfaz plantilla.php para usuarios con permisos limitados
- ✨ **Buscador de empleados**: Select2 con búsqueda rápida por número o nombre
- ✨ **Buscador de puestos**: Select2 en modal de crear plaza
- ✨ **Filtrado Ajax**: Carga dinámica de departamentos al cambiar unidad
- ✨ **Permisos granulares**: Permisos plantilla.admin y plantilla.ver
- ✨ **Menú dual**: Menú adaptativo según permisos del usuario
- 🐛 **Fix**: DISTINCT en query de puestos para eliminar duplicados
- 📝 **Documentación**: Guía completa de roles, permisos y uso de asignación

### v2.0 - 2026-01-20
- 🔄 **Rediseño completo**: Cambio de enfoque de cantidades agregadas a plazas individuales
- ✨ Código único por plaza con generación automática
- ✨ Ciclo de vida completo: creación, congelación, cancelación
- ✨ Justificaciones obligatorias para cada acción
- ✨ Estados: activa, congelada, cancelada
- ✨ Tracking de ocupación por empleado
- ✨ Vista detallada de historial por plaza
- ✨ Modales para congelar y cancelar plazas
- ✨ Dashboard con 6 estadísticas clave
- ✨ Filtros por estado y unidad
- ✨ Auditoría completa con created_by
- 📝 Documentación completa actualizada

---

## Autor

Sistema de Gestión de Recursos Humanos (SGRH)  
Módulo rediseñado: 2026-01-20  
Versión: 2.0 (Plazas Individuales)
