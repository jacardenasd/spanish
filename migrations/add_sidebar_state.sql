-- Agregar columna sidebar_state a la tabla usuarios si no existe
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS sidebar_state VARCHAR(50) DEFAULT 'normal';
