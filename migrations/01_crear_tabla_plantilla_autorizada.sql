-- =====================================================
-- Migración: Crear tabla org_plantilla_autorizada (Plazas Individuales)
-- Fecha: 2026-01-20
-- Descripción: Tabla para registrar cada plaza autorizada de forma individual
--              con su historial de creación, cancelación y congelación
-- =====================================================

-- Crear tabla org_plantilla_autorizada (cada registro = 1 plaza)
DROP TABLE IF EXISTS `org_plantilla_autorizada`;
CREATE TABLE `org_plantilla_autorizada` (
  `plaza_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_plaza` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT 'Código único de la plaza',
  `empresa_id` int(11) NOT NULL COMMENT 'Empresa a la que pertenece',
  `unidad_id` int(11) NOT NULL COMMENT 'Unidad organizacional',
  `adscripcion_id` int(11) NULL DEFAULT NULL COMMENT 'Adscripción opcional',
  `puesto_id` int(11) NULL DEFAULT NULL COMMENT 'Puesto específico opcional',
  
  -- Fechas de ciclo de vida
  `fecha_creacion` date NOT NULL COMMENT 'Fecha de autorización de la plaza',
  `fecha_cancelacion` date NULL DEFAULT NULL COMMENT 'Fecha de cancelación',
  `fecha_congelacion` date NULL DEFAULT NULL COMMENT 'Fecha de congelación',
  
  -- Justificaciones
  `justificacion_creacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Justificación de creación',
  `justificacion_cancelacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT 'Motivo de cancelación',
  `justificacion_congelacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT 'Motivo de congelación',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT 'Notas adicionales',
  
  -- Estado
  `estado` enum('activa','congelada','cancelada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'activa',
  
  -- Ocupación
  `empleado_id` int(11) NULL DEFAULT NULL COMMENT 'Empleado asignado',
  `fecha_asignacion` date NULL DEFAULT NULL COMMENT 'Fecha de asignación',
  
  -- Auditoría
  `created_by` int(11) NULL COMMENT 'Usuario creador',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`plaza_id`) USING BTREE,
  UNIQUE INDEX `uq_codigo_plaza`(`empresa_id`, `codigo_plaza`) USING BTREE,
  INDEX `idx_plaza_empresa_estado`(`empresa_id`, `estado`) USING BTREE,
  INDEX `idx_plaza_unidad`(`unidad_id`) USING BTREE,
  INDEX `idx_plaza_adscripcion`(`adscripcion_id`) USING BTREE,
  INDEX `idx_plaza_puesto`(`puesto_id`) USING BTREE,
  INDEX `idx_plaza_empleado`(`empleado_id`) USING BTREE,
  
  CONSTRAINT `fk_plaza_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_plaza_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `org_unidades` (`unidad_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_plaza_adscripcion` FOREIGN KEY (`adscripcion_id`) REFERENCES `org_adscripciones` (`adscripcion_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_plaza_puesto` FOREIGN KEY (`puesto_id`) REFERENCES `org_puestos` (`puesto_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_plaza_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_plaza_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'Registro individual de plazas autorizadas' ROW_FORMAT = Dynamic;

-- Vista auxiliar
DROP VIEW IF EXISTS `v_plantilla_autorizada`;
CREATE VIEW `v_plantilla_autorizada` AS
SELECT 
  p.plaza_id,
  p.codigo_plaza,
  p.empresa_id,
  e.nombre AS empresa_nombre,
  p.unidad_id,
  u.nombre AS unidad_nombre,
  p.adscripcion_id,
  a.nombre AS adscripcion_nombre,
  p.puesto_id,
  pu.nombre AS puesto_nombre,
  p.fecha_creacion,
  p.fecha_cancelacion,
  p.fecha_congelacion,
  p.justificacion_creacion,
  p.justificacion_cancelacion,
  p.justificacion_congelacion,
  p.observaciones,
  p.estado,
  p.empleado_id,
  p.fecha_asignacion,
  emp.nombre AS empleado_nombre,
  emp.apellido_paterno AS empleado_apellido_paterno,
  emp.apellido_materno AS empleado_apellido_materno,
  emp.no_emp AS empleado_no_emp,
  emp.estatus AS empleado_estatus,
  CASE 
    WHEN p.estado = 'cancelada' THEN 'Cancelada'
    WHEN p.estado = 'congelada' THEN 'Congelada'
    WHEN p.empleado_id IS NOT NULL THEN 'Ocupada'
    ELSE 'Vacante'
  END AS estado_ocupacion,
  p.created_by,
  CONCAT_WS(' ', usr.nombre, usr.apellido_paterno, usr.apellido_materno) AS creado_por_usuario,
  p.created_at,
  p.updated_at
FROM org_plantilla_autorizada p
INNER JOIN empresas e ON e.empresa_id = p.empresa_id
INNER JOIN org_unidades u ON u.unidad_id = p.unidad_id
LEFT JOIN org_adscripciones a ON a.adscripcion_id = p.adscripcion_id
LEFT JOIN org_puestos pu ON pu.puesto_id = p.puesto_id
LEFT JOIN empleados emp ON emp.empleado_id = p.empleado_id
LEFT JOIN usuarios usr ON usr.usuario_id = p.created_by;
