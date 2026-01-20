-- Script para generar datos de ejemplo - Clima Laboral
-- Este script inserta datos de ejemplo para probar la visualización de resultados
-- por empresa y unidad

-- =====================================================
-- IMPORTANTE: Ajusta los IDs según tu base de datos
-- =====================================================

-- Verificar y obtener IDs válidos
-- Descomenta estas líneas para ver los IDs disponibles:
-- SELECT * FROM empresas LIMIT 5;
-- SELECT * FROM clima_periodos LIMIT 5;
-- SELECT * FROM org_unidades LIMIT 5;
-- SELECT * FROM clima_dimensiones LIMIT 5;

-- Para este ejemplo, usaremos:
-- empresa_id = 1
-- periodo_id = 1
-- unidad_id = 1 (para área específica)
-- dimension_ids = 1,2,3,4,5 (las dimensiones del clima)

-- =====================================================
-- 0. DIAGNÓSTICO - Ver dimensiones y reactivos disponibles
-- =====================================================
SELECT 'DIMENSIONES DISPONIBLES' AS info;
SELECT dimension_id, nombre, activo FROM clima_dimensiones WHERE activo = 1 ORDER BY orden;

SELECT 'TOTAL REACTIVOS POR DIMENSIÓN' AS info;
SELECT 
  d.dimension_id, 
  d.nombre, 
  COUNT(cr.reactivo_id) AS total_reactivos
FROM clima_dimensiones d
LEFT JOIN clima_reactivos cr ON cr.dimension_id = d.dimension_id AND cr.activo = 1
WHERE d.activo = 1
GROUP BY d.dimension_id, d.nombre
ORDER BY d.orden;

BEGIN;

-- =====================================================
-- 1. Insertar datos de elegibilidad - EMPRESA
-- =====================================================
-- Insertar 161 empleados como elegibles para la empresa (universo aplicable)
INSERT INTO clima_elegibles (periodo_id, empresa_id, unidad_id, empleado_id, elegible)
SELECT 1, 1, u.unidad_id, e.empleado_id, 1
FROM empleados e
LEFT JOIN org_unidades u ON u.unidad_id = e.unidad_id
WHERE e.empresa_id = 1
LIMIT 161
ON DUPLICATE KEY UPDATE elegible = 1;

-- =====================================================
-- 2. Insertar respuestas para EMPRESA (90% participación)
-- =====================================================
-- Insertar respuestas para empleados que "respondieron" (90%)
-- Los valores estarán distribuidos para un promedio de 72% (escala 1-5 = 3.88)

DELETE FROM clima_respuestas 
WHERE periodo_id = 1 AND empleado_id IN (
  SELECT empleado_id FROM clima_elegibles WHERE periodo_id = 1 AND empresa_id = 1
);

INSERT INTO clima_respuestas (periodo_id, empleado_id, reactivo_id, valor, fecha_respuesta)
SELECT 
  1 AS periodo_id,
  ce.empleado_id,
  crt.reactivo_id,
  CASE 
    WHEN RAND() < 0.15 THEN 2  -- 15% respuestas bajas (2/5 = 40%)
    WHEN RAND() < 0.25 THEN 3  -- 25% respuestas medias (3/5 = 60%)
    WHEN RAND() < 0.45 THEN 4  -- 45% respuestas altas (4/5 = 80%)
    ELSE 5                      -- 15% respuestas muy altas (5/5 = 100%)
  END AS valor,
  NOW() AS fecha_respuesta
FROM clima_elegibles ce
CROSS JOIN (
  SELECT DISTINCT reactivo_id FROM clima_reactivos WHERE activo = 1
) crt
WHERE ce.periodo_id = 1 AND ce.empresa_id = 1 AND ce.elegible = 1
  AND RAND() > 0.10;

-- =====================================================
-- 3. Insertar respuestas para UNIDAD ESPECÍFICA (90% participación)
-- =====================================================
-- Para la unidad_id = 1, con 90% de participación
-- Con promedio más bajo (65%)

DELETE FROM clima_respuestas 
WHERE periodo_id = 1 AND empleado_id IN (
  SELECT empleado_id FROM clima_elegibles WHERE periodo_id = 1 AND unidad_id = 1
);

INSERT INTO clima_respuestas (periodo_id, empleado_id, reactivo_id, valor, fecha_respuesta)
SELECT 
  1 AS periodo_id,
  ce.empleado_id,
  crt.reactivo_id,
  CASE 
    WHEN RAND() < 0.25 THEN 2  -- 25% respuestas bajas (más problemas en el área)
    WHEN RAND() < 0.35 THEN 3  -- 35% respuestas medias
    WHEN RAND() < 0.30 THEN 4  -- 30% respuestas altas
    ELSE 5                      -- 10% respuestas muy altas
  END AS valor,
  NOW() AS fecha_respuesta
FROM clima_elegibles ce
CROSS JOIN (
  SELECT DISTINCT reactivo_id FROM clima_reactivos WHERE activo = 1
) crt
WHERE ce.periodo_id = 1 AND ce.unidad_id = 1 AND ce.elegible = 1
  AND RAND() > 0.10;

-- =====================================================
-- Verificación de datos insertados
-- =====================================================
SELECT 'VERIFICACIÓN - Respuestas totales por empresa' AS verificacion;
SELECT COUNT(*) AS total_respuestas FROM clima_respuestas WHERE periodo_id = 1;

SELECT 'VERIFICACIÓN - Empleados elegibles' AS verificacion;
SELECT COUNT(*) AS total_elegibles FROM clima_elegibles WHERE periodo_id = 1 AND empresa_id = 1;

SELECT 'VERIFICACIÓN - Respondientes empresa (debe ser ~90%)' AS verificacion;
SELECT 
  COUNT(DISTINCT empleado_id) AS respondientes,
  ROUND(COUNT(DISTINCT empleado_id) / 161 * 100, 1) AS porcentaje_participacion
FROM clima_respuestas WHERE periodo_id = 1;

SELECT 'VERIFICACIÓN - Promedio general (debe ser ~3.88 = 72%)' AS verificacion;
SELECT AVG(valor) AS promedio_1_5, 
       ROUND((AVG(valor) - 1) / 4 * 100, 2) AS promedio_0_100
FROM clima_respuestas WHERE periodo_id = 1;

SELECT 'VERIFICACIÓN - Respondientes por unidad 1' AS verificacion;
SELECT 
  COUNT(DISTINCT ce.empleado_id) AS respondientes_unidad,
  (SELECT COUNT(DISTINCT empleado_id) FROM clima_elegibles WHERE periodo_id = 1 AND unidad_id = 1) AS total_unidad,
  ROUND(
    COUNT(DISTINCT ce.empleado_id) / 
    (SELECT COUNT(DISTINCT empleado_id) FROM clima_elegibles WHERE periodo_id = 1 AND unidad_id = 1) * 100, 1
  ) AS porcentaje_participacion
FROM clima_respuestas cr
INNER JOIN clima_elegibles ce ON cr.periodo_id = ce.periodo_id AND cr.empleado_id = ce.empleado_id
WHERE cr.periodo_id = 1 AND ce.unidad_id = 1;

SELECT 'VERIFICACIÓN - Promedio por unidad 1 (debe ser ~3.65 = 65%)' AS verificacion;
SELECT AVG(cr.valor) AS promedio_1_5,
       ROUND((AVG(cr.valor) - 1) / 4 * 100, 2) AS promedio_0_100
FROM clima_respuestas cr
INNER JOIN clima_elegibles ce ON cr.periodo_id = ce.periodo_id AND cr.empleado_id = ce.empleado_id
WHERE cr.periodo_id = 1 AND ce.unidad_id = 1;

COMMIT;

-- =====================================================
-- NOTAS DE PRUEBA:
-- =====================================================
-- 1. Verifica que los IDs de empresa_id, periodo_id y unidad_id existan en tu BD
-- 2. Ejecuta solo las líneas que necesites según tu estructura
-- 3. Los porcentajes son aproximados debido a la naturaleza aleatoria
-- 4. Puedes ajustar las distribuciones CASE para obtener porcentajes específicos
-- 5. Para datos más realistas, distribuye mejor los valores según cada dimensión

-- Ejemplo de obtener datos por dimensión:
SELECT 'Promedios por DIMENSIÓN - EMPRESA' AS reporte;
SELECT 
  d.dimension_id,
  d.nombre AS dimension,
  COUNT(DISTINCT cr.empleado_id) AS total_respondentes,
  COUNT(cr.reactivo_id) AS total_respuestas,
  ROUND(AVG(cr.valor), 2) AS promedio_1_5,
  ROUND((AVG(cr.valor) - 1) / 4 * 100, 2) AS promedio_0_100
FROM clima_respuestas cr
INNER JOIN clima_reactivos crt ON crt.reactivo_id = cr.reactivo_id
INNER JOIN clima_dimensiones d ON d.dimension_id = crt.dimension_id
INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
WHERE cr.periodo_id = 1 AND ce.empresa_id = 1
GROUP BY d.dimension_id, d.nombre
ORDER BY d.dimension_id;

SELECT 'Promedios por DIMENSIÓN - UNIDAD 1' AS reporte;
SELECT 
  d.dimension_id,
  d.nombre AS dimension,
  COUNT(DISTINCT cr.empleado_id) AS total_respondentes,
  COUNT(cr.reactivo_id) AS total_respuestas,
  ROUND(AVG(cr.valor), 2) AS promedio_1_5,
  ROUND((AVG(cr.valor) - 1) / 4 * 100, 2) AS promedio_0_100
FROM clima_respuestas cr
INNER JOIN clima_reactivos crt ON crt.reactivo_id = cr.reactivo_id
INNER JOIN clima_dimensiones d ON d.dimension_id = crt.dimension_id
INNER JOIN clima_elegibles ce ON ce.periodo_id = cr.periodo_id AND ce.empleado_id = cr.empleado_id
WHERE cr.periodo_id = 1 AND ce.unidad_id = 1
GROUP BY d.dimension_id, d.nombre
ORDER BY d.dimension_id;
