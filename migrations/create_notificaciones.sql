-- Tabla para sistema de notificaciones
CREATE TABLE IF NOT EXISTS `notificaciones` (
  `notificacion_id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_destino_id` int(11) NOT NULL COMMENT 'Usuario que recibe la notificación',
  `tipo` enum('contrato_vencimiento','evaluacion_pendiente','documento_generado','otro') NOT NULL DEFAULT 'otro',
  `asunto` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `url` varchar(500) NULL DEFAULT NULL COMMENT 'Link a la acción relacionada',
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_lectura` datetime NULL DEFAULT NULL,
  `prioridad` enum('baja','normal','alta') NOT NULL DEFAULT 'normal',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notificacion_id`),
  INDEX `idx_usuario_leida` (`usuario_destino_id`, `leida`),
  INDEX `idx_tipo` (`tipo`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sistema de notificaciones internas';

-- Agregar índices adicionales a tabla contratos si no existen
ALTER TABLE `contratos` ADD INDEX IF NOT EXISTS `idx_tipo_estatus` (`tipo_contrato`, `estatus`);
ALTER TABLE `contratos` ADD INDEX IF NOT EXISTS `idx_fecha_notif` (`fecha_fin`, `notificacion_enviada`);
