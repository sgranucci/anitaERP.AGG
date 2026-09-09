# Informe de costos — Kandiko S.A.
## Artículos LIM0016, LIM0099, LIM0075, LIM0072
**Fecha:** 08/09/2026  
**Alcance:** Solo lectura — recepciones ERP empresa 2 (Kandiko) + maestro Anita Kandiko (`stkmae` emp. 2)  
**Objetivo:** Explicar las diferencias del Excel de Contaduría y determinar si hay falla de cálculo del ERP

---

## 1. Resumen ejecutivo

| SKU | Descripción | Excel actual | Excel mes ant. | Var. Excel | ¿Bug ERP? |
|-----|-------------|--------------|----------------|------------|-----------|
| LIM0016 | BOLSA 110X110X30MC NEG (PAQ) | 74.000,00 | 67.400,00 | +6.600,00 | **No** — suba real de compra |
| LIM0099 | PALA C/ESCOBILLA FIORENT (UNI) | 2.920,00 | 9.900,90 | −6.980,90 | **No** — actual real; mes ant. no cuadra con compras Kandiko |
| LIM0075 | GUANTES MAPA MED 8/5 (UNI) | 9.900,00 | 2.840,00 | +7.060,00 | **No** — precio cargado en factura; revisar calidad del dato |
| LIM0072 | GUANTES LATEX X 100UNID (CAJ) | 9.900,00 | 3.850,93 | +6.049,07 | **No** — precio cargado en factura; revisar calidad del dato |

**Criterio Anita:** la última compra Anita del ERP es **unificada entre BSA / KSA / RSA** (gana `stkm_pre_compra3` de la empresa con `stkm_fe_ult_compra` más reciente). No se valúa con el maestro aislado de una sola sala.

**Conclusión:** **No hay error de fórmula del sistema.**  
- LIM0016 / LIM0099 / LIM0072: el Excel actual coincide con última compra unificada (o con entrada ERP al mismo precio).  
- LIM0075: el Excel muestra **9.900**, pero la última compra Anita **unificada hoy es 2.840** (BSA 07/09/2026, más reciente que KSA 9.900 del 04/09). Si el Excel es un snapshot previo al 07/09, podía mostrar 9.900.  
Los saltos a **9.900** en guantes merecen revisión operativa (mismo importe que LIM0028 del mismo proveedor).

---

## 2. Estado Anita Kandiko hoy (`stkmae` empresa 2)

| SKU | Compra 1 | Compra 2 | Compra 3 (últ.) | PPP | F. últ. compra |
|-----|----------|----------|-----------------|-----|----------------|
| LIM0016 | 62.200 | 74.000 | **74.000** | **74.000** | 26/08/2026 |
| LIM0099 | 2.920 | 2.920 | **2.920** | **2.920** | 04/09/2026 |
| LIM0075 | 9.900 | 9.900 | **9.900** | **9.900** | 04/09/2026 |
| LIM0072 | 9.900 | 9.900 | **9.900** | **9.900** | 04/09/2026 |

Coeficientes en recepciones Kandiko de estos ítems: **1,00** (salvo un caso puntual LIM0028).  
LIM0072 en maestro ERP tiene `coeficienteconversion = 100` (caja × 100), pero las recepciones analizadas usaron **coef = 1**.

---

## 3. LIM0016 — BOLSA 110×110 (PAQ)

| Concepto | Valor | Evidencia |
|----------|-------|-----------|
| Excel actual | 74.000 | = Anita KSA PPP / última compra |
| Excel mes ant. | 67.400 | = compras jul/2026 mismo proveedor |

Recepciones Kandiko relevantes (PROYECTOS DEL NORTE):
- 08/07/2026 — 67.400,00  
- 18–26/08/2026 — **74.000,00**

**Interpretación:** variación normal de precio de compra (+9,8%). No es falla de sistema.

---

## 4. LIM0099 — PALA C/ESCOBILLA FIORENT

| Concepto | Valor | Evidencia |
|----------|-------|-----------|
| Excel actual | 2.920,00 | = Anita KSA (todo en 2.920) |
| Excel mes ant. | **9.900,90** | **No aparece** en historial de compras Kandiko de este SKU |

Historial de compras LIM0099:
| Fecha | Empresa | Proveedor | Precio |
|-------|---------|-----------|--------|
| 09/09/2025 | Kandiko | Terzaghi | 11.580,00 |
| 18/11/2025 | Kandiko | Terzaghi | 11.580,00 |
| 19/12/2025 | Kandiko | Terzaghi | 11.580,00 |
| **31/07/2026** | **Kandiko** | **Di Napoli** | **2.920,00** |

Dato cruzado: en Anita **Biyemas** (emp. 1), LIM0099 tiene `stkm_pre_compra3 = 9.900,90` (fecha 12/07/2024).  
Ese valor **coincide al centavo** con el “Ctl mes anterior” del Excel Kandiko.

**Interpretación:**
1. El costo actual **2.920** es real (recepción Kandiko 31/07/2026).
2. El mes anterior **9.900,90** no sale de compras Kandiko; coincide con el maestro Biyemas.
3. Conviene que Contaduría verifique de dónde tomó el control del mes anterior (¿archivo cruzado entre empresas?).
4. No es un recálculo erróneo del ERP Kandiko.

---

## 5. LIM0075 — GUANTES MAPA MED 8/5

| Concepto | Valor | Evidencia |
|----------|-------|-----------|
| Excel actual | 9.900,00 | = Anita KSA PPP |
| Excel mes ant. | 2.840,00 | = remitos Llano jul/2026 |

Cadena Kandiko (proveedor LLANO):
| Fecha | Tipo | Comp. | Precio LIM0075 |
|-------|------|-------|----------------|
| 28/07/2026 | Recepción | REM 5-910 | **2.840,00** |
| 31/07/2026 | Recepción | fac 4-573 (Di Napoli) | 2.840,00 |
| 14/08/2026 | Devolución | REM 5-910 | 2.840,00 |
| 14/08/2026 | Recepción | **FAC 3-869** | 3.900,00 |
| **24/08/2026** | Recepción | **fac 3-865** | **9.900,00** |

En la misma **fac 3-865** el artículo **LIM0028 (BOLSA 60×90)** también figura a **9.900,00** (precio habitual de esa bolsa en Llano).

**Interpretación:** el Excel está alineado con Anita. La suba 2.840 → 9.900 viene de la carga de la factura 3-865.  
**Sospecha operativa (no bug de sistema):** posible precio mal asignado a la línea de guantes (mismo importe que la bolsa LIM0028 en el mismo comprobante). Revisar factura física con Compras.

---

## 6. LIM0072 — GUANTES LÁTEX × 100 (CAJ)

| Concepto | Valor | Evidencia |
|----------|-------|-----------|
| Excel actual | 9.900,00 | = Anita KSA PPP |
| Excel mes ant. | 3.850,93 | = remito Llano 5-910 |

Cadena Kandiko (LLANO):
| Fecha | Tipo | Comp. | Precio LIM0072 |
|-------|------|-------|----------------|
| 28/07/2026 | Recepción | REM 5-910 | **3.850,93** |
| 14/08/2026 | Devolución | REM 5-910 | 3.850,93 |
| **14/08/2026** | Recepción | **FAC 3-869** | **9.900,00** |

En esa misma FAC 3-869:
- LIM0072 (guantes látex caja) → **9.900**
- LIM0075 (guantes mapa) → 3.900
- LIM0028 no está en esa factura, pero **9.900** es el precio típico de LIM0028

**Interpretación:** igual que LIM0075: el sistema grabó lo cargado en la recepción.  
La variación del Excel no es un bug; hay que validar si **9.900** es el precio real de la caja de látex o un error de carga al facturar el remito.

Nota: maestro ERP LIM0072 tiene coeficiente **100** (caja). En estas recepciones se usó **coef = 1**. No explica por sí solo el salto a 9.900 (3.850,93 × factores razonables no da 9.900).

---

## 7. Patrón del valor 9.900

| Dónde aparece 9.900 | Contexto |
|---------------------|----------|
| LIM0028 BOLSA 60×90 | Precio habitual Llano (jul–ago) |
| LIM0072 guantes látex | FAC 3-869 (14/08) — reemplazo de remito a 3.850,93 |
| LIM0075 guantes mapa | fac 3-865 (24/08) — junto a LIM0028 a 9.900 |
| LIM0099 Excel “mes ant.” | **9.900,90** = coincide con Anita **Biyemas**, no con compras Kandiko |

Esto refuerza: no es un coeficiente mágico ni un bug de valuación; son **precios unitarios cargados** (y en un caso, posible cruce de control entre empresas).

---

## 8. Conclusión para Contaduría

1. **No se observa falla de cálculo del ERP** en estos cuatro ítems Kandiko.
2. **LIM0016:** suba real de compra 67.400 → 74.000 (Proyectos del Norte).
3. **LIM0099:** actual 2.920 correcto (Di Napoli). El control mes ant. **9.900,90** no tiene respaldo en compras Kandiko y coincide con Anita Biyemas → revisar origen del control anterior.
4. **LIM0075 y LIM0072:** el Excel refleja Anita (PPP 9.900). El cambio viene de facturas Llano ago/2026. **Recomendación:** contrastar FAC 3-869 y fac 3-865 con comprobantes físicos; el 9.900 se repite con la bolsa LIM0028.
5. Acción sugerida: Compras/Contaduría validen esas facturas; si el precio es incorrecto, corregir recepciones y re-sincronizar `stkmae` Kandiko (no es parche de sistema).

---

## 9. Evidencia consultada

- ERP: `recepcion_proveedor` / `recepcion_proveedor_articulo` (`empresa_id = 2`)
- Anita: `stkmae` empresas 1, 2 y 3 (comparativo)
- Generado 08/09/2026 — solo lectura
