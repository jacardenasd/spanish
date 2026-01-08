-- =============================================
-- DATOS DE PRUEBA PARA MÓDULO CLIMA LABORAL
-- =============================================
-- Genera información aleatoria para probar resultados
-- Incluye: dimensiones, reactivos, periodos, elegibles, respuestas y publicación

SET NAMES utf8mb4;

-- =============================================
-- 1. DIMENSIONES (4 dimensiones estándar)
-- =============================================
-- COMENTADO: El usuario ya tiene las dimensiones configuradas
-- INSERT INTO clima_dimensiones (dimension_id, nombre, orden, activo) VALUES
-- (1, 'Relación con el Jefe Inmediato', 1, 1),
-- (2, 'Relación con los Compañeros', 2, 1),
-- (3, 'Relación con la Empresa', 3, 1),
-- (4, 'Relación con el Trabajo', 4, 1)
-- ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), orden=VALUES(orden), activo=VALUES(activo);

-- =============================================
-- 2. REACTIVOS (40 preguntas: 10 por dimensión)
-- =============================================
-- COMENTADO: El usuario ya tiene los reactivos configurados

-- Dimensión 1: Relación con el Jefe Inmediato (10 preguntas)
-- INSERT INTO clima_reactivos (dimension_id, texto, orden, activo) VALUES
-- (1, 'Mi jefe inmediato me comunica claramente lo que espera de mi trabajo', 1, 1),
-- (1, 'Recibo retroalimentación oportuna sobre mi desempeño', 2, 1),
-- (1, 'Mi jefe me trata con respeto y consideración', 3, 1),
-- (1, 'Siento que puedo expresar mis ideas y opiniones a mi jefe', 4, 1),
-- (1, 'Mi jefe reconoce mis logros y esfuerzos', 5, 1),
-- (1, 'Confío en las decisiones que toma mi jefe', 6, 1),
-- (1, 'Mi jefe me apoya cuando enfrento dificultades en el trabajo', 7, 1),
-- (1, 'Las indicaciones que recibo de mi jefe son claras y precisas', 8, 1),
-- (1, 'Mi jefe es accesible cuando necesito hablar con él/ella', 9, 1),
-- (1, 'Considero que mi jefe tiene las competencias para dirigir el área', 10, 1);

-- Dimensión 2: Relación con los Compañeros (10 preguntas)
-- INSERT INTO clima_reactivos (dimension_id, texto, orden, activo) VALUES
-- (2, 'Existe un ambiente de colaboración entre los miembros de mi equipo', 1, 1),
-- (2, 'Mis compañeros me apoyan cuando lo necesito', 2, 1),
-- (2, 'Me siento parte del equipo de trabajo', 3, 1),
-- (2, 'La comunicación con mis compañeros es efectiva', 4, 1),
-- (2, 'Existe respeto mutuo entre los miembros del equipo', 5, 1),
-- (2, 'Puedo confiar en mis compañeros de trabajo', 6, 1),
-- (2, 'Trabajamos juntos para alcanzar objetivos comunes', 7, 1),
-- (2, 'Los conflictos se resuelven de manera constructiva', 8, 1),
-- (2, 'Siento que mis compañeros valoran mi contribución', 9, 1),
-- (2, 'El ambiente entre compañeros es cordial y profesional', 10, 1);

-- Dimensión 3: Relación con la Empresa (10 preguntas)
-- INSERT INTO clima_reactivos (dimension_id, texto, orden, activo) VALUES
-- (3, 'Me siento orgulloso/a de trabajar en esta empresa', 1, 1),
-- (3, 'La empresa se preocupa por el bienestar de sus empleados', 2, 1),
-- (3, 'Conozco claramente la misión y visión de la empresa', 3, 1),
-- (3, 'Las políticas y procedimientos de la empresa son justas', 4, 1),
-- (3, 'La empresa ofrece oportunidades de desarrollo profesional', 5, 1),
-- (3, 'Mi salario es justo en relación con mis responsabilidades', 6, 1),
-- (3, 'Los beneficios que ofrece la empresa son adecuados', 7, 1),
-- (3, 'La empresa reconoce y valora el esfuerzo de sus empleados', 8, 1),
-- (3, 'Considero que la empresa es un buen lugar para trabajar', 9, 1),
-- (3, 'Recomendaría a otros trabajar en esta empresa', 10, 1);

-- Dimensión 4: Relación con el Trabajo (10 preguntas)
-- INSERT INTO clima_reactivos (dimension_id, texto, orden, activo) VALUES
-- (4, 'Mi trabajo me resulta interesante y motivador', 1, 1),
-- (4, 'Tengo los recursos necesarios para realizar mi trabajo', 2, 1),
-- (4, 'Mi carga de trabajo es razonable', 3, 1),
-- (4, 'Tengo claridad sobre mis responsabilidades y funciones', 4, 1),
-- (4, 'Puedo tomar decisiones necesarias para realizar mi trabajo', 5, 1),
-- (4, 'Mi trabajo me permite desarrollar mis habilidades', 6, 1),
-- (4, 'Las condiciones físicas de mi lugar de trabajo son adecuadas', 7, 1),
-- (4, 'Tengo un equilibrio adecuado entre mi vida personal y laboral', 8, 1),
-- (4, 'Mi trabajo aporta valor significativo a la empresa', 9, 1),
-- (4, 'Me siento satisfecho/a con el trabajo que realizo', 10, 1);

-- =============================================
-- 3. PERIODOS (Año 2025 y 2026 para empresa 1)
-- =============================================
-- Nota: Ajusta empresa_id y creado_por según tu base de datos

-- Periodo 2025 - Cerrado
INSERT INTO clima_periodos (periodo_id, empresa_id, anio, fecha_inicio, fecha_fin, fecha_corte_elegibilidad, estatus, creado_por, created_at, updated_at) VALUES
(1, 1, 2025, '2025-01-01', '2025-12-31', '2024-10-01', 'cerrado', 1, '2025-01-15 10:00:00', '2025-12-31 18:00:00')
ON DUPLICATE KEY UPDATE 
  anio=VALUES(anio), 
  fecha_inicio=VALUES(fecha_inicio), 
  fecha_fin=VALUES(fecha_fin), 
  fecha_corte_elegibilidad=VALUES(fecha_corte_elegibilidad),
  estatus=VALUES(estatus);

-- Periodo 2026 - Publicado
INSERT INTO clima_periodos (periodo_id, empresa_id, anio, fecha_inicio, fecha_fin, fecha_corte_elegibilidad, estatus, creado_por, created_at, updated_at) VALUES
(2, 1, 2026, '2026-01-01', '2026-12-31', '2025-10-01', 'publicado', 1, '2026-01-01 08:00:00', '2026-01-08 10:00:00')
ON DUPLICATE KEY UPDATE 
  anio=VALUES(anio), 
  fecha_inicio=VALUES(fecha_inicio), 
  fecha_fin=VALUES(fecha_fin), 
  fecha_corte_elegibilidad=VALUES(fecha_corte_elegibilidad),
  estatus=VALUES(estatus);

-- =============================================
-- 4. RESPUESTAS ALEATORIAS
-- =============================================
-- Genera respuestas para periodo 2 (2026)
-- Necesitas tener empleados en tu base de datos
-- Este script genera respuestas para empleados según rango especificado
-- Los reactivos van del 1 al 48 (4 por cada una de las 12 dimensiones)
-- Valores: 1 (Totalmente en desacuerdo) a 5 (Totalmente de acuerdo)

-- IMPORTANTE: Ajusta el rango de empleado_id según tu base de datos
-- Para empresa 1: empleados desde 5526 en adelante
-- Para empresa 2: empleados desde 6093 en adelante
-- Verifica con: SELECT MIN(empleado_id), MAX(empleado_id), COUNT(*) FROM empleados WHERE empresa_id=1 AND es_activo=1 AND estatus='activo';

-- Generar respuestas con distribución realista:
-- - 10% respuestas bajas (1-2): Áreas con problemas
-- - 70% respuestas medias (3-4): Mayoría satisfecho
-- - 20% respuestas altas (5): Muy satisfechos

DELIMITER $$

DROP PROCEDURE IF EXISTS generar_respuestas_clima$$

CREATE PROCEDURE generar_respuestas_clima(
  IN p_periodo_id INT,
  IN p_empresa_id INT,
  IN p_empleado_inicio INT,
  IN p_empleado_fin INT
)
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
  
END$$

DELIMITER ;

-- =============================================
-- 5. GENERAR ELEGIBLES
-- =============================================
-- Marca a todos los empleados activos como elegibles para el periodo 2026

DELIMITER $$

DROP PROCEDURE IF EXISTS generar_elegibles_clima$$

CREATE PROCEDURE generar_elegibles_clima(
  IN p_periodo_id INT,
  IN p_empresa_id INT
)
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
    
END$$

DELIMITER ;

-- =============================================
-- 6. PUBLICACIÓN DE RESULTADOS
-- =============================================
-- Habilita la visualización de resultados para todas las unidades

DELIMITER $$

DROP PROCEDURE IF EXISTS publicar_resultados_clima$$

CREATE PROCEDURE publicar_resultados_clima(
  IN p_periodo_id INT,
  IN p_empresa_id INT,
  IN p_publicado_por INT
)
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
    
END$$

DELIMITER ;

-- =============================================
-- 7. EJECUTAR PROCEDIMIENTOS
-- =============================================
-- Ajusta los parámetros según tu base de datos:
-- - periodo_id: 2 (año 2026)
-- - empresa_id: 1 (o tu empresa)
-- - empleado_inicio y empleado_fin: rango de IDs de empleados
-- - publicado_por: ID del usuario admin

-- Ejemplo para EMPRESA 1 (empleados desde 5526):
-- Primero verifica el rango: SELECT MIN(empleado_id), MAX(empleado_id) FROM empleados WHERE empresa_id=1 AND es_activo=1 AND estatus='activo';

-- Generar elegibles
CALL generar_elegibles_clima(2, 1);

-- Generar respuestas para empresa 1 (ajusta el rango según tu base de datos)
CALL generar_respuestas_clima(2, 1, 5526, 6300);

-- Publicar resultados (usuario 1 publica)
CALL publicar_resultados_clima(2, 1, 1);

-- =============================================
-- 8. LIMPIAR PROCEDIMIENTOS (OPCIONAL)
-- =============================================
-- Si quieres eliminar los procedimientos después de usarlos:
-- DROP PROCEDURE IF EXISTS generar_respuestas_clima;
-- DROP PROCEDURE IF EXISTS generar_elegibles_clima;
-- DROP PROCEDURE IF EXISTS publicar_resultados_clima;

-- =============================================
-- 9. VERIFICACIÓN
-- =============================================
-- Consultas para verificar los datos generados:

-- Ver total de respuestas por periodo
-- SELECT periodo_id, COUNT(*) as total_respuestas FROM clima_respuestas GROUP BY periodo_id;

-- Ver promedio general por dimensión
-- SELECT 
--   d.nombre as dimension,
--   ROUND(AVG(r.valor), 2) as promedio
-- FROM clima_respuestas r
-- INNER JOIN clima_reactivos rc ON r.reactivo_id = rc.reactivo_id
-- INNER JOIN clima_dimensiones d ON rc.dimension_id = d.dimension_id
-- WHERE r.periodo_id = 2
-- GROUP BY d.dimension_id, d.nombre
-- ORDER BY d.orden;

-- Ver participación por unidad
-- SELECT 
--   u.nombre_unidad,
--   COUNT(DISTINCT ce.empleado_id) as elegibles,
--   COUNT(DISTINCT cr.empleado_id) as respondieron,
--   ROUND(COUNT(DISTINCT cr.empleado_id) * 100.0 / COUNT(DISTINCT ce.empleado_id), 2) as porcentaje_participacion
-- FROM clima_elegibles ce
-- INNER JOIN org_unidades u ON ce.unidad_id = u.unidad_id
-- LEFT JOIN clima_respuestas cr ON cr.periodo_id = ce.periodo_id AND cr.empleado_id = ce.empleado_id
-- WHERE ce.periodo_id = 2 AND ce.elegible = 1
-- GROUP BY u.unidad_id, u.nombre_unidad
-- ORDER BY porcentaje_participacion DESC;

-- =============================================
-- NOTAS IMPORTANTES:
-- =============================================
-- 1. Tu base de datos tiene 12 DIMENSIONES con 48 REACTIVOS (4 cada una)
-- 2. Empresa 1: empleados desde ID 5526 en adelante
-- 3. Empresa 2: empleados desde ID 6093 en adelante
-- 4. Ajusta el rango de empleado_id según tu empresa en la llamada al procedimiento
-- 5. Verifica que existen unidades en org_unidades para tu empresa
-- 6. Los periodos 1 (2025) y 2 (2026) YA EXISTEN en tu base de datos
-- 7. Este script genera datos ALEATORIOS para pruebas
-- 8. Los promedios generados seguirán una distribución realista
-- 9. Puedes ejecutar varias veces modificando los parámetros

SELECT '✅ Script de datos de prueba completado' as status;
