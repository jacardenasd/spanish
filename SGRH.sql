/*
 Navicat Premium Dump SQL

 Source Server         : localhost
 Source Server Type    : MySQL
 Source Server Version : 50724 (5.7.24)
 Source Host           : localhost:3306
 Source Schema         : sgrh

 Target Server Type    : MySQL
 Target Server Version : 50724 (5.7.24)
 File Encoding         : 65001

 Date: 19/01/2026 18:15:22
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for bitacora
-- ----------------------------
DROP TABLE IF EXISTS `bitacora`;
CREATE TABLE `bitacora`  (
  `bitacora_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NULL DEFAULT NULL,
  `usuario_id` int(11) NULL DEFAULT NULL,
  `modulo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `accion` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `detalle_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`bitacora_id`) USING BTREE,
  INDEX `idx_bit_empresa_fecha`(`empresa_id`, `created_at`) USING BTREE,
  INDEX `idx_bit_usuario_fecha`(`usuario_id`, `created_at`) USING BTREE,
  CONSTRAINT `fk_bit_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bit_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 29 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for cat_bancos
-- ----------------------------
DROP TABLE IF EXISTS `cat_bancos`;
CREATE TABLE `cat_bancos`  (
  `banco_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_banco` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `orden` int(11) NULL DEFAULT 0,
  `activo` tinyint(1) NULL DEFAULT 1,
  PRIMARY KEY (`banco_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 16 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for cat_codigos_postales
-- ----------------------------
DROP TABLE IF EXISTS `cat_codigos_postales`;
CREATE TABLE `cat_codigos_postales`  (
  `cp_id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_postal` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipio` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `colonia` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`cp_id`) USING BTREE,
  UNIQUE INDEX `unique_cp_mun_col`(`codigo_postal`, `estado`, `municipio`, `colonia`) USING BTREE,
  INDEX `idx_cp`(`codigo_postal`) USING BTREE,
  INDEX `idx_estado`(`estado`) USING BTREE,
  INDEX `idx_municipio`(`municipio`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for cat_escolaridades
-- ----------------------------
DROP TABLE IF EXISTS `cat_escolaridades`;
CREATE TABLE `cat_escolaridades`  (
  `escolaridad_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` int(11) NULL DEFAULT 0,
  `activo` tinyint(1) NULL DEFAULT 1,
  PRIMARY KEY (`escolaridad_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for cat_parentescos
-- ----------------------------
DROP TABLE IF EXISTS `cat_parentescos`;
CREATE TABLE `cat_parentescos`  (
  `parentesco_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` int(11) NULL DEFAULT 0,
  `activo` tinyint(1) NULL DEFAULT 1,
  PRIMARY KEY (`parentesco_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 22 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for clima_aplicaciones
-- ----------------------------
DROP TABLE IF EXISTS `clima_aplicaciones`;
CREATE TABLE `clima_aplicaciones`  (
  `periodo_id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `canal` enum('online','kiosco','papel') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'online',
  `token` char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `fecha_inicio` datetime NULL DEFAULT NULL,
  `fecha_fin` datetime NULL DEFAULT NULL,
  `completada` tinyint(1) NOT NULL DEFAULT 0,
  `capturada_por` int(11) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`periodo_id`, `empleado_id`) USING BTREE,
  UNIQUE INDEX `uq_clima_token_periodo`(`periodo_id`, `token`) USING BTREE,
  INDEX `idx_clima_app_seg`(`periodo_id`, `empresa_id`, `unidad_id`, `completada`) USING BTREE,
  INDEX `fk_clima_app_empleado`(`empleado_id`) USING BTREE,
  INDEX `fk_clima_app_empresa`(`empresa_id`) USING BTREE,
  INDEX `fk_clima_app_unidad`(`unidad_id`) USING BTREE,
  INDEX `fk_clima_app_capturador`(`capturada_por`) USING BTREE,
  CONSTRAINT `fk_clima_app_capturador` FOREIGN KEY (`capturada_por`) REFERENCES `usuarios` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_app_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_app_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_app_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `clima_periodos` (`periodo_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_app_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `org_unidades` (`unidad_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for clima_dimensiones
-- ----------------------------
DROP TABLE IF EXISTS `clima_dimensiones`;
CREATE TABLE `clima_dimensiones`  (
  `dimension_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`dimension_id`) USING BTREE,
  UNIQUE INDEX `uq_clima_dimension_nombre`(`nombre`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for clima_elegibles
-- ----------------------------
DROP TABLE IF EXISTS `clima_elegibles`;
CREATE TABLE `clima_elegibles`  (
  `periodo_id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `elegible` tinyint(1) NOT NULL DEFAULT 0,
  `motivo_no_elegible` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`periodo_id`, `empleado_id`) USING BTREE,
  INDEX `idx_clima_elegibles_seg`(`periodo_id`, `empresa_id`, `unidad_id`, `elegible`) USING BTREE,
  INDEX `fk_clima_elegibles_empleado`(`empleado_id`) USING BTREE,
  INDEX `fk_clima_elegibles_empresa`(`empresa_id`) USING BTREE,
  INDEX `fk_clima_elegibles_unidad`(`unidad_id`) USING BTREE,
  CONSTRAINT `fk_clima_elegibles_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_elegibles_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_elegibles_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `clima_periodos` (`periodo_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_elegibles_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `org_unidades` (`unidad_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for clima_envios
-- ----------------------------
DROP TABLE IF EXISTS `clima_envios`;
CREATE TABLE `clima_envios`  (
  `periodo_id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `completado` tinyint(1) NOT NULL DEFAULT 0,
  `completado_at` datetime NULL DEFAULT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`periodo_id`, `empleado_id`) USING BTREE,
  INDEX `idx_ce_seg`(`periodo_id`, `empresa_id`, `unidad_id`, `completado`) USING BTREE,
  INDEX `fk_ce_empleado`(`empleado_id`) USING BTREE,
  CONSTRAINT `fk_ce_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ce_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `clima_periodos` (`periodo_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for clima_excepciones
-- ----------------------------
DROP TABLE IF EXISTS `clima_excepciones`;
CREATE TABLE `clima_excepciones`  (
  `periodo_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `unidad_id_override` int(11) NULL DEFAULT NULL,
  `reset` tinyint(1) NOT NULL DEFAULT 1,
  `motivo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'EXCEPCION_MANUAL',
  `created_by` int(11) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`periodo_id`, `empresa_id`, `empleado_id`) USING BTREE,
  INDEX `idx_empresa_periodo`(`empresa_id`, `periodo_id`) USING BTREE,
  INDEX `idx_empleado`(`empleado_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for clima_periodos
-- ----------------------------
DROP TABLE IF EXISTS `clima_periodos`;
CREATE TABLE `clima_periodos`  (
  `periodo_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `anio` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `fecha_corte_elegibilidad` date NOT NULL,
  `estatus` enum('borrador','publicado','cerrado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'borrador',
  `creado_por` int(11) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`periodo_id`) USING BTREE,
  UNIQUE INDEX `uq_clima_empresa_anio`(`empresa_id`, `anio`) USING BTREE,
  INDEX `idx_clima_estatus`(`estatus`) USING BTREE,
  INDEX `idx_clima_empresa`(`empresa_id`) USING BTREE,
  INDEX `fk_clima_periodos_creado_por`(`creado_por`) USING BTREE,
  CONSTRAINT `fk_clima_periodo_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_periodos_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_periodos_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for clima_planes
-- ----------------------------
DROP TABLE IF EXISTS `clima_planes`;
CREATE TABLE `clima_planes`  (
  `plan_id` int(11) NOT NULL AUTO_INCREMENT,
  `periodo_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `dimension_id` int(11) NOT NULL,
  `problema_identificado` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `accion` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `responsable` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_compromiso` date NOT NULL,
  `indicador` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `estatus` enum('pendiente','en_proceso','cumplido') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pendiente',
  `created_by` int(11) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`plan_id`) USING BTREE,
  INDEX `idx_clima_plan_seg`(`periodo_id`, `empresa_id`, `unidad_id`) USING BTREE,
  INDEX `fk_clima_plan_empresa`(`empresa_id`) USING BTREE,
  INDEX `fk_clima_plan_unidad`(`unidad_id`) USING BTREE,
  INDEX `fk_clima_plan_dimension`(`dimension_id`) USING BTREE,
  INDEX `fk_clima_plan_user`(`created_by`) USING BTREE,
  CONSTRAINT `fk_clima_plan_dimension` FOREIGN KEY (`dimension_id`) REFERENCES `clima_dimensiones` (`dimension_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_plan_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_plan_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `clima_periodos` (`periodo_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_plan_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `org_unidades` (`unidad_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_plan_user` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for clima_preguntas_abiertas
-- ----------------------------
DROP TABLE IF EXISTS `clima_preguntas_abiertas`;
CREATE TABLE `clima_preguntas_abiertas`  (
  `pregunta_id` int(11) NOT NULL AUTO_INCREMENT,
  `dimension_id` int(11) NULL DEFAULT NULL,
  `texto` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 1,
  `obligatorio` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`pregunta_id`) USING BTREE,
  INDEX `idx_cpa_dim_orden`(`dimension_id`, `orden`) USING BTREE,
  CONSTRAINT `fk_cpa_dimension` FOREIGN KEY (`dimension_id`) REFERENCES `clima_dimensiones` (`dimension_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for clima_publicacion
-- ----------------------------
DROP TABLE IF EXISTS `clima_publicacion`;
CREATE TABLE `clima_publicacion`  (
  `periodo_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `habilitado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_publicacion` datetime NULL DEFAULT NULL,
  `publicado_por` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`periodo_id`, `empresa_id`, `unidad_id`) USING BTREE,
  INDEX `fk_clima_pub_empresa`(`empresa_id`) USING BTREE,
  INDEX `fk_clima_pub_unidad`(`unidad_id`) USING BTREE,
  INDEX `fk_clima_pub_user`(`publicado_por`) USING BTREE,
  CONSTRAINT `fk_clima_pub_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_pub_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `clima_periodos` (`periodo_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_pub_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `org_unidades` (`unidad_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_pub_user` FOREIGN KEY (`publicado_por`) REFERENCES `usuarios` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for clima_reactivos
-- ----------------------------
DROP TABLE IF EXISTS `clima_reactivos`;
CREATE TABLE `clima_reactivos`  (
  `reactivo_id` int(11) NOT NULL AUTO_INCREMENT,
  `dimension_id` int(11) NOT NULL,
  `texto` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `orden` int(11) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`reactivo_id`) USING BTREE,
  INDEX `idx_clima_reactivos_dim`(`dimension_id`, `orden`) USING BTREE,
  CONSTRAINT `fk_clima_reactivos_dim` FOREIGN KEY (`dimension_id`) REFERENCES `clima_dimensiones` (`dimension_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 49 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for clima_respuestas
-- ----------------------------
DROP TABLE IF EXISTS `clima_respuestas`;
CREATE TABLE `clima_respuestas`  (
  `periodo_id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `reactivo_id` int(11) NOT NULL,
  `valor` tinyint(4) NOT NULL,
  `fecha_respuesta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`periodo_id`, `empleado_id`, `reactivo_id`) USING BTREE,
  INDEX `idx_clima_resp_reactivo`(`periodo_id`, `reactivo_id`) USING BTREE,
  INDEX `idx_clima_resp_empleado`(`periodo_id`, `empleado_id`) USING BTREE,
  INDEX `fk_clima_resp_empleado`(`empleado_id`) USING BTREE,
  INDEX `idx_clima_resp_reactivo_empleado`(`reactivo_id`, `empleado_id`) USING BTREE,
  CONSTRAINT `fk_clima_resp_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_resp_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `clima_periodos` (`periodo_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_clima_resp_reactivo` FOREIGN KEY (`reactivo_id`) REFERENCES `clima_reactivos` (`reactivo_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for clima_respuestas_abiertas
-- ----------------------------
DROP TABLE IF EXISTS `clima_respuestas_abiertas`;
CREATE TABLE `clima_respuestas_abiertas`  (
  `periodo_id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `pregunta_id` int(11) NOT NULL,
  `respuesta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_respuesta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`periodo_id`, `empleado_id`, `pregunta_id`) USING BTREE,
  INDEX `idx_cra_seg`(`periodo_id`, `empresa_id`, `unidad_id`) USING BTREE,
  INDEX `idx_cra_pregunta`(`periodo_id`, `pregunta_id`) USING BTREE,
  INDEX `fk_cra_empleado`(`empleado_id`) USING BTREE,
  INDEX `fk_cra_empresa`(`empresa_id`) USING BTREE,
  INDEX `fk_cra_unidad`(`unidad_id`) USING BTREE,
  INDEX `fk_cra_pregunta`(`pregunta_id`) USING BTREE,
  CONSTRAINT `fk_cra_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cra_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_cra_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `clima_periodos` (`periodo_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cra_pregunta` FOREIGN KEY (`pregunta_id`) REFERENCES `clima_preguntas_abiertas` (`pregunta_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cra_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `org_unidades` (`unidad_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for clima_resultados_visibilidad
-- ----------------------------
DROP TABLE IF EXISTS `clima_resultados_visibilidad`;
CREATE TABLE `clima_resultados_visibilidad`  (
  `vis_id` int(11) NOT NULL AUTO_INCREMENT,
  `periodo_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `habilitado` tinyint(1) NOT NULL DEFAULT 0,
  `habilitado_por` int(11) NULL DEFAULT NULL,
  `habilitado_at` datetime NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`vis_id`) USING BTREE,
  UNIQUE INDEX `uq_clima_vis`(`periodo_id`, `empresa_id`, `unidad_id`) USING BTREE,
  INDEX `idx_clima_vis_periodo`(`periodo_id`) USING BTREE,
  INDEX `idx_clima_vis_empresa`(`empresa_id`) USING BTREE,
  INDEX `idx_clima_vis_unidad`(`unidad_id`) USING BTREE,
  CONSTRAINT `fk_clima_vis_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `clima_periodos` (`periodo_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for contratos
-- ----------------------------
DROP TABLE IF EXISTS `contratos`;
CREATE TABLE `contratos`  (
  `contrato_id` int(11) NOT NULL AUTO_INCREMENT,
  `empleado_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `tipo_contrato` enum('temporal','permanente') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'temporal',
  `numero_contrato` int(11) NOT NULL DEFAULT 1 COMMENT 'Para temporales: 1, 2 o 3',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NULL DEFAULT NULL COMMENT 'NULL para permanentes',
  `dias_naturales` int(11) NULL DEFAULT NULL COMMENT 'Días del contrato temporal',
  `estatus` enum('borrador','activo','por_vencer','evaluacion_pendiente','aprobado','rechazado','finalizado','convertido_permanente') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'borrador',
  `puesto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `departamento` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `jefe_inmediato_id` int(11) NULL DEFAULT NULL COMMENT 'empleado_id del jefe',
  `salario_mensual` decimal(10, 2) NULL DEFAULT NULL,
  `salario_diario` decimal(10, 2) NULL DEFAULT NULL,
  `notificacion_enviada` tinyint(1) NULL DEFAULT 0,
  `fecha_notificacion` datetime NULL DEFAULT NULL,
  `requiere_evaluacion` tinyint(1) NULL DEFAULT 0 COMMENT '1 si necesita eval para renovar/permanente',
  `evaluacion_completada` tinyint(1) NULL DEFAULT 0,
  `fecha_evaluacion` datetime NULL DEFAULT NULL,
  `aprobado_por` int(11) NULL DEFAULT NULL COMMENT 'usuario_id del jefe que aprobó',
  `fecha_aprobacion` datetime NULL DEFAULT NULL,
  `documentos_generados` tinyint(1) NULL DEFAULT 0,
  `fecha_generacion_docs` datetime NULL DEFAULT NULL,
  `creado_por` int(11) NULL DEFAULT NULL COMMENT 'usuario_id',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`contrato_id`) USING BTREE,
  INDEX `idx_empleado`(`empleado_id`) USING BTREE,
  INDEX `idx_empresa`(`empresa_id`) USING BTREE,
  INDEX `idx_estatus`(`estatus`) USING BTREE,
  INDEX `idx_fecha_fin`(`fecha_fin`) USING BTREE,
  INDEX `idx_jefe`(`jefe_inmediato_id`) USING BTREE,
  CONSTRAINT `contratos_ibfk_1` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `contratos_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for contratos_documentos
-- ----------------------------
DROP TABLE IF EXISTS `contratos_documentos`;
CREATE TABLE `contratos_documentos`  (
  `documento_id` int(11) NOT NULL AUTO_INCREMENT,
  `contrato_id` int(11) NULL DEFAULT NULL,
  `empleado_id` int(11) NULL DEFAULT NULL,
  `empresa_id` int(11) NOT NULL,
  `tipo_documento` enum('contrato_temporal','contrato_permanente','poliza_fh','carta_patronal','otro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_archivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta_archivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Ruta relativa o URL',
  `extension` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'pdf',
  `tamanio` int(11) NULL DEFAULT NULL COMMENT 'Bytes',
  `fecha_generacion` datetime NOT NULL,
  `generado_por` int(11) NULL DEFAULT NULL COMMENT 'usuario_id',
  `impreso` tinyint(1) NULL DEFAULT 0,
  `fecha_impresion` datetime NULL DEFAULT NULL,
  `entregado` tinyint(1) NULL DEFAULT 0,
  `fecha_entrega` datetime NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`documento_id`) USING BTREE,
  INDEX `idx_contrato`(`contrato_id`) USING BTREE,
  INDEX `idx_empleado`(`empleado_id`) USING BTREE,
  INDEX `idx_empresa`(`empresa_id`) USING BTREE,
  INDEX `idx_tipo`(`tipo_documento`) USING BTREE,
  CONSTRAINT `contratos_documentos_ibfk_1` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`contrato_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `contratos_documentos_ibfk_2` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `contratos_documentos_ibfk_3` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 25 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for contratos_evaluaciones
-- ----------------------------
DROP TABLE IF EXISTS `contratos_evaluaciones`;
CREATE TABLE `contratos_evaluaciones`  (
  `evaluacion_id` int(11) NOT NULL AUTO_INCREMENT,
  `contrato_id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `evaluador_id` int(11) NOT NULL COMMENT 'usuario_id del jefe inmediato',
  `puntualidad` int(11) NULL DEFAULT NULL COMMENT '1-5',
  `responsabilidad` int(11) NULL DEFAULT NULL COMMENT '1-5',
  `calidad_trabajo` int(11) NULL DEFAULT NULL COMMENT '1-5',
  `trabajo_equipo` int(11) NULL DEFAULT NULL COMMENT '1-5',
  `iniciativa` int(11) NULL DEFAULT NULL COMMENT '1-5',
  `calificacion_general` decimal(3, 2) NULL DEFAULT NULL COMMENT 'Promedio de criterios',
  `fortalezas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `areas_mejora` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `comentarios_adicionales` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `recomendacion` enum('aprobar','rechazar','extender_temporal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `justificacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`evaluacion_id`) USING BTREE,
  INDEX `idx_contrato`(`contrato_id`) USING BTREE,
  INDEX `idx_empleado`(`empleado_id`) USING BTREE,
  INDEX `idx_evaluador`(`evaluador_id`) USING BTREE,
  INDEX `empresa_id`(`empresa_id`) USING BTREE,
  CONSTRAINT `contratos_evaluaciones_ibfk_1` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`contrato_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `contratos_evaluaciones_ibfk_2` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `contratos_evaluaciones_ibfk_3` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for contratos_notificaciones
-- ----------------------------
DROP TABLE IF EXISTS `contratos_notificaciones`;
CREATE TABLE `contratos_notificaciones`  (
  `notificacion_id` int(11) NOT NULL AUTO_INCREMENT,
  `contrato_id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `destinatario_id` int(11) NOT NULL COMMENT 'usuario_id del destinatario',
  `tipo` enum('vencimiento_proximo','evaluacion_requerida','aprobacion_pendiente','contrato_aprobado','contrato_rechazado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `asunto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `enviada` tinyint(1) NULL DEFAULT 0,
  `fecha_envio` datetime NULL DEFAULT NULL,
  `via` enum('sistema','email','ambos') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'ambos',
  `email_enviado` tinyint(1) NULL DEFAULT 0,
  `email_destinatario` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `fecha_email` datetime NULL DEFAULT NULL,
  `leida` tinyint(1) NULL DEFAULT 0,
  `fecha_lectura` datetime NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notificacion_id`) USING BTREE,
  INDEX `idx_contrato`(`contrato_id`) USING BTREE,
  INDEX `idx_destinatario`(`destinatario_id`) USING BTREE,
  INDEX `idx_tipo`(`tipo`) USING BTREE,
  INDEX `idx_enviada`(`enviada`) USING BTREE,
  INDEX `idx_leida`(`leida`) USING BTREE,
  INDEX `empleado_id`(`empleado_id`) USING BTREE,
  INDEX `empresa_id`(`empresa_id`) USING BTREE,
  CONSTRAINT `contratos_notificaciones_ibfk_1` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`contrato_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `contratos_notificaciones_ibfk_2` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `contratos_notificaciones_ibfk_3` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for empleados
-- ----------------------------
DROP TABLE IF EXISTS `empleados`;
CREATE TABLE `empleados`  (
  `empleado_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `no_emp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `rfc_base` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `curp` char(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `nombre` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `apellido_paterno` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `apellido_materno` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `es_activo` tinyint(1) NOT NULL DEFAULT 1,
  `foto_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `unidad_id` int(11) NULL DEFAULT NULL,
  `adscripcion_id` int(11) NULL DEFAULT NULL,
  `puesto_id` int(11) NULL DEFAULT NULL,
  `jefe_no_emp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `fecha_ingreso` date NULL DEFAULT NULL,
  `fecha_baja` date NULL DEFAULT NULL,
  `tipo_empleado_id` tinyint(1) NOT NULL DEFAULT 1,
  `tipo_empleado_nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '1',
  `departamento_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `departamento_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `puesto_nomina_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `puesto_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `centro_trabajo_id` int(11) NULL DEFAULT NULL,
  `centro_trabajo_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `jefe_inmediato` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `salario_diario` decimal(12, 2) NULL DEFAULT NULL,
  `salario_mensual` decimal(12, 2) NULL DEFAULT NULL,
  `estatus` enum('activo','baja','suspendido') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'activo',
  `empresa_nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`empleado_id`) USING BTREE,
  UNIQUE INDEX `uq_emp_empresa_noemp_rfc`(`empresa_id`, `no_emp`, `rfc_base`) USING BTREE,
  INDEX `idx_emp_rfc`(`rfc_base`) USING BTREE,
  INDEX `idx_emp_estatus`(`empresa_id`, `estatus`) USING BTREE,
  INDEX `idx_emp_ct`(`empresa_id`, `centro_trabajo_id`) USING BTREE,
  INDEX `fk_emp_unidad`(`unidad_id`) USING BTREE,
  INDEX `fk_emp_ads`(`adscripcion_id`) USING BTREE,
  INDEX `fk_emp_puesto`(`puesto_id`) USING BTREE,
  CONSTRAINT `fk_emp_ads` FOREIGN KEY (`adscripcion_id`) REFERENCES `org_adscripciones` (`adscripcion_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_emp_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_emp_puesto` FOREIGN KEY (`puesto_id`) REFERENCES `org_puestos` (`puesto_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_emp_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `org_unidades` (`unidad_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 7659 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for empleados_demograficos
-- ----------------------------
DROP TABLE IF EXISTS `empleados_demograficos`;
CREATE TABLE `empleados_demograficos`  (
  `empleado_id` int(11) NOT NULL,
  `numero_empleado` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `apellido_paterno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `apellido_materno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `sexo` enum('M','F','X') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `fecha_nacimiento` date NULL DEFAULT NULL,
  `edad` int(11) NULL DEFAULT NULL,
  `nacionalidad` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `lugar_nacimiento` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `escolaridad` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `estado_civil` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `rfc` varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `curp` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `nss` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `domicilio_calle` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `domicilio_num_ext` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `domicilio_num_int` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `domicilio_cp` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `domicilio_colonia` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `domicilio_municipio` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `domicilio_estado` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `empresa_id_pago` int(11) NULL DEFAULT NULL COMMENT 'Empresa activa',
  `apoderado_legal_id` int(11) NULL DEFAULT NULL COMMENT 'org_apoderados',
  `puesto_id` int(11) NULL DEFAULT NULL COMMENT 'catálogo de puestos',
  `departamento_id` int(11) NULL DEFAULT NULL COMMENT 'catálogo de departamentos',
  `fecha_alta` date NULL DEFAULT NULL,
  `fecha_contrato_indeterminado` date NULL DEFAULT NULL,
  `tipo_nomina` enum('SEMANAL','QUINCENAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `sueldo_diario` decimal(10, 2) NULL DEFAULT NULL,
  `sueldo_mensual` decimal(10, 2) NULL DEFAULT NULL,
  `sueldo_integrado` decimal(10, 2) NULL DEFAULT NULL,
  `banco_id` int(11) NULL DEFAULT NULL COMMENT 'cat_bancos',
  `banco` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `numero_cuenta` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `clabe` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `ubicacion_id` int(11) NULL DEFAULT NULL COMMENT 'org_centros_trabajo',
  `jefe_inmediato_id` int(11) NULL DEFAULT NULL COMMENT 'empleado_id del jefe',
  `sustitute_a` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `correo_empresa` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `num_hijos` tinyint(4) NULL DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `telefono_contacto_emergencias` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `correo_personal` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `escolaridad_id` int(11) NULL DEFAULT NULL COMMENT 'cat_escolaridades',
  `telefono_personal` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `telefono_empresa` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `unidad_medica_familiar` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `credito_infonavit` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `tiene_credito_infonavit` tinyint(1) NULL DEFAULT 0,
  `tiene_beneficiarios` tinyint(1) NULL DEFAULT 0,
  `tipo_contrato` enum('TEMPORAL','PERMANENTE') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'TEMPORAL',
  `numero_contrato_actual` int(11) NULL DEFAULT 1 COMMENT '1,2,3 determinado; 0 indeterminado',
  `fecha_inicio_contrato` date NULL DEFAULT NULL,
  `fecha_termino_contrato` date NULL DEFAULT NULL COMMENT 'Fecha de t??rmino del contrato',
  `fecha_fin_contrato` date NULL DEFAULT NULL,
  `dias_contrato` int(11) NULL DEFAULT 90,
  `correo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `datos_completos` tinyint(1) NULL DEFAULT 0 COMMENT '1 si RFC, CURP, NSS, sueldo y banco estan capturados',
  PRIMARY KEY (`empleado_id`) USING BTREE,
  CONSTRAINT `fk_emp_demo_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for empleados_familiares
-- ----------------------------
DROP TABLE IF EXISTS `empleados_familiares`;
CREATE TABLE `empleados_familiares`  (
  `familiar_id` int(11) NOT NULL AUTO_INCREMENT,
  `empleado_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `tipo` enum('conyuge','hijo','padre','madre','hermano','otro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'tabla parentescos_tipos',
  `nombre_completo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parentesco_id` int(11) NULL DEFAULT NULL COMMENT 'tabla parentescos',
  `parentesco_otro` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `fecha_nacimiento` date NULL DEFAULT NULL,
  `edad` int(11) NULL DEFAULT NULL,
  `sexo` enum('M','F','Otro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `curp` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `rfc` varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `es_beneficiario` tinyint(1) NULL DEFAULT 0,
  `porcentaje_beneficio` decimal(5, 2) NULL DEFAULT NULL COMMENT 'Porcentaje si es beneficiario',
  `tipo_beneficiario_id` int(11) NULL DEFAULT NULL COMMENT 'tabla parentescos_tipos',
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `direccion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `es_dependiente_economico` tinyint(1) NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`familiar_id`) USING BTREE,
  INDEX `idx_empleado`(`empleado_id`) USING BTREE,
  INDEX `idx_empresa`(`empresa_id`) USING BTREE,
  INDEX `idx_es_beneficiario`(`es_beneficiario`) USING BTREE,
  CONSTRAINT `empleados_familiares_ibfk_1` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `empleados_familiares_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for empleados_nuevo_ingreso
-- ----------------------------
DROP TABLE IF EXISTS `empleados_nuevo_ingreso`;
CREATE TABLE `empleados_nuevo_ingreso`  (
  `nuevo_ingreso_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(150) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `apellido_paterno` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `apellido_materno` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `sexo` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'M/F',
  `fecha_nacimiento` date NULL DEFAULT NULL,
  `nacionalidad` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `lugar_nacimiento` varchar(150) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `rfc` varchar(13) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `curp` varchar(18) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `nss` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `domicilio_calle` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `domicilio_num_ext` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `domicilio_num_int` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `domicilio_cp` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `domicilio_estado` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `domicilio_municipio` varchar(150) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `domicilio_colonia` varchar(150) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `apoderado_legal_id` int(11) NULL DEFAULT NULL,
  `fecha_alta` date NULL DEFAULT NULL,
  `fecha_inicio_contrato` date NULL DEFAULT NULL COMMENT 'Fecha de inicio del contrato',
  `fecha_termino_contrato` date NULL DEFAULT NULL COMMENT 'Fecha de t??rmino del contrato',
  `tipo_nomina` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'Semanal/Quincenal/Mensual',
  `sueldo_diario` decimal(10, 2) NULL DEFAULT NULL,
  `sueldo_mensual` decimal(10, 2) NULL DEFAULT NULL,
  `sueldo_integrado` decimal(10, 2) NULL DEFAULT NULL,
  `banco_id` int(11) NULL DEFAULT NULL,
  `numero_cuenta` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `clabe` varchar(18) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `correo_empresa` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `correo_personal` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `escolaridad_id` int(11) NULL DEFAULT NULL,
  `telefono_personal` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `telefono_empresa` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `unidad_medica_familiar` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `tiene_credito_infonavit` char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'S/N',
  `datos_completos` tinyint(1) NULL DEFAULT 0 COMMENT '1 si tiene RFC, CURP, NSS, sueldo_mensual, banco_id',
  `estatus` enum('nuevo','en_proceso','completado','rechazado') CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT 'nuevo',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `creado_por` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`nuevo_ingreso_id`) USING BTREE,
  INDEX `empresa_id`(`empresa_id`) USING BTREE,
  INDEX `apoderado_legal_id`(`apoderado_legal_id`) USING BTREE,
  INDEX `banco_id`(`banco_id`) USING BTREE,
  INDEX `escolaridad_id`(`escolaridad_id`) USING BTREE,
  INDEX `creado_por`(`creado_por`) USING BTREE,
  CONSTRAINT `empleados_nuevo_ingreso_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `empleados_nuevo_ingreso_ibfk_2` FOREIGN KEY (`apoderado_legal_id`) REFERENCES `org_apoderados` (`apoderado_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `empleados_nuevo_ingreso_ibfk_3` FOREIGN KEY (`banco_id`) REFERENCES `cat_bancos` (`banco_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `empleados_nuevo_ingreso_ibfk_4` FOREIGN KEY (`escolaridad_id`) REFERENCES `cat_escolaridades` (`escolaridad_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `empleados_nuevo_ingreso_ibfk_5` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`usuario_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for empresas
-- ----------------------------
DROP TABLE IF EXISTS `empresas`;
CREATE TABLE `empresas`  (
  `empresa_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `alias` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `estatus` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`empresa_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for empresas_logos
-- ----------------------------
DROP TABLE IF EXISTS `empresas_logos`;
CREATE TABLE `empresas_logos`  (
  `logo_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `logo_ruta` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Ruta al archivo del logo',
  `logo_width` int(11) NULL DEFAULT 150 COMMENT 'Ancho en px para documentos',
  `logo_height` int(11) NULL DEFAULT 80 COMMENT 'Alto en px para documentos',
  `color_primario` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '#000000' COMMENT 'Color hex para headers',
  `color_secundario` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '#666666',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`logo_id`) USING BTREE,
  UNIQUE INDEX `empresa_id`(`empresa_id`) USING BTREE,
  CONSTRAINT `empresas_logos_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for nomina_import_detalle
-- ----------------------------
DROP TABLE IF EXISTS `nomina_import_detalle`;
CREATE TABLE `nomina_import_detalle`  (
  `import_detalle_id` int(11) NOT NULL AUTO_INCREMENT,
  `import_id` int(11) NOT NULL,
  `no_emp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `rfc_base` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `payload_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `accion` enum('insert','update','skip','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'skip',
  `mensaje` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`import_detalle_id`) USING BTREE,
  INDEX `idx_impd_import`(`import_id`, `accion`) USING BTREE,
  CONSTRAINT `fk_impd_import` FOREIGN KEY (`import_id`) REFERENCES `nomina_importaciones` (`import_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 10691 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for nomina_importaciones
-- ----------------------------
DROP TABLE IF EXISTS `nomina_importaciones`;
CREATE TABLE `nomina_importaciones`  (
  `import_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NULL DEFAULT NULL,
  `usuario_id` int(11) NULL DEFAULT NULL,
  `archivo_nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `total_registros` int(11) NOT NULL DEFAULT 0,
  `status` enum('cargado','procesado','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'cargado',
  `mensaje` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`import_id`) USING BTREE,
  INDEX `fk_imp_empresa`(`empresa_id`) USING BTREE,
  INDEX `fk_imp_usuario`(`usuario_id`) USING BTREE,
  CONSTRAINT `fk_imp_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_imp_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 80 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for org_adscripciones
-- ----------------------------
DROP TABLE IF EXISTS `org_adscripciones`;
CREATE TABLE `org_adscripciones`  (
  `adscripcion_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `clave` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `estatus` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`adscripcion_id`) USING BTREE,
  UNIQUE INDEX `uq_ads_empresa_unidad_nombre`(`empresa_id`, `unidad_id`, `nombre`) USING BTREE,
  INDEX `idx_ads_unidad`(`unidad_id`, `estatus`) USING BTREE,
  CONSTRAINT `fk_ads_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ads_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `org_unidades` (`unidad_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 66 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for org_apoderados
-- ----------------------------
DROP TABLE IF EXISTS `org_apoderados`;
CREATE TABLE `org_apoderados`  (
  `apoderado_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `puesto` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `rfc` varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `correo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `orden` int(11) NULL DEFAULT 0,
  `activo` tinyint(1) NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`apoderado_id`) USING BTREE,
  INDEX `idx_empresa`(`empresa_id`) USING BTREE,
  CONSTRAINT `org_apoderados_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for org_centros_trabajo
-- ----------------------------
DROP TABLE IF EXISTS `org_centros_trabajo`;
CREATE TABLE `org_centros_trabajo`  (
  `empresa_id` int(11) NOT NULL,
  `centro_trabajo_id` int(11) NOT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `clave` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `estatus` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`empresa_id`, `centro_trabajo_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for org_puestos
-- ----------------------------
DROP TABLE IF EXISTS `org_puestos`;
CREATE TABLE `org_puestos`  (
  `puesto_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `unidad_id` int(11) NULL DEFAULT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `clave` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `nivel` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `puntos_hay` int(11) NULL DEFAULT NULL,
  `estatus` tinyint(1) NOT NULL DEFAULT 1,
  `sindicalizado` int(11) NULL DEFAULT NULL,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`puesto_id`) USING BTREE,
  INDEX `idx_puesto_empresa`(`empresa_id`, `estatus`) USING BTREE,
  INDEX `idx_puesto_unidad`(`empresa_id`, `unidad_id`, `estatus`) USING BTREE,
  INDEX `fk_puesto_unidad`(`unidad_id`) USING BTREE,
  CONSTRAINT `fk_puesto_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_puesto_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `org_unidades` (`unidad_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 319 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for org_unidades
-- ----------------------------
DROP TABLE IF EXISTS `org_unidades`;
CREATE TABLE `org_unidades`  (
  `unidad_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `clave` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `unidad_padre_id` int(11) NULL DEFAULT NULL,
  `estatus` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`unidad_id`) USING BTREE,
  UNIQUE INDEX `uq_unidad_empresa_nombre`(`empresa_id`, `nombre`) USING BTREE,
  INDEX `idx_unidad_empresa`(`empresa_id`, `estatus`) USING BTREE,
  INDEX `idx_unidad_padre`(`empresa_id`, `unidad_padre_id`) USING BTREE,
  INDEX `fk_unidad_padre`(`unidad_padre_id`) USING BTREE,
  CONSTRAINT `fk_unidad_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_unidad_padre` FOREIGN KEY (`unidad_padre_id`) REFERENCES `org_unidades` (`unidad_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 14 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for password_resets
-- ----------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets`  (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime NULL DEFAULT NULL,
  `requested_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reset_id`) USING BTREE,
  UNIQUE INDEX `uq_token_hash`(`token_hash`) USING BTREE,
  INDEX `idx_pr_user`(`usuario_id`, `expires_at`) USING BTREE,
  CONSTRAINT `fk_pr_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for permisos
-- ----------------------------
DROP TABLE IF EXISTS `permisos`;
CREATE TABLE `permisos`  (
  `permiso_id` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `modulo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`permiso_id`) USING BTREE,
  UNIQUE INDEX `uq_perm_clave`(`clave`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 19 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for rh_documentos
-- ----------------------------
DROP TABLE IF EXISTS `rh_documentos`;
CREATE TABLE `rh_documentos`  (
  `documento_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `titulo` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `archivo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `visible_empleados` tinyint(1) NOT NULL DEFAULT 1,
  `estatus` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`documento_id`) USING BTREE,
  INDEX `idx_doc_empresa`(`empresa_id`, `visible_empleados`, `estatus`) USING BTREE,
  CONSTRAINT `fk_doc_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for rol_permisos
-- ----------------------------
DROP TABLE IF EXISTS `rol_permisos`;
CREATE TABLE `rol_permisos`  (
  `rol_id` int(11) NOT NULL,
  `permiso_id` int(11) NOT NULL,
  PRIMARY KEY (`rol_id`, `permiso_id`) USING BTREE,
  INDEX `fk_rp_permiso`(`permiso_id`) USING BTREE,
  CONSTRAINT `fk_rp_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`permiso_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rp_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`rol_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for roles
-- ----------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles`  (
  `rol_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `estatus` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`rol_id`) USING BTREE,
  UNIQUE INDEX `uq_rol_nombre`(`nombre`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for usuario_empresas
-- ----------------------------
DROP TABLE IF EXISTS `usuario_empresas`;
CREATE TABLE `usuario_empresas`  (
  `usuario_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `empleado_id` int(11) NULL DEFAULT NULL,
  `es_admin` tinyint(1) NOT NULL DEFAULT 0,
  `estatus` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`usuario_id`, `empresa_id`) USING BTREE,
  INDEX `fk_ue_empresa`(`empresa_id`) USING BTREE,
  INDEX `idx_ue_empleado`(`empleado_id`) USING BTREE,
  CONSTRAINT `fk_ue_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ue_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ue_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for usuario_roles
-- ----------------------------
DROP TABLE IF EXISTS `usuario_roles`;
CREATE TABLE `usuario_roles`  (
  `usuario_id` int(11) NOT NULL,
  `rol_id` int(11) NOT NULL,
  PRIMARY KEY (`usuario_id`, `rol_id`) USING BTREE,
  INDEX `fk_ur_rol`(`rol_id`) USING BTREE,
  CONSTRAINT `fk_ur_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`rol_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ur_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for usuarios
-- ----------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios`  (
  `usuario_id` int(11) NOT NULL AUTO_INCREMENT,
  `no_emp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `rfc_base` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `apellido_paterno` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `apellido_materno` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `debe_cambiar_pass` tinyint(1) NOT NULL DEFAULT 1,
  `estatus` enum('activo','inactivo','baja') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'activo',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `correo` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `pass_cambiada` tinyint(4) NOT NULL DEFAULT 0,
  `sidebar_state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'normal',
  PRIMARY KEY (`usuario_id`) USING BTREE,
  UNIQUE INDEX `uq_usuario_noemp_rfc`(`no_emp`, `rfc_base`) USING BTREE,
  INDEX `idx_rfc`(`rfc_base`) USING BTREE,
  INDEX `idx_estatus`(`estatus`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2134 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for vacaciones_saldos
-- ----------------------------
DROP TABLE IF EXISTS `vacaciones_saldos`;
CREATE TABLE `vacaciones_saldos`  (
  `saldo_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `periodo` year NOT NULL,
  `dias_disponibles` decimal(6, 2) NOT NULL DEFAULT 0.00,
  `dias_tomados` decimal(6, 2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`saldo_id`) USING BTREE,
  UNIQUE INDEX `uq_saldo`(`empresa_id`, `empleado_id`, `periodo`) USING BTREE,
  INDEX `fk_saldo_empleado`(`empleado_id`) USING BTREE,
  CONSTRAINT `fk_saldo_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_saldo_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for vacaciones_solicitudes
-- ----------------------------
DROP TABLE IF EXISTS `vacaciones_solicitudes`;
CREATE TABLE `vacaciones_solicitudes`  (
  `solicitud_id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `dias` decimal(6, 2) NOT NULL,
  `estatus` enum('capturada','aprobada','rechazada','cancelada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'capturada',
  `comentario` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `aprobado_por` int(11) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`solicitud_id`) USING BTREE,
  INDEX `fk_sol_empleado`(`empleado_id`) USING BTREE,
  INDEX `fk_sol_aprobador`(`aprobado_por`) USING BTREE,
  INDEX `idx_sol_estatus`(`empresa_id`, `estatus`, `created_at`) USING BTREE,
  CONSTRAINT `fk_sol_aprobador` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sol_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sol_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`empresa_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- View structure for v_contratos_por_vencer
-- ----------------------------
DROP VIEW IF EXISTS `v_contratos_por_vencer`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_contratos_por_vencer` AS select `c`.`contrato_id` AS `contrato_id`,`c`.`empleado_id` AS `empleado_id`,`c`.`empresa_id` AS `empresa_id`,`e`.`nombre` AS `nombre`,`e`.`apellido_paterno` AS `apellido_paterno`,`e`.`apellido_materno` AS `apellido_materno`,`c`.`tipo_contrato` AS `tipo_contrato`,`c`.`numero_contrato` AS `numero_contrato`,`c`.`fecha_inicio` AS `fecha_inicio`,`c`.`fecha_fin` AS `fecha_fin`,`c`.`dias_naturales` AS `dias_naturales`,(to_days(`c`.`fecha_fin`) - to_days(curdate())) AS `dias_restantes`,`c`.`estatus` AS `estatus`,`c`.`jefe_inmediato_id` AS `jefe_inmediato_id`,`jefe`.`nombre` AS `jefe_nombre`,`jefe`.`apellido_paterno` AS `jefe_apellido_paterno`,`c`.`notificacion_enviada` AS `notificacion_enviada`,`c`.`evaluacion_completada` AS `evaluacion_completada`,`emp`.`nombre` AS `empresa_nombre` from (((`contratos` `c` join `empleados` `e` on((`c`.`empleado_id` = `e`.`empleado_id`))) join `empresas` `emp` on((`c`.`empresa_id` = `emp`.`empresa_id`))) left join `empleados` `jefe` on((`c`.`jefe_inmediato_id` = `jefe`.`empleado_id`))) where ((`c`.`tipo_contrato` = 'temporal') and (`c`.`estatus` in ('activo','por_vencer')) and (`c`.`fecha_fin` is not null) and ((to_days(`c`.`fecha_fin`) - to_days(curdate())) <= 7) and ((to_days(`c`.`fecha_fin`) - to_days(curdate())) >= 0));

-- ----------------------------
-- Procedure structure for generar_elegibles_clima
-- ----------------------------
DROP PROCEDURE IF EXISTS `generar_elegibles_clima`;
delimiter ;;
CREATE PROCEDURE `generar_elegibles_clima`(IN p_periodo_id INT,
  IN p_empresa_id INT)
BEGIN
  
  -- Limpiar elegibles existentes (opcional)
  DELETE FROM clima_elegibles WHERE periodo_id = p_periodo_id AND empresa_id = p_empresa_id;
  
  -- Insertar todos los empleados activos como elegibles
  INSERT INTO clima_elegibles (periodo_id, empleado_id, empresa_id, unidad_id, elegible, motivo_no_elegible, created_at)
  SELECT 
    p_periodo_id,
    e.empleado_id,
    e.empresa_id,
    COALESCE(e.unidad_id, 1) as unidad_id,
    1 as elegible,
    NULL as motivo_no_elegible,
    NOW() as created_at
  FROM empleados e
  WHERE e.empresa_id = p_empresa_id 
    AND e.es_activo = 1
    AND e.estatus = 'activo'
    AND e.fecha_baja IS NULL
  ON DUPLICATE KEY UPDATE 
    elegible = VALUES(elegible),
    unidad_id = VALUES(unidad_id);
    
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for generar_respuestas_clima
-- ----------------------------
DROP PROCEDURE IF EXISTS `generar_respuestas_clima`;
delimiter ;;
CREATE PROCEDURE `generar_respuestas_clima`(IN p_periodo_id INT,
  IN p_empresa_id INT,
  IN p_empleado_inicio INT,
  IN p_empleado_fin INT)
BEGIN
  DECLARE v_empleado INT;
  DECLARE v_reactivo INT;
  DECLARE v_valor INT;
  DECLARE v_random DECIMAL(3,2);
  
  -- Limpiar respuestas existentes para este periodo (opcional)
  DELETE FROM clima_respuestas WHERE periodo_id = p_periodo_id;
  
  SET v_empleado = p_empleado_inicio;
  
  -- Iterar sobre empleados
  WHILE v_empleado <= p_empleado_fin DO
    
    -- Verificar que el empleado existe, pertenece a la empresa y está activo
    IF EXISTS (SELECT 1 FROM empleados WHERE empleado_id = v_empleado AND empresa_id = p_empresa_id AND es_activo = 1 AND estatus = 'activo') THEN
      
      SET v_reactivo = 1;
      
      -- Iterar sobre los 48 reactivos (4 por cada una de las 12 dimensiones)
      WHILE v_reactivo <= 48 DO
        
        -- Generar valor aleatorio con distribución realista
        SET v_random = RAND();
        
        -- Distribución: 10% (1-2), 70% (3-4), 20% (5)
        IF v_random < 0.05 THEN
          SET v_valor = 1; -- 5% muy insatisfecho
        ELSEIF v_random < 0.10 THEN
          SET v_valor = 2; -- 5% insatisfecho
        ELSEIF v_random < 0.40 THEN
          SET v_valor = 3; -- 30% neutral
        ELSEIF v_random < 0.80 THEN
          SET v_valor = 4; -- 40% satisfecho
        ELSE
          SET v_valor = 5; -- 20% muy satisfecho
        END IF;
        
        -- Insertar respuesta
        INSERT INTO clima_respuestas (periodo_id, empleado_id, reactivo_id, valor, fecha_respuesta)
        VALUES (p_periodo_id, v_empleado, v_reactivo, v_valor, NOW())
        ON DUPLICATE KEY UPDATE valor = VALUES(valor), fecha_respuesta = VALUES(fecha_respuesta);
        
        SET v_reactivo = v_reactivo + 1;
      END WHILE;
      
    END IF;
    
    SET v_empleado = v_empleado + 1;
  END WHILE;
  
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for publicar_resultados_clima
-- ----------------------------
DROP PROCEDURE IF EXISTS `publicar_resultados_clima`;
delimiter ;;
CREATE PROCEDURE `publicar_resultados_clima`(IN p_periodo_id INT,
  IN p_empresa_id INT,
  IN p_publicado_por INT)
BEGIN
  
  -- Limpiar publicaciones existentes (opcional)
  DELETE FROM clima_publicacion WHERE periodo_id = p_periodo_id AND empresa_id = p_empresa_id;
  
  -- Habilitar resultados para todas las unidades de la empresa
  INSERT INTO clima_publicacion (periodo_id, empresa_id, unidad_id, habilitado, fecha_publicacion, publicado_por)
  SELECT DISTINCT
    p_periodo_id,
    p_empresa_id,
    u.unidad_id,
    1 as habilitado,
    NOW() as fecha_publicacion,
    p_publicado_por
  FROM org_unidades u
  WHERE u.empresa_id = p_empresa_id
  ON DUPLICATE KEY UPDATE 
    habilitado = VALUES(habilitado),
    fecha_publicacion = VALUES(fecha_publicacion),
    publicado_por = VALUES(publicado_por);
    
END
;;
delimiter ;

SET FOREIGN_KEY_CHECKS = 1;
