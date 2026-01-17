# Generación de Documentos - Kit de Contratación

## Flujo de Trabajo

### 1. Captura de Datos
- Acceder a `contratos_gestionar.php?nuevo_ingreso_id=X` o `contratos_gestionar.php?empleado_id=X`
- Completar las 4 secciones del formulario:
  - **Datos Básicos**: Nombre, apellidos, RFC, CURP, NSS, fecha nacimiento, sexo, nacionalidad, lugar nacimiento
  - **Domicilio**: Calle, números, colonia, CP, estado, municipio
  - **Datos Laborales**: Apoderado legal, fecha alta, tipo nómina, sueldos, banco, cuenta, CLABE
  - **Datos Complementarios**: Correos, teléfonos, escolaridad, UMF, crédito Infonavit

### 2. Validación Automática
El sistema marca `datos_completos = 1` cuando se cumplen los requisitos mínimos:
- RFC válido ✓
- CURP válido (18 caracteres) ✓
- NSS válido ✓
- Sueldo mensual > 0 ✓
- Banco seleccionado ✓

### 3. Extracción Automática desde CURP
Al capturar el CURP (18 caracteres), el sistema extrae:
- **Fecha de nacimiento**: Posiciones 5-10 (AAMMDD)
- **Sexo**: Posición 11 (H=Masculino, M=Femenino)
- **Lugar de nacimiento**: Posiciones 12-13 (código de estado)
- **Nacionalidad**: NE = Extranjero, otros = Mexicano

### 4. Generación de Documentos

#### Botón "Generar Documentos"
- Solo habilitado cuando `datos_completos = 1`
- Ubicado en la sección "Resumen" junto al botón "Editar"

#### Modal de Selección
Al hacer clic en "Generar Documentos", se abre un modal con 4 opciones:

1. **Contrato por Tiempo Determinado** (`contrato_determinado.html`)
   - Contrato temporal con fecha de término (90 días desde fecha de alta)
   - Incluye cláusulas de confidencialidad y beneficios

2. **Contrato por Tiempo Indeterminado** (`contrato_indeterminado.html`)
   - Contrato indefinido sin fecha de término
   - Cláusulas estándar LFT

3. **Póliza Fonacot FH-250** (`poliza_fh_250.html`)
   - Formato de póliza de seguro Fonacot
   - Datos del empleado y beneficiarios

4. **Carta Patronal FH** (`carta_patronal_fh.html`)
   - Carta del empleador para trámites Fonacot
   - Comprobante de relación laboral

#### Proceso de Generación
1. Usuario hace clic en "Descargar" del documento deseado
2. Sistema valida `datos_completos = 1`
3. Carga plantilla HTML desde `resources/contratos/`
4. Reemplaza placeholders con datos del empleado:
   - `{{NOMBRE}}`, `{{RFC}}`, `{{CURP}}`, `{{NSS}}`
   - `{{SEXO}}`, `{{NACIONALIDAD}}`, `{{LUGAR_DE_NACIMIENTO}}`
   - `{{F_NACIMIENTO}}`, `{{DOMICILIO}}`, `{{PUESTO}}`
   - `{{SUELDO}}`, `{{LETRA}}` (sueldo en letras)
   - `{{F_INICIO_1er_contrato}}`, `{{FTERMINO_3er_contrato}}`
   - `{{APODERADO_LEGAL}}`, `{{FECHA_GENERACION}}`
5. Genera PDF con Dompdf
6. Guarda en `storage/contratos/{empresa_id}/{empleado_id}/`
7. Registra en tabla `contratos_documentos` (si existe)
8. Descarga automáticamente el PDF

## Archivos Involucrados

### Backend
- **`public/contratos_generar_pdf.php`**: Endpoint para generar PDFs
- **`includes/contratos/pdf_generator.php`**: Servicio de generación con Dompdf
  - `contratos_render_pdf()`: Renderiza plantilla HTML → PDF
  - `contratos_registrar_documento()`: Guarda registro en DB
  - `contratos_generar_y_guardar()`: Función completa (generar + guardar + registrar)

### Frontend
- **`public/contratos_gestionar.php`**: Interfaz de captura y generación
  - Línea 203-215: Botón "Generar Documentos" (condicional)
  - Línea 704-765: Modal de selección de documentos
  - Línea 660-693: JavaScript `generarDocumento(tipoDoc)`

### Plantillas
- **`resources/contratos/contrato_determinado.html`**
- **`resources/contratos/contrato_indeterminado.html`**
- **`resources/contratos/poliza_fh_250.html`**
- **`resources/contratos/carta_patronal_fh.html`**

### Storage
- **`storage/contratos/{empresa_id}/{empleado_id}/`**: PDFs generados
- **`storage/contratos/.htaccess`**: Protección contra acceso directo

## Placeholders Disponibles

Los siguientes placeholders se pueden usar en las plantillas HTML:

| Placeholder | Descripción | Ejemplo |
|-------------|-------------|---------|
| `{{NOMBRE}}` | Nombre del empleado | Juan |
| `{{APELLIDO_PATERNO}}` | Apellido paterno | Pérez |
| `{{APELLIDO_MATERNO}}` | Apellido materno | García |
| `{{NOMBRE_COMPLETO}}` | Nombre completo | Juan Pérez García |
| `{{RFC}}` | RFC | PEGJ900101XXX |
| `{{CURP}}` | CURP | PEGJ900101HDFRRN09 |
| `{{NSS}}` | Número de Seguro Social | 12345678901 |
| `{{SEXO}}` | Sexo (MASCULINO/FEMENINO) | MASCULINO |
| `{{NACIONALIDAD}}` | Nacionalidad | MEXICANA |
| `{{LUGAR_DE_NACIMIENTO}}` | Estado/lugar nacimiento | HIDALGO |
| `{{F_NACIMIENTO}}` | Fecha nacimiento | 01/01/1990 |
| `{{DOMICILIO}}` | Domicilio completo | Calle 1 #2, Col. Centro, CDMX |
| `{{PUESTO}}` | Puesto | Analista |
| `{{SUELDO}}` | Sueldo mensual | 25,000.00 |
| `{{SUELDO_DIARIO}}` | Sueldo diario | 833.33 |
| `{{LETRA}}` | Sueldo en letra | VEINTICINCO MIL PESOS 00/100 M.N. |
| `{{F_INICIO_1er_contrato}}` | Fecha alta | 01/02/2026 |
| `{{FTERMINO_3er_contrato}}` | Fecha término (+90 días) | 02/05/2026 |
| `{{APODERADO_LEGAL}}` | Nombre apoderado | Nombre Apoderado |
| `{{FECHA_GENERACION}}` | Fecha generación PDF | 13/01/2026 |

## Funciones Auxiliares

### `numeroALetras($numero)`
Convierte cantidad numérica a texto en español:
- `25000.50` → `"VEINTICINCO MIL PESOS 50/100 M.N."`
- Soporta millones, miles, centenas
- Casos especiales: 100 = CIEN, 1 = UN

### Validaciones
- `datos_completos = 1`: Solo permite generar si los datos están completos
- Directorio storage: Se crea automáticamente si no existe
- Permisos: Solo usuarios autenticados con sesión activa

## Seguridad

1. **Autenticación**: Requiere sesión activa (`$_SESSION['usuario_id']`)
2. **Validación empresa**: Solo acceso a datos de la empresa del usuario
3. **Protección storage**: `.htaccess` bloquea acceso directo a PDFs
4. **Sanitización**: Todos los datos se escapan con `htmlspecialchars()` o `strtoupper()`

## Base de Datos

### Tabla `contratos_documentos` (si existe)
```sql
documento_id, contrato_id, empleado_id, empresa_id, 
tipo_documento, nombre_archivo, ruta_archivo, 
extension, tamanio, fecha_generacion, generado_por
```

## Próximos Pasos

- [ ] Agregar vista de historial de documentos generados
- [ ] Implementar re-generación de documentos con nueva fecha
- [ ] Agregar firma digital en PDFs
- [ ] Envío automático por email al empleado
- [ ] Integración con tabla `contratos` para vincular contratos activos
