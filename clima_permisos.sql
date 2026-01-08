-- SGRH - Script de actualización: Permisos para módulo de Clima Laboral
-- Ejecutar después de tener el catálogo base de permisos

-- Insertar permisos específicos de clima (si no existen)
INSERT INTO permisos (clave, descripcion, modulo)
SELECT 'clima.admin', 'Administrar clima laboral (configuración completa)', 'Clima Laboral'
WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE clave='clima.admin');

INSERT INTO permisos (clave, descripcion, modulo)
SELECT 'clima.ver_resultados', 'Ver resultados de clima laboral', 'Clima Laboral'
WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE clave='clima.ver_resultados');

INSERT INTO permisos (clave, descripcion, modulo)
SELECT 'clima.responder', 'Contestar encuesta de clima', 'Clima Laboral'
WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE clave='clima.responder');

INSERT INTO permisos (clave, descripcion, modulo)
SELECT 'clima.planes', 'Gestionar planes de acción', 'Clima Laboral'
WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE clave='clima.planes');

-- Nota: 
-- El permiso 'organizacion.admin' existente ya cubre la administración completa de clima
-- Los permisos arriba son opcionales para segregación más granular si se requiere en el futuro
