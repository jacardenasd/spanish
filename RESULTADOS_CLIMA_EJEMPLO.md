# Clima Laboral - Ejemplo de Resultados
## Nuevo Orden: Empresa → Área/Unidad

### 📊 Cambios Realizados

Se reorganizó `clima_resultados_mi_unidad.php` para mostrar:

1. **Primero**: Resultados de la EMPRESA (promedio general)
2. **Luego**: Resultados del ÁREA/UNIDAD (solo si existen datos)

---

## 📈 Ejemplo de Datos

### EMPRESA - Promedio General
- **Promedio**: 72.0%
- **Respondentes**: 150 personas
- **Escala**: 0-100

#### Desglose por Dimensión:

| Dimensión | Promedio | Estado |
|-----------|----------|--------|
| Liderazgo | 75.5% | ✅ Bueno |
| Comunicación | 70.2% | ✅ Bueno |
| Ambiente Laboral | 68.5% | ⚠️ Regular |
| Reconocimiento | 72.8% | ✅ Bueno |
| Desarrollo | 71.0% | ✅ Bueno |

---

### MI ÁREA (Dirección de Operaciones) - Resultados Específicos
- **Promedio**: 65.0%
- **Respondentes**: 45 personas
- **Escala**: 0-100

#### Desglose por Dimensión:

| Dimensión | Promedio | Comparativa |
|-----------|----------|-------------|
| Liderazgo | 62.5% | ❌ -13.0% vs empresa |
| Comunicación | 64.2% | ❌ -6.0% vs empresa |
| Ambiente Laboral | 62.0% | ❌ -6.5% vs empresa |
| Reconocimiento | 68.5% | ❌ -4.3% vs empresa |
| Desarrollo | 66.8% | ❌ -4.2% vs empresa |

---

## 🎨 Código Generado - Estructura Visual

```
┌─────────────────────────────────────────────┐
│  Resultados de la Empresa                   │
├─────────────────────────────────────────────┤
│                                             │
│  [Gauge: 72.0%]    [Chart: Dimensiones]    │
│                    - Liderazgo: 75.5%      │
│  150 personas      - Comunicación: 70.2%   │
│  respondieron      - Ambiente: 68.5%       │
│                    - Reconocimiento: 72.8% │
│                    - Desarrollo: 71.0%     │
│                                             │
│  ┌──────────────────────────────────────┐  │
│  │ Resultados Detallados por Dimensión │  │
│  ├──────────────────────────────────────┤  │
│  │ [75.5%] [70.2%] [68.5%]              │  │
│  │ [72.8%] [71.0%] ...                  │  │
│  └──────────────────────────────────────┘  │
│                                             │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│  Resultados de Mi Área                      │
├─────────────────────────────────────────────┤
│                                             │
│  [Gauge: 65.0%]    [Chart: Dimensiones]    │
│                    - Liderazgo: 62.5%      │
│  45 personas       - Comunicación: 64.2%   │
│  respondieron      - Ambiente: 62.0%       │
│                    - Reconocimiento: 68.5% │
│                    - Desarrollo: 66.8%     │
│                                             │
│  ┌──────────────────────────────────────┐  │
│  │ Resultados Detallados por Dimensión │  │
│  ├──────────────────────────────────────┤  │
│  │ [62.5%] [64.2%] [62.0%]              │  │
│  │ [68.5%] [66.8%] ...                  │  │
│  └──────────────────────────────────────┘  │
│                                             │
│  📌 Comparativa: Puedes comparar con el    │
│     promedio general de la empresa          │
│                                             │
└─────────────────────────────────────────────┘
```

---

## 🔧 Características del Nuevo Diseño

### Secciones del Resultado por Empresa:
1. **Gauge Visual** (indicador semicircular 0-100)
2. **Gráfico de Barras** con dimensiones ordenadas
3. **Tarjetas Detalladas** con colores según desempeño:
   - 🔵 **Azul**: 70% o más (Bueno)
   - 🟢 **Verde**: 50-69% (Regular)
   - 🟠 **Naranja**: 30-49% (Alerta)
   - 🔴 **Rojo**: Menos de 30% (Crítico)
4. **Badge** mostrando cantidad de respondentes

### Secciones del Resultado por Área:
- Idéntica estructura que empresa
- Con énfasis en comparación
- Alerta si no hay datos suficientes

---

## 📝 Cambios en el Código PHP

### Variables Modificadas:

**Antes:**
```php
$resultados = null;
$promedios_dimensiones = array();
```

**Ahora:**
```php
$resultados_empresa = null;
$resultados_unidad = null;
$promedios_dimensiones_empresa = array();
$promedios_dimensiones_unidad = array();
```

### Queries SQL:

Se agregaron dos queries separadas:

1. **Para Empresa** (sin filtro de unidad_id)
2. **Para Unidad** (con WHERE ce.unidad_id = ?)

---

## 🎯 Colores de Indicadores

| Rango | Color | Hex | Significado |
|-------|-------|-----|-------------|
| 0-29% | 🔴 Rojo | #EF5350 | Crítico |
| 30-49% | 🟠 Naranja | #FFA726 | Alerta |
| 50-69% | 🟢 Verde | #66BB6A | Regular |
| 70-100% | 🔵 Azul | #29B6F6 | Bueno |

---

## 📊 Interpretación de Datos

### Escala de Conversión:
- **Respuesta 1/5** = 0% (Muy en desacuerdo)
- **Respuesta 2/5** = 25% (En desacuerdo)
- **Respuesta 3/5** = 50% (Neutral)
- **Respuesta 4/5** = 75% (De acuerdo)
- **Respuesta 5/5** = 100% (Muy de acuerdo)

**Fórmula de conversión:**
```
Porcentaje = ((Promedio_1_5 - 1) / 4) * 100
```

---

## 🚀 Para Insertar Datos de Prueba

Utiliza el archivo `datos_ejemplo_clima.sql`:

```bash
mysql -u usuario -p base_datos < datos_ejemplo_clima.sql
```

O ejecuta manualmente en tu cliente MySQL:
- Copia el contenido de `datos_ejemplo_clima.sql`
- Ejecuta en tu base de datos
- Ajusta `empresa_id`, `periodo_id` y `unidad_id` según necesites

---

## ✅ Verificación

Para verificar que los datos se insertan correctamente:

```sql
-- Total de respuestas
SELECT COUNT(*) FROM clima_respuestas WHERE periodo_id = 1;

-- Promedio por empresa
SELECT ROUND((AVG(valor) - 1) / 4 * 100, 2) FROM clima_respuestas WHERE periodo_id = 1;

-- Promedio por dimensión
SELECT d.nombre, ROUND((AVG(cr.valor) - 1) / 4 * 100, 2) 
FROM clima_respuestas cr
JOIN clima_reactivos crt ON cr.reactivo_id = crt.reactivo_id
JOIN clima_dimensiones d ON crt.dimension_id = d.dimension_id
GROUP BY d.dimension_id;
```

---

## 📌 Notas Importantes

- Los datos individuales permanecen confidenciales
- Solo se muestran promedios agregados
- El usuario ve su propia unidad si tiene permisos
- Los gráficos se generan con ECharts en tiempo real
- El orden ahora es: **Empresa → Unidad**
