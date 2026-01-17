# Módulo de Clima Laboral - SGRH

## Descripción General

Sistema completo de evaluación de clima organizacional para Farmacéutica Hispanoamericana, con enfoque en RH corporativo estratégico, cumplimiento de participación mínima (90%) y generación de planes de acción.

## Arquitectura

### Tablas principales
- `clima_periodos`: Periodos de evaluación por empresa/año
- `clima_dimensiones`: Dimensiones globales de evaluación (ej: Relación con jefe, compañeros, empresa, trabajo)
- `clima_reactivos`: Preguntas Likert asociadas a dimensiones
- `clima_elegibles`: Empleados elegibles por periodo+empresa+unidad (fecha de corte)
- `clima_respuestas`: Respuestas Likert (1-5) de empleados
- `clima_publicacion`: Control de visibilidad de resultados por unidad (requiere >=90% participación)
- `clima_planes`: Planes de acción por periodo+empresa+unidad+dimensión

### Modelo organizacional
- Integración con `org_unidades` (Direcciones)
- Multiempresa (`empresa_id` en todas las tablas críticas)
- Elegibilidad basada en fecha de corte (empleados activos en esa fecha)

## Componentes implementados

### 1. Menú Principal (`clima.php`)
**Ruta:** `/public/clima.php`  
**Función:** Hub de navegación con estadísticas rápidas
- Tarjetas con KPIs: periodos activos, respuestas totales, promedio general, planes pendientes
- Información de periodo activo
- Botón "Contestar encuesta" para empleados elegibles
- Acceso rápido a todos los módulos administrativos (solo para `organizacion.admin`)
- Guía rápida del flujo completo

**Permisos:** Disponible para todos los usuarios autenticados (control interno por rol)

---

### 2. Administración de Dimensiones y Reactivos (`clima_dimensiones.php`)
**Ruta:** `/public/clima_dimensiones.php`  
**Función:** Configuración de la encuesta

#### Dimensiones
- CRUD completo: crear, editar, eliminar, activar/desactivar
- Campos: nombre, orden, estatus
- Validación de unicidad por nombre
- Protección: no se puede eliminar si tiene reactivos asociados

#### Reactivos (preguntas)
- CRUD completo asociado a dimensión
- Campos: dimensión, texto (hasta 400 caracteres), orden, estatus
- Validación: no se puede eliminar si tiene respuestas registradas
- DataTable con búsqueda y ordenamiento

**Permisos:** `organizacion.admin`

**Funcionalidades:**
- Modales Bootstrap para edición inline
- Validación CSRF
- JavaScript para edición/eliminación sin recarga

---

### 3. Gestión de Periodos (`clima_periodos.php`)
**Ruta:** `/public/clima_periodos.php` (existente, sin modificaciones)  
**Función:** Crear y administrar periodos de evaluación

- Campos: año, fecha inicio/fin, fecha corte elegibilidad, estatus (borrador/publicado/cerrado)
- Validación de unicidad por empresa+año
- Protección: no se puede eliminar si tiene respuestas registradas
- Creado por usuario logueado

**Estados del periodo:**
- `borrador`: En configuración
- `publicado`: Encuesta disponible para empleados
- `cerrado`: Periodo finalizado, solo consulta

**Permisos:** `organizacion.admin`

---

### 4. Generar Elegibles (`clima_generar_elegibles.php`)
**Ruta:** `/public/clima_generar_elegibles.php` (existente, sin modificaciones)  
**Función:** Definir empleados que participarán en el periodo

- Selección por fecha de corte de elegibilidad
- Filtros: empresa, periodo, criterios de empleado activo
- Reglas de negocio:
  - Solo empleados con `estatus='activo'`
  - Solo empleados con `fecha_ingreso <= fecha_corte_elegibilidad`
  - Excluyentes configurables (ej: tipo de empleado, unidad)
- Resultado: tabla `clima_elegibles` con flag `elegible=1` y `motivo_no_elegible` para auditoría

**Permisos:** `organizacion.admin`

#### Excepciones de elegibilidad ✨ NUEVO
- Tabla: `clima_excepciones` (ver `clima_excepciones.sql` en raíz)
- Uso: permite incluir por excepción a empleados no elegibles (ej. recién ingresados), opcionalmente cambiando su `unidad_id` para contabilización.
- UI: en la misma pantalla existe una tarjeta para:
   - Agregar excepción por No. empleado
   - Establecer override de Unidad (opcional)
   - Listar y eliminar excepciones del periodo
- Integración: al generar el snapshot, las excepciones marcan `elegible=1` y `motivo_no_elegible='EXCEPCION'`, con `unidad_id` usando `unidad_id_override` cuando se especifica.

---

### 5. Monitoreo de Participación (`clima_participacion.php`)
**Ruta:** `/public/clima_participacion.php` (existente, sin modificaciones)  
**Función:** Control de participación y publicación de resultados

- Dashboard por Dirección (unidad):
  - Total elegibles
  - Total respondieron (DISTINCT empleado_id en `clima_respuestas`)
  - Porcentaje de participación
  - Estatus de publicación
- Acción: Publicar/Despublicar resultados por unidad
- Regla: Solo se puede publicar si participación >= 90%
- Tabla `clima_publicacion`: control de visibilidad (`habilitado=1`)

**Permisos:** `organizacion.admin`

---

### 6. Dashboard de Resultados (`clima_resultados.php`) ✨ NUEVO
**Ruta:** `/public/clima_resultados.php`  
**Función:** Dashboard ejecutivo con análisis de resultados

#### Componentes visuales:
1. **Gauge global**: Promedio general de la empresa (escala 1-5) con ECharts
2. **Ranking por Dirección**: Tabla con:
   - Posición
   - Dirección
   - Total respondieron
   - Promedio general (badge con color según rango: <3.0 rojo, <4.0 amarillo, >=4.0 verde)
   - Promedios por cada dimensión
   - Estatus de publicación
3. **Gráfico comparativo**: Bar chart con promedios por dimensión para todas las direcciones

#### Reglas de visualización:
- Solo muestra unidades con `clima_publicacion.habilitado=1`
- Filtro por periodo
- Cálculos:
  - Promedio global: `AVG(clima_respuestas.valor)` para empleados elegibles de la empresa
  - Promedio por unidad: `AVG(valor)` agrupado por `unidad_id`
  - Promedio por dimensión: JOIN con `clima_reactivos` filtrando por `dimension_id`

**Permisos:** `organizacion.admin`

**Tecnologías:** DataTables, ECharts (gauge y bar chart)

---

### 7. Planes de Acción (`clima_planes.php`) ✨ NUEVO
**Ruta:** `/public/clima_planes.php`  
**Función:** Gestión de planes de mejora continua

#### Campos del plan:
- Periodo
- Empresa
- Dirección (unidad)
- Dimensión
- Problema identificado (300 caracteres)
- Acción (400 caracteres)
- Responsable (120 caracteres)
- Fecha de compromiso
- Indicador de cumplimiento (opcional, 200 caracteres)
- Estatus: `pendiente`, `en_proceso`, `cumplido`

#### Funcionalidades:
- CRUD completo con modales
- Estadísticas en tarjetas: total, pendientes, en proceso, cumplidos
- DataTable con ordenamiento por estatus y fecha
- Filtro por periodo
- Validación CSRF
- Auditoría: `created_by` (usuario creador), `created_at`, `updated_at`

**Permisos:** `organizacion.admin`

---

### 8. Contestar Encuesta (`clima_contestar.php`)
**Ruta:** `/public/clima_contestar.php` (existente, sin modificaciones)  
**Función:** Interface de captura para empleados

- Validación de elegibilidad en `clima_elegibles`
- Escala Likert 1-5 para cada reactivo activo
- Preguntas abiertas adicionales (opcional, tabla separada)
- Guardado incremental (permite pausar y continuar)
- Botón "Finalizar" que bloquea la encuesta
- Tabla `clima_envios` (opcional): control de finalizados

**Permisos:** Empleados elegibles (validación interna)

---

## Flujo completo del módulo

```
1. [Admin] Crear periodo → clima_periodos
2. [Admin] Configurar dimensiones/reactivos → clima_dimensiones, clima_reactivos
3. [Admin] Generar elegibles → clima_elegibles (según fecha de corte)
4. [Admin] Cambiar estatus a "Publicado" → clima_periodos.estatus='publicado'
5. [Empleados] Contestar encuesta → clima_respuestas
6. [Admin] Monitorear participación → clima_participacion
7. [Admin] Publicar resultados (si >=90%) → clima_publicacion.habilitado=1
8. [Admin] Analizar resultados → clima_resultados
9. [Admin] Crear planes de acción → clima_planes
10. [Admin] Dar seguimiento a planes → clima_planes.estatus
```

## Navegación

### Sidebar
- **Menú principal:** "Clima laboral" (visible para todos)
- **Submenú Administración → Clima Laboral** (solo `organizacion.admin`):
  - Dimensiones y Reactivos
  - Periodos
  - Generar Elegibles
  - Participación
  - Resultados
  - Planes de Acción

### Acceso directo desde menú principal
[clima.php](clima.php) funciona como hub central con botones de acceso rápido a todos los módulos.

## Permisos

### Permiso principal utilizado:
- `organizacion.admin`: Control total del módulo (actual)

### Permisos opcionales (para segregación futura):
Script en [`clima_permisos.sql`](clima_permisos.sql) incluye:
- `clima.admin`: Administración completa
- `clima.ver_resultados`: Ver resultados
- `clima.responder`: Contestar encuesta
- `clima.planes`: Gestionar planes

**Nota:** Actualmente el sistema usa `organizacion.admin` para simplificar. Los permisos opcionales están listos para implementación granular si se requiere.

## Reglas de negocio críticas

### Participación mínima
- Solo se pueden publicar resultados de una Dirección si **participación >= 90%**
- Cálculo: `(respondidos / elegibles) * 100`
- `respondidos`: COUNT(DISTINCT empleado_id) en `clima_respuestas`
- `elegibles`: COUNT(*) en `clima_elegibles` WHERE `elegible=1`

### Elegibilidad
- Basada en **fecha de corte de elegibilidad** del periodo
- Solo empleados activos en esa fecha
- Se registra en `clima_elegibles` con `empresa_id`, `unidad_id`, `elegible`, `motivo_no_elegible`

### Consistencia de datos
- No se puede eliminar dimensión con reactivos asociados
- No se puede eliminar reactivo con respuestas registradas
- No se puede eliminar periodo con respuestas registradas

### Multiempresa
- Todas las consultas filtran por `empresa_id` de sesión
- Periodos son por empresa (unicidad: empresa+año)
- Resultados y planes son por empresa+periodo

## Consideraciones técnicas

### Compatibilidad PHP
- **Compatible PHP 5.7**: NO se usa operador null coalescing (`??`)
- Sustitución: `isset($_POST['x']) ? $_POST['x'] : valor_default`

### Seguridad
- Validación CSRF en todos los formularios
- Guards: `require_login()`, `require_empresa()`, `require_perm()`
- PDO con prepared statements
- Escapado HTML con `htmlspecialchars()` (función `h()`)

### Performance
- Índices en `clima_elegibles`: `(periodo_id, empresa_id, unidad_id, elegible)`
- Índices en `clima_respuestas`: `(periodo_id, empleado_id, reactivo_id)` (PK compuesta)
- DataTables con paginación lado cliente (<=1000 registros) o servidor (opcional)

### Dependencias frontend
- Bootstrap 4.x (incluido en plantilla Limitless)
- DataTables (español, ordenamiento, búsqueda)
- ECharts (gráficos gauge y bar chart)
- jQuery (modales, AJAX)

## Archivos creados/modificados

### Nuevos archivos:
- `/public/clima.php` - Menú principal
- `/public/clima_dimensiones.php` - Admin dimensiones/reactivos
- `/public/clima_resultados.php` - Dashboard resultados
- `/public/clima_planes.php` - Planes de acción
- `/clima_permisos.sql` - Script de permisos
- `/CLIMA_README.md` - Esta documentación

### Archivos modificados:
- `/includes/layout/sidebar.php` - Navegación actualizada

### Archivos existentes (sin cambios):
- `/public/clima_periodos.php`
- `/public/clima_generar_elegibles.php`
- `/public/clima_participacion.php`
- `/public/clima_contestar.php`
- `/public/clima_guardar_respuesta.php`
- `/public/clima_guardar_abierta.php`
- `/public/clima_finalizar.php`

## Pruebas recomendadas

1. **Configuración inicial:**
   - Crear dimensiones (ej: 4 dimensiones: Jefe, Compañeros, Empresa, Trabajo)
   - Crear reactivos (mínimo 5 por dimensión, total ~20)
   - Crear periodo 2026

2. **Generación de elegibles:**
   - Definir fecha de corte (ej: 2025-12-31)
   - Ejecutar generación
   - Validar lista de elegibles por unidad

3. **Publicación:**
   - Cambiar estatus a "Publicado"
   - Validar que empleados elegibles ven botón "Contestar"

4. **Captura:**
   - Contestar encuesta con varios empleados
   - Validar guardado incremental
   - Finalizar encuestas

5. **Participación:**
   - Verificar porcentajes por unidad
   - Intentar publicar con <90% (debe rechazar)
   - Alcanzar >=90% y publicar

6. **Resultados:**
   - Verificar dashboard con promedios correctos
   - Validar gráficos (gauge y bar chart)
   - Exportar datos (opcional)

7. **Planes de acción:**
   - Crear planes para dimensiones con puntaje bajo
   - Actualizar estatus: pendiente → en proceso → cumplido
   - Validar estadísticas

## Mantenimiento futuro

### Extensiones sugeridas:
- **Exportación a Excel:** Resultados por unidad/dimensión
- **Reportes PDF:** Dashboards ejecutivos descargables
- **Notificaciones:** Email/SMS cuando se publique encuesta
- **Dashboard empleado:** Vista simplificada para empleados (solo su unidad)
- **Comparativa histórica:** Tendencias año sobre año
- **Alertas automáticas:** Planes de acción próximos a vencimiento
- **Integración con metas:** Vincular planes con objetivos organizacionales

### Mejoras técnicas:
- **API REST:** Endpoints para integraciones externas
- **WebSockets:** Actualización en tiempo real de participación
- **Caché:** Redis para resultados precalculados
- **Segregación de permisos:** Implementar permisos granulares de `clima_permisos.sql`

## Contacto y soporte

**Proyecto:** SGRH - Farmacéutica Hispanoamericana  
**Módulo:** Clima Laboral  
**Desarrollador:** Juan Cardenas (jacardenas@outlook.com)  
**Fecha:** Enero 2026  
**Versión:** 1.0

---

**Nota final:** Este módulo cumple con los criterios de RH corporativo, escalabilidad y trazabilidad del SGRH. Está listo para uso en producción tras validación con datos reales.
