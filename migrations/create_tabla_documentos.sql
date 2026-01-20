-- Tabla para gestionar documentos/manuales del sistema
CREATE TABLE IF NOT EXISTS documentos (
  documento_id int(11) NOT NULL AUTO_INCREMENT,
  empresa_id int(11) NOT NULL,
  titulo varchar(255) NOT NULL COMMENT 'Nombre del documento',
  descripcion text COMMENT 'Descripción breve del documento',
  archivo_path varchar(500) COMMENT 'Ruta del archivo en storage',
  seccion varchar(100) NOT NULL COMMENT 'Sección del sistema (clima, desempeño, perfil, admin, etc)',
  orden int(11) DEFAULT 0 COMMENT 'Orden de aparición en el dashboard',
  estatus enum('activo','inactivo') DEFAULT 'activo',
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`documento_id`),
  FOREIGN KEY (empresa_id) REFERENCES empresas(empresa_id) ON DELETE CASCADE,
  INDEX idx_empresa_seccion (empresa_id, seccion),
  INDEX idx_estatus (estatus)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
