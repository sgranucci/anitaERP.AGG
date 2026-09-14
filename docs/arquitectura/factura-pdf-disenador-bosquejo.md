# Bosquejo: diseñador de facturas PDF

Objetivo: permitir a Ferli, Bierzo y demás clientes armar el layout de FAC/REM/ENVÍO sin hardcode en Blade, con plantillas versionables por empresa.

## Qué hay hoy

| Capa | Rol |
|---|---|
| Blade `formulariofactura_*` | Layout único Anita/Ferli (encabezado, cuerpo, pie) |
| `factura_pdf_parametro` | Textos de membrete / leyendas por empresa |
| Programa impresión | Orden FACTURA → REMITO → ENVÍO y salidas |
| DomPDF | Render fijo A4/legal |

No hay editor visual: cambiar layout = deploy de código.

## Propuesta (MVP + premium)

### 1. Modelo de plantilla

- Tabla `factura_pdf_plantilla` (`empresa_id`, `formulario` FAC|REM|ENVIO, `nombre`, `version`, `activa`, `json_layout`, `preview_png`).
- Una plantilla **activa** por empresa+formulario; el resto queda histórico.
- El JSON describe **bloques** anclados a una grilla (mm), no HTML libre.

### 2. Bloques canónicos (biblioteca)

| Bloque | Datos |
|---|---|
| `logo` | Archivo empresa / override |
| `emisor` | Razón social, CUIT, domicilio, IVA, inicio act. |
| `receptor` | Snapshot venta / cliente |
| `comprobante` | Tipo, letra, PV, número, fecha, CAE/QR |
| `items` | Columnas configurables (SKU, desc, talle, pares, precio…) |
| `totales` | Conceptos IVA / tributos |
| `leyendas` | Desde `factura_pdf_parametro` |
| `firma` / `transporte` / `custom_texto` | Opcionales |

Cada bloque: `x,y,w,h`, tipografía, bordes, visibilidad por letra (A/B/C) o tipo.

### 3. UI diseñador (pantalla)

- Canvas A4/legal con reglas en mm (estilo “premium”: WYSIWYG acotado, no Canva genérico).
- Panel izquierdo: bloques arrastrables.
- Panel derecho: propiedades del bloque seleccionado.
- Barra: Guardar borrador / Publicar versión / Vista previa PDF real (DomPDF).
- No editar SQL ni PHP desde la UI.

### 4. Motor de render

1. Resolver plantilla activa (`empresa` + `formulario`).
2. Hidratar contexto (mismo `prepararContextoPdfFactura` / remito / envío).
3. Pintar bloques → HTML intermedio o PDF directo.
4. Fallback: Blade actual si no hay plantilla activa.

### 5. Fases

| Fase | Entrega |
|---|---|
| **A (ya)** | `factura_pdf_parametro` + remito ERP + ENVÍO scaffold |
| **B** | ABM de parámetros membrete (sin diseñador) |
| **C** | Motor JSON mínimo + 1 plantilla Ferli clonada del Blade actual |
| **D** | Editor visual + versionado + preview |
| **E** | Plantillas REM/ENVÍO y presets Bierzo |

### 6. Fuera de alcance (por ahora)

- HTML/CSS arbitrario del usuario (XSS + DomPDF frágil).
- Subir tipografías custom sin whitelist.
- Diseñar asientos / tickets / gastronomía en el mismo editor.

### 7. Criterio de éxito

Un operador Ferli cambia logo, anchos de columnas de ítems y leyendas del pie **sin deploy**, y el pack FACTURA→REMITO→ENVÍO sigue imprimiendo con el mismo programa.
