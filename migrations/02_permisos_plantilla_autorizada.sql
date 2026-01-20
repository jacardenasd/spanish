-- ============================================================================
-- SGRH - Permisos para módulo de Plantilla Autorizada
-- Fecha: 2026
-- Versión: 1.0
-- Descripción: Inserta permisos necesarios para gestión y visualización
--              de plantilla autorizada
-- ============================================================================

USE sgrh;

-- Insertar permisos si no existen
INSERT INTO permisos (clave, descripcion, modulo)
VALUES 
    ('plantilla.admin', 'Administración completa de Plantilla Autorizada (CRUD)', 'Organización'),
    ('plantilla.ver', 'Ver/consultar Plantilla Autorizada (solo lectura)', 'Organización'),
    ('organizacion.plantilla.edit', 'Editar plazas de la Plantilla Autorizada', 'Organización')
ON DUPLICATE KEY UPDATE 
    descripcion = VALUES(descripcion),
    modulo = VALUES(modulo);

-- Asignar permiso plantilla.admin al rol de Admin Organización (si existe)
-- Esto permite a los administradores de organización gestionar plazas
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT r.rol_id, p.permiso_id
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre LIKE '%Organizaci%n%' 
  AND r.estatus = 1
  AND p.clave = 'plantilla.admin';

-- Asignar permiso plantilla.ver a roles que deban consultar (opcional)
-- Por ejemplo, al rol Empleado o cualquier otro según tu necesidad
-- INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
-- SELECT r.rol_id, p.permiso_id
-- FROM roles r
-- CROSS JOIN permisos p
-- WHERE r.nombre = 'Empleado' 
--   AND r.estatus = 1
--   AND p.clave = 'plantilla.ver';

-- Verificar permisos insertados
SELECT p.permiso_id, p.clave, p.descripcion, p.modulo
FROM permisos p
WHERE p.clave IN ('plantilla.admin', 'plantilla.ver', 'organizacion.plantilla.edit')
ORDER BY p.clave;

-- Verificar asignación a roles
SELECT r.nombre AS rol, p.clave AS permiso, p.descripcion
FROM rol_permisos rp
INNER JOIN roles r ON r.rol_id = rp.rol_id
INNER JOIN permisos p ON p.permiso_id = rp.permiso_id
WHERE p.clave IN ('plantilla.admin', 'plantilla.ver')
ORDER BY r.nombre, p.clave;
