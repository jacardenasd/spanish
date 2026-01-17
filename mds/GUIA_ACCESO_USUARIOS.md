# Guía Completa: Acceso a Clima Laboral y Contratos

## Resumen de cambios

### 1. Permisos creados
- **clima.admin** (permiso_id=14): Administrar clima laboral
- **contratos.ver**: Ver Kit de Contratación
- **contratos.crear**: Crear/editar contratos

### 2. Roles y permisos asignados

| Rol | Clima | Contratos | Notas |
|-----|-------|-----------|-------|
| Empleado (1) | clima.ver, clima.responder | ❌ | Acceso básico |
| Jefe (2) | clima.ver, clima.responder | ❌ | Acceso básico |
| RH Operativo (3) | clima.ver, clima.responder | ❌ | Sin admin |
| **RH Admin (4)** | ⭐ | ✅ contratos.ver, contratos.crear | Recomendado para supervisores |
| **Super Admin (5)** | ⭐ | ✅ contratos.ver, contratos.crear | Control total |

### 3. Páginas protegidas

#### Clima Laboral
- `public/clima_admin.php` - Panel administrativo
- `public/clima_generar_elegibles.php` - Generar universo elegible
- `public/clima_participacion.php` - Dashboard de participación
- `public/clima_planes.php` - Planes de acción
- `public/clima_periodos.php` - Crear períodos
- `public/clima_resultados.php` - Ver resultados
- `public/clima_dimensiones.php` - Configurar dimensiones

**Permisos requeridos**: `organizacion.admin` O `clima.admin`

#### Contratos
- `public/contratos_generar.php` - Listar empleados en contratación
- `public/contratos_importar_empleado.php` - Importar empleados tipo 1
- `public/contratos_nuevo_empleado.php` - Agregar empleado nuevo

**Permisos requeridos**: `contratos.crear` O `usuarios.admin`

---

## Flujo de implementación

### Paso 1: Ejecutar SQL de permisos

```sql
-- Crear rol Clima Admin (si no existe)
INSERT INTO roles (nombre, descripcion, estatus)
VALUES ('Clima Admin', 'Administración de Clima Laboral', 1)
ON DUPLICATE KEY UPDATE estatus = VALUES(estatus);

-- Vincular clima.admin al rol
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT r.rol_id, p.permiso_id
FROM roles r
JOIN permisos p ON p.clave = 'clima.admin'
WHERE r.nombre = 'Clima Admin';

-- Crear permisos de contratos
INSERT INTO permisos (clave, descripcion, modulo)
VALUES 
  ('contratos.ver', 'Ver Kit de Contratación', 'Contratos'),
  ('contratos.crear', 'Crear/editar contratos', 'Contratos')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- Asignar permisos de contratos a RH Admin y Super Admin
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT 4, p.permiso_id FROM permisos p WHERE p.clave IN ('contratos.ver', 'contratos.crear');

INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT 5, p.permiso_id FROM permisos p WHERE p.clave IN ('contratos.ver', 'contratos.crear');
```

### Paso 2: Otorgar acceso a usuarios (vía UI)

#### Para acceso a Clima Laboral

1. Ir a **Administración → Usuarios**
2. Buscar el usuario por No. empleado, RFC o nombre
3. Asegurar asignación a empresa (botón con escudo ⚔️)
4. Hacer clic en el botón de **Roles** (ícono de lista 📋)
5. Marcar **"Clima Admin"**
6. Guardar roles
7. Repetir para el segundo usuario

#### Para acceso a Contratos

1. Ir a **Administración → Usuarios**
2. Buscar el usuario
3. Asegurar asignación a empresa
4. Hacer clic en **Roles**
5. Marcar **"RH Admin"** (que incluye contratos.crear) o agregar el rol "Clima Admin" si solo necesita clima
6. Guardar
7. Repetir para más usuarios

### Paso 3: Verificar acceso

Cada usuario debe:
1. **Cerrar sesión completamente**
2. **Iniciar sesión nuevamente**
3. **Seleccionar empresa** (carga permisos desde DB)
4. Verificar que vea:
   - ✅ "Clima Laboral" en Administración (si tiene clima.admin)
   - ✅ "Kit de Contratación" en menú (si tiene contratos.crear)

---

## Casos de uso comunes

### Supervisor de Clima Laboral
- Rol: **Clima Admin**
- Puede: Crear períodos, generar elegibles, ver participación, gestionar planes, ver resultados
- NO puede: Crear contratos, importar nóminas, administrar usuarios

### Administrador RH completo
- Rol: **RH Admin**
- Puede: Todo lo de Clima Admin + crear contratos, importar empleados
- NO puede: Crear/editar usuarios, cambiar roles

### Super Administrador
- Rol: **Super Admin**
- Puede: Todo (acceso total indicado por `*` en permisos)

---

## Revertir cambios (si es necesario)

### Remover acceso a un usuario específico

```sql
-- Quitar todos los roles (excepto Empleado)
DELETE FROM usuario_roles 
WHERE usuario_id = <ID_USUARIO> 
  AND rol_id NOT IN (SELECT rol_id FROM roles WHERE nombre = 'Empleado');

-- O quitar solo un rol específico
DELETE FROM usuario_roles 
WHERE usuario_id = <ID_USUARIO> 
  AND rol_id = (SELECT rol_id FROM roles WHERE nombre = 'Clima Admin');
```

### Desactivar el rol "Clima Admin"

```sql
UPDATE roles 
SET estatus = 0 
WHERE nombre = 'Clima Admin';
```

---

## Notas técnicas

- Los permisos se cargan **al seleccionar empresa** en sesión (incluido en `cargar_permisos_sesion()`)
- Si un usuario tiene `es_admin=1` en `usuario_empresas`, obtiene el comodín `*` (acceso a todo)
- El sistema valida permisos **antes de mostrar contenido** en páginas protegidas
- El menú lateral **solo muestra opciones habilitadas** para el usuario actual

---

## Archivos modificados

1. ✅ `includes/permisos.php` - Agregadas funciones `can_any()` y `require_perm_any()`
2. ✅ `public/clima_admin.php` - Ahora acepta `clima.admin`
3. ✅ `public/clima_generar_elegibles.php` - Ahora acepta `clima.admin`
4. ✅ `public/clima_participacion.php` - Ahora acepta `clima.admin`
5. ✅ `public/clima_planes.php` - Ahora acepta `clima.admin`
6. ✅ `public/clima_periodos.php` - Ahora acepta `clima.admin`
7. ✅ `public/clima_resultados.php` - Ahora acepta `clima.admin`
8. ✅ `public/clima_dimensiones.php` - Ahora acepta `clima.admin`
9. ✅ `includes/layout/sidebar.php` - Muestra Clima Laboral si tiene `clima.admin`
10. ✅ `public/contratos_generar.php` - Protegido con `contratos.crear`
11. ✅ `public/contratos_importar_empleado.php` - Protegido con `contratos.crear`
12. ✅ `public/contratos_nuevo_empleado.php` - Protegido con `contratos.crear`

## Scripts SQL proporcionados

- `agregar_rol_clima_admin.sql` - Crear rol y vincular permiso clima.admin
- `agregar_permisos_contratos.sql` - Crear permisos de contratos y asignar a roles
