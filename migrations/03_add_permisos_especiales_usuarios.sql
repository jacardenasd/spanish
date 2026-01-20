-- ============================================================================
-- SGRH - Agregar columna permisos_especiales a tabla usuarios
-- Fecha: 2026-01-20
-- Descripción: Permite almacenar permisos específicos por usuario
-- ============================================================================

USE sgrh;

-- Agregar columna si no existe (MySQL 5.7 compatible)
SET @preparedStatement = (SELECT IF(
	(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
	 WHERE TABLE_SCHEMA=DATABASE()
		 AND TABLE_NAME='usuarios'
		 AND COLUMN_NAME='permisos_especiales') > 0,
	'SELECT 1',
	'ALTER TABLE usuarios ADD COLUMN permisos_especiales TEXT NULL COMMENT "JSON con permisos específicos del usuario: {permisos: [], unidades: []}"'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Verificar columna agregada
DESCRIBE usuarios;
