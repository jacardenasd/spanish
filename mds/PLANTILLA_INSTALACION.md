# Guía de Instalación - Módulo de Plantilla Autorizada v2.1

## Introducción

Este documento describe cómo instalar y configurar completamente el módulo mejorado de Plantilla Autorizada con asignación de empleados y sistema de permisos.

---

## Requisitos Previos

- Base de datos SGRH ya creada y funcional
- PHP 5.7 o superior
- MySQL 5.7 o MariaDB 10.2+
- Módulo de organización (unidades, adscripciones, puestos) instalado

---

## Pasos de Instalación

### Paso 1: Ejecutar migración de tabla (si aún no existe)

Si aún no tiene la tabla `org_plantilla_autorizada`, ejecute:

```bash
# Archivo: migrations/01_crear_tabla_plantilla_autorizada.sql
# En phpMyAdmin o terminal:
mysql -u [usuario] -p sgrh < migrations/01_crear_tabla_plantilla_autorizada.sql
```

**Verifica que la tabla exista:**
```sql
SHOW TABLES LIKE 'org_plantilla_autorizada';
DESC org_plantilla_autorizada;
```

### Paso 2: Ejecutar script de permisos

```bash
# Archivo: migrations/02_permisos_plantilla_autorizada.sql
# En phpMyAdmin o terminal:
mysql -u [usuario] -p sgrh < migrations/02_permisos_plantilla_autorizada.sql
```

**Verifica que los permisos se crearon:**
```sql
SELECT * FROM permisos WHERE clave LIKE 'plantilla%';
SELECT r.nombre, p.clave 
FROM rol_permisos rp
JOIN roles r ON r.rol_id = rp.rol_id
JOIN permisos p ON p.permiso_id = rp.permiso_id
WHERE p.clave LIKE 'plantilla%';
```

### Paso 3: Verificar archivos nuevos

Confirma que estos archivos están en su lugar:

✅ `public/admin_org_plantilla.php` - Interfaz administrativa (mejorada v2.1)
✅ `public/plantilla.php` - Interfaz de usuario (nueva)
✅ `public/ajax_get_adscripciones.php` - Endpoint AJAX (nueva)
✅ `migrations/02_permisos_plantilla_autorizada.sql` - Script de permisos (nueva)
✅ `mds/PLANTILLA_AUTORIZADA_README.md` - Documentación (actualizada)

### Paso 4: Actualizar sidebar

El archivo `includes/layout/sidebar.php` ya debe estar actualizado con:

```php
<?php if (can('plantilla.ver') && !can('organizacion.admin') && !can('plantilla.admin')): ?>
<li class="nav-item">
  <a href="<?php echo ASSET_BASE; ?>public/plantilla.php"
    class="nav-link <?php echo is_active('plantilla', $active_menu); ?>">
    Plantilla Autorizada
  </a>
</li>
<?php endif; ?>
```

---

## Configuración de Acceso

### Opción A: Asignar a Admin Organización (Recomendado)

Si ya tienen un rol de "Admin Organización", el permiso `plantilla.admin` se asignará automáticamente por el script SQL.

**Verifica en Admin Usuarios:**
1. Ir a **Administración → Usuarios**
2. Seleccionar un usuario admin
3. Clic en botón de roles
4. Debe ver rol "Admin Organización" u otro que tenga permiso `plantilla.admin`

### Opción B: Crear nuevo rol (Avanzado)

Si desea crear un rol específico para Plantilla:

```sql
-- Crear rol
INSERT INTO roles (nombre, descripcion, estatus)
VALUES ('Admin Plantilla', 'Administración de Plantilla Autorizada', 1);

-- Obtener IDs (ejecutar por separado)
SELECT rol_id FROM roles WHERE nombre = 'Admin Plantilla';
SELECT permiso_id FROM permisos WHERE clave = 'plantilla.admin';

-- Asignar permiso al rol (reemplazar IDs según resultado anterior)
INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (5, 15); -- Ejemplo con IDs
```

### Opción C: Permisos de solo lectura

Para usuarios que solo deben ver la plantilla:

```sql
-- Asignar plantilla.ver a un rol específico
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT r.rol_id, p.permiso_id
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre = 'Empleado'  -- o el rol que desee
  AND p.clave = 'plantilla.ver';
```

---

## Pruebas Post-Instalación

### Test 1: Interfaz Administrativa

1. Log in como usuario con permiso `plantilla.admin`
2. Navega a **Organización → Plantilla Autorizada (Admin)**
3. Verifica que ves:
   - ✅ 6 tarjetas de estadísticas
   - ✅ Botón "Crear Nueva Plaza"
   - ✅ Tabla con plazas existentes
   - ✅ Filtros por estado, unidad, departamento
   - ✅ Botones de acción en dropdown de cada plaza

### Test 2: Crear Plaza

1. Clic en "Crear Nueva Plaza"
2. Llena los campos:
   - Código Plaza (opcional): `TEST-001`
   - Fecha Creación: Hoy
   - Unidad: Selecciona una
   - Departamento: Selecciona uno
   - Puesto: Busca usando Select2 (nueva feature)
   - Justificación: "Plaza de prueba"
3. Clic Crear
4. Verifica que aparece en tabla con estado "Activa"

### Test 3: Asignar Empleado

1. En la tabla, busca la plaza TEST-001
2. Clic en el icono de menú (⋮) y luego "Asignar Empleado"
3. En modal:
   - Campo empleado: Busca un empleado activo con Select2 (nueva feature)
   - Fecha Asignación: Auto-llenada con hoy
4. Clic "Asignar Empleado"
5. Verifica que:
   - ✅ Plaza ahora muestra empleado en tabla
   - ✅ Estado ocupación es "Ocupada"
   - ✅ Bitácora registró la asignación

### Test 4: Interfaz Usuario (solo lectura)

1. Log in como usuario con permiso `plantilla.ver` (pero NO plantilla.admin)
2. Navega a **Organización → Plantilla Autorizada** (sin "(Admin)")
3. Verifica que:
   - ✅ Ve las estadísticas
   - ✅ Ve tabla de plazas
   - ✅ Puede filtrar
   - ✅ Puede ver detalles
   - ✅ NO hay botón "Crear Nueva Plaza"
   - ✅ NO hay dropdown de acciones
   - ✅ NO puede editar

### Test 5: Filtrado Ajax

1. En cualquiera de las dos interfaces
2. Selecciona una unidad en dropdown "Unidad"
3. Verifica que:
   - ✅ Dropdown "Departamento" se actualiza por Ajax
   - ✅ Solo muestra departamentos de esa unidad
4. Clic en "Filtrar"
5. Tabla se actualiza

### Test 6: Desasignar Empleado

1. Busca una plaza ocupada en la tabla
2. Clic en menú (⋮) y "Desasignar Empleado"
3. Modal muestra confirmación con nombre del empleado actual
4. Clic "Desasignar"
5. Verifica que:
   - ✅ Empleado se desvincula
   - ✅ Plaza pasa a "Vacante"
   - ✅ Bitácora registró la desasignación

---

## Solución de Problemas

### "No tengo acceso a Plantilla Autorizada"

**Causa:** No tienes permiso `plantilla.admin`

**Solución:**
1. Pide al admin que te asigne el permiso
2. O asígnate mediante SQL:
```sql
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT ur.rol_id, p.permiso_id
FROM usuario_roles ur
CROSS JOIN permisos p
WHERE ur.usuario_id = 1  -- Tu ID de usuario
  AND p.clave = 'plantilla.admin';
```

### "El buscador de empleados no funciona"

**Causa:** Select2 no está cargado o hay error de JS

**Solución:**
1. Abre Developer Tools (F12)
2. Checks Console por errores
3. Verifica que jquery y select2 estén cargados:
```javascript
console.log($.fn.select2);  // Debe mostrar función
```
4. Si no funciona, verifica que el modal tiene ID `modalAsignarEmpleado`

### "Filtrado Ajax no funciona"

**Causa:** Archivo `ajax_get_adscripciones.php` no existe o tiene errores

**Solución:**
1. Verifica que el archivo existe en `public/ajax_get_adscripciones.php`
2. Prueba el endpoint directamente:
```
http://localhost/sgrh/public/ajax_get_adscripciones.php?unidad_id=1
```
3. Debe retornar JSON, no HTML
4. Si ves error PHP, revisa logs

### "No puedo desasignar empleado"

**Causa:** La plaza podría estar congelada o cancelada

**Solución:**
1. Abre el modal de detalles de la plaza
2. Verifica que Estado sea "Activa"
3. Si está congelada, descongelala primero

---

## Configuración Avanzada

### Personalizar límites de búsqueda

En `public/admin_org_plantilla.php`, búsqueda de empleados:

```php
$emp_sql = "SELECT empleado_id, no_emp, nombre, apellido_paterno, apellido_materno 
            FROM empleados 
            WHERE empresa_id = :empresa_id AND estatus = 'ACTIVO'
            LIMIT 100";  // Aumentar si es necesario
```

### Cambiar idioma de Select2

En la inicialización de Select2, puedes especificar idioma:

```javascript
$('.select2-empleados').select2({
    language: 'es'  // o 'en', 'fr', etc.
});
```

### Personalizar formato de código de plaza

En `admin_org_plantilla.php`, función `generar_codigo_plaza()`:

```php
function generar_codigo_plaza($empresa_id, $unidad_id, $adscripcion_id) {
    // Actualmente: DEMO-00001, DEMO-00002, etc.
    // Personaliza según tu necesidad
}
```

---

## Mantenimiento

### Limpiar plazas canceladas antiguas

```sql
-- Eliminar plazas canceladas más de 2 años atrás
DELETE FROM org_plantilla_autorizada
WHERE estado = 'cancelada'
  AND fecha_cancelacion < DATE_SUB(NOW(), INTERVAL 2 YEAR);
```

### Auditoría de cambios

Ver todos los cambios realizados:

```sql
SELECT * FROM bitacora
WHERE modulo = 'plantilla_autorizada'
ORDER BY created_at DESC
LIMIT 50;
```

### Reporte de plazas por estado

```sql
SELECT 
    estado,
    COUNT(*) as cantidad,
    SUM(CASE WHEN empleado_id IS NOT NULL THEN 1 ELSE 0 END) as ocupadas,
    SUM(CASE WHEN empleado_id IS NULL THEN 1 ELSE 0 END) as vacantes
FROM org_plantilla_autorizada
WHERE empresa_id = 1
GROUP BY estado;
```

---

## Soporte

Para errores o preguntas:
1. Revisa logs del servidor: `php error.log`
2. Revisa logs de MySQL: `mysql.log`
3. Verifica permisos de archivos
4. Consulta la documentación: [PLANTILLA_AUTORIZADA_README.md](PLANTILLA_AUTORIZADA_README.md)

---

**Versión:** 2.1  
**Fecha:** 2026-01-22  
**Estado:** ✅ Producción
