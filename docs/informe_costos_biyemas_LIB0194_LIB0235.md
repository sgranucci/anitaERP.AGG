# Informe de costos — Biyemas S.A.
## Artículos LIB0194 y LIB0235
**Fecha:** 08/09/2026  
**Alcance:** Solo lectura — cruce ERP + maestro Anita (`stkmae`) + historial de recepciones  
**Objetivo:** Explicar las diferencias del Excel de Contaduría y determinar si hay falla de cálculo del sistema ERP

---

## 1. Resumen ejecutivo

| Código | Descripción | Costo Excel actual | Costo Excel mes ant. | Diferencia Excel |
|--------|-------------|--------------------|----------------------|------------------|
| LIB0194 | PLASTICOLA VOLIGOMA | 420,41 | 1.982,50 | −1.562,09 |
| LIB0235 | ROLLO TERMICO BARRERA 79mm×200 | 15,00 | 21.225,00 | −21.210,00 |

**Conclusión:** las diferencias **no son un error de cálculo del ERP**. El Excel refleja (o toma de) datos de **precios de compra reales** cargados en recepciones / maestro Anita.

Sobre el **“coeficiente de 15”**:
- **No hubo cambio de coeficiente a 15.**
- En ambas recepciones el coeficiente de conversión es **1,000000**.
- En el maestro ERP `articulo.coeficienteconversion` = **0** (sin coeficiente operativo).
- No hay vínculos en `articulo_proveedor` con coeficiente para estos SKUs.
- El valor **15,00** del LIB0235 es un **precio unitario de compra histórico**, no un coeficiente.

---

## 2. Estado actual del maestro (hoy)

### 2.1 ERP (`articulo`)

| Campo | LIB0194 | LIB0235 |
|-------|---------|---------|
| Unidad | UNIDADES | UNIDADES |
| Coeficiente conversión | 0 | 0 |
| Unidades x envase | 1 | 1 |
| PPP ERP (`articulo.ppp`) | 1.266,19 | 15.044,28 |
| Fecha última compra ERP | 03/06/2025 | 22/10/2025 |

> Nota: el PPP del ERP local está desfasado respecto de Anita; la valuación operativa de stock Biyemas usa el maestro Anita.

### 2.2 Anita (`stkmae`) — fuente de precios de compra / PPP

| Campo | LIB0194 | LIB0235 |
|-------|---------|---------|
| Precio compra 1 (histórico) | **420,41** | **15,00** |
| Precio compra 2 | **420,41** | **15,00** |
| Precio compra 3 (último) | **420,41** | **21.675,00** |
| PPP Anita (`stkm_ppp`) | **420,41** | **21.675,00** |
| Fecha última compra Anita | **02/09/2026** | **13/05/2026** |

---

## 3. Análisis LIB0194 — PLASTICOLA VOLIGOMA

### 3.1 Qué muestra el Excel
- Actual **420,41** = coincide exactamente con Anita (última compra y PPP).
- Mes anterior **1.982,50** = coincide exactamente con una compra confirmada del 03/06/2025.

### 3.2 Historial de recepciones confirmadas

| Fecha | Tipo | Proveedor | Cant. | Precio unit. | Coef. | Origen |
|-------|------|-----------|-------|--------------|-------|--------|
| 01/06/2025 | RECEPCIÓN | CONGRESO INSUMOS SA | 20 | 1.000,00 | 1,00 | Import Anita |
| **03/06/2025** | RECEPCIÓN | ABRANTES SUSANA NORMA | 10 | **1.982,50** | 1,00 | Import Anita |
| 01/07/2025 | RECEPCIÓN | RIPOLL AGUSTIN | 10 | 861,98 | 1,00 | Import Anita |
| 26/12/2025 | RECEPCIÓN | ZONA OFFICE S.R.L. | 12 | **420,41** | 1,00 | Import Anita |
| 01/09/2026 | RECEPCIÓN | RIPOLL AGUSTIN | 5 | **420,41** | 1,00 | Manual (sync Anita 01/09 16:55) |
| 02/09/2026 | DEVOLUCIÓN | RIPOLL AGUSTIN | 5 | 420,41 | 1,00 | Manual |
| 02/09/2026 | RECEPCIÓN | RIPOLL AGUSTIN | 5 | **420,41** | 1,00 | Manual (sync Anita 02/09 13:00) |

### 3.3 Interpretación
1. El costo **1.982,50** existió como precio de compra real (Abrantes, jun/2025).
2. Luego hubo compras a **420,41** (Zona Office dic/2025 y Ripoll sep/2026).
3. El 02/09/2026 la recepción confirmada **actualizó Anita** (`stkm_pre_compra*` y PPP) a **420,41**.
4. El coeficiente **nunca fue 15 ni distinto de 1** en estas operaciones.
5. La baja del Excel (−1.562,09) es la comparación entre **dos precios de compra distintos de proveedores distintos**, no un recálculo erróneo del sistema.

---

## 4. Análisis LIB0235 — ROLLO TÉRMICO BARRERA 79mm×200

### 4.1 Qué muestra el Excel
- Actual **15,00**
- Mes anterior **21.225,00** (orden de magnitud de la última compra / PPP Anita ≈ **21.675,00**)

### 4.2 Historial de recepciones confirmadas

| Fecha | Proveedor | Cant. | Precio unit. | Coef. | Origen |
|-------|-----------|-------|--------------|-------|--------|
| **25/04/2025** | CARAMUTA DIEGO WALTER | 12 | **15,00** | 1,00 | Import Anita |
| **27/06/2025** | CARAMUTA DIEGO WALTER | 24 | **15,00** | 1,00 | Import Anita |
| 26/08/2025 | CARAMUTA DIEGO WALTER | 24 | 19.875,00 | 1,00 | Import Anita |
| 22/10/2025 | CARAMUTA DIEGO WALTER | 48 | **21.675,00** | 1,00 | Import Anita |
| **13/05/2026** | CARAMUTA DIEGO WALTER | 36 | **21.675,00** | 1,00 | Import Anita |

### 4.3 Estado Anita hoy
- Última compra y PPP: **21.675,00** (coherente con compras ago/2025–may/2026).
- Slots de compra 1 y 2: siguen en **15,00** (heredados de compras abr/jun 2025).

### 4.4 Interpretación del “15”
1. **No es coeficiente.** En todas las recepciones el coeficiente = **1,00**.
2. **Sí es precio de compra** cargado en dos recepciones de 2025 (mismo proveedor).
3. Ese **15,00** es anómalo frente a las compras posteriores del mismo proveedor a **19.875 / 21.675** (probable carga incorrecta en origen: precio incompleto / mal digitado).
4. Si Contaduría valúa por **última compra o PPP Anita**, el costo actual debería ser **≈ 21.675**, no 15:
   - mes anterior Excel **21.225** ≈ última compra/PPP;
   - actual Excel **15** coincide con los precios viejos (`pre_compra1/2`), **no** con la última compra.
5. Por lo tanto:
   - **no hay bug de fórmula del ERP** que “divida por 15” o aplique coeficiente 15;
   - hay que revisar **qué criterio de costo usa el Excel** (¿última compra, PPP, precio histórico 1/2?) y, en paralelo, **corregir/depurar los precios 15,00** de abr/jun 2025 si no son válidos.

---

## 5. Coeficiente: verificación explícita

| Control | Resultado |
|---------|-----------|
| `articulo.coeficienteconversion` LIB0194 / LIB0235 | 0 / 0 |
| `articulo_proveedor` (coef por proveedor) | Sin registros |
| Coeficiente en todas las recepciones listadas | **1,00** en todos los casos |
| ¿Existe cambio de coeficiente a 15? | **No** |
| ¿Qué es el 15 del LIB0235? | Precio unitario de compra histórico |

---

## 6. Conclusión para Contaduría

1. **No se observa falla de cálculo del sistema ERP** en estos dos ítems.
2. **LIB0194:** la diferencia Excel se explica por cambio real de precio de compra (de 1.982,50 a 420,41) documentado en recepciones; Anita quedó actualizado el **02/09/2026**.
3. **LIB0235:** el **15 no es coeficiente**; es un precio de compra viejo. La última compra/PPP vigentes están en **21.675**. Si el Excel muestra 15 como “actual”, conviene validar el **criterio de valuación del reporte** y la calidad de esas dos compras a 15,00.
4. Acción sugerida (operativa / Contaduría + Compras, no bug de sistema):
   - Confirmar criterio de valuación del Excel (última compra vs PPP vs otro).
   - Revisar con Compras las facturas Caramuta abr/jun 2025 del LIB0235 (precio 15,00).
   - Si corresponde, corregir maestro Anita / PPP del LIB0235 y homogenizar criterio de valuación.

---

## 7. Evidencia técnica consultada

- ERP: `articulo`, `recepcion_proveedor`, `recepcion_proveedor_articulo`, `articulo_proveedor`
- Anita: `stkmae` (precios compra 1/2/3, PPP, fecha última compra), `stkdep` (saldos)
- Empresa: Biyemas (`empresa_id` 1 / path Anita `/usr2/biyemas`)
- Generado por cruce de solo lectura el 08/09/2026
