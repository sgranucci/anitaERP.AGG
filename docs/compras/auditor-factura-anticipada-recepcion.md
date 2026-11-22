# Auditor factura anticipada vs recepción

Bitácora del control diario `compras:auditar-factura-anticipada-recepcion`  
Tabla `proveedor_factura_anticipada_recepcion_desvio` · cuenta de anticipos por empresa.

Por cada OC anticipada se suma el Debe de facturas anticipadas y el Haber de recepciones
en la cuenta de anticipos. Si la recepción acredita más que lo facturado, es **desvío**.

La alerta del mail es solo por desvío **nuevo** o por **cambio del exceso** respecto de la
base del día anterior. Los desvíos con el mismo exceso se listan como **ya informados**
(sin alerta). La primera corrida sin base queda como medición inicial (sin alerta).

OPA no entra: las mira `compras:auditar-deuda-ficha-proveedor`.

---

## Base vigente (aceptada)

Corte **09/11/2026** · Biyemas (empresa 1), ampliada **14/11/2026** y **21/11/2026**.  
Desvíos documentados y dejados como ya informados (sin corregir en ERP).

| Empresa | OC | Proveedor | Exceso recepción | Estado |
|---|---|---|---:|---|
| Biyemas | **223218** (id 29240) | 5176 ALVAREZ MIRIAM ANALIA | **280.000,00** | Aceptada / ya informada (09/11) |
| Biyemas | **215834** (id 6887) | 3904 CONGRESO INSUMOS SA | **498.461,50** | Aceptada / transferida desde Anita (14/11) |
| Biyemas | **206539** (id 2145) | 4801 DELL ACQUA JULIETA SUSANA | **8.302.000,00** | Aceptada / transferida desde Anita (21/11) |
| Biyemas | **211276** (id 4632) | 352 TEKNICA ELEVACION S.A | **6.109.468,90** | Aceptada / transferida desde Anita (21/11) |

### Detalle OC 223218

- Tratamiento ANTICIPADA · OC neta 2.180.000 (honorarios) · cumplida.
- Facturas anticipadas en 114040001:
  - A-2-986 (CP 34052): anticipo 570.000
  - A-2-996 (CP 34051): anticipo 1.330.000
  - Total facturado anticipado: **1.900.000**
- Recepción COM **168181** (R 68571) · asiento **366598** · fecha **02/10/2026**:
  - Debe 521140002 Honorarios 2.180.000
  - Haber 114040001 Anticipos **−2.180.000** (toda la OC)
- Remanente facturado después: A-2-1010 (CP 34869, 27/10) 338.800 contra
  *Prov. automática de FC a recibir* (280.000) + IVA.
- Decisión 09/11/2026: **no corregir** el asiento de octubre; documentar el desvío
  de 280.000 como base aceptada.

### Detalle OC 215834 (transferida desde Anita)

- Tratamiento ANTICIPADA · 4 cartuchos Epson (LIB0421–LIB0424) × 1 · OC neta **545.826,62**.
- Origen: recepciones `ANITA_IMPORT` (COM Anita → ERP).
- Factura anticipada en 114040001:
  - A-12-26509 (CP 27450) · asiento **119361** · anticipo **545.826,65**
- Recepciones (ambas a Otros Activos 117010001 / Anticipos):
  - COM **160058** (R 57440) · asiento **119510** · 06/01/2026 · Haber anticipos **−498.461,53**  
    (mismos 4 SKU × 1 a precio más bajo)
  - COM **160721** (R 58395) · asiento **122161** · 02/02/2026 · Haber anticipos **−545.826,62**  
    (mismos 4 SKU × 1 al precio de la OC)
- Cantidad: OC pide 1 de cada SKU; quedan **2** recibidas de cada uno → doble aplicación a anticipos.
- Exceso = 1.044.288,15 − 545.826,65 = **498.461,50** (≈ la 1ª COM).
- Decisión 14/11/2026: **no corregir** en ERP; documentar como diferencia **transferida desde Anita**
  y dejarla en base aceptada / ya informada.

### Detalle OC 206539 (transferida desde Anita)

- Tratamiento ANTICIPADA · PUBLICIDAD (lunetas) · neto **5.726.000**.
- Origen: facturas y recepciones `ANITA_IMPORT` / asientos `subhist`.
- Facturas anticipadas 21/04/2025 en 114040001:
  - FIB A-3-73: anticipo **3.150.000**
  - FIS A-3-74: anticipo **2.576.000**
  - Total FA anticipo: **5.726.000**
- Recepciones **23/04/2025** (todas Haber anticipos):
  - COM **152785**: OC completa **−5.726.000**
  - COM **152792**: línea **−2.576.000**
  - COM **152793**: línea **−3.150.000**
  - COM **152794**: línea **−2.576.000**
  - Total COM anticipos: **−14.028.000**
- Exceso = 14.028.000 − 5.726.000 = **8.302.000** (COM total + 3 parciales).
- Decisión 21/11/2026: **no corregir** en ERP; documentar como diferencia
  **transferida desde Anita** y dejarla en base aceptada / ya informada.

### Detalle OC 211276 (transferida desde Anita)

- Tratamiento ANTICIPADA · ABONO ESCALERA MECÁNICA · OC **USD 6.814,78**.
- Origen: facturas y recepciones `ANITA_IMPORT` / asientos `subhist`.
- Facturas anticipadas (USD) en 114040001: **5.451,83**
  (A-10-81415 / 81504 / 82627); A-10-83007 va a Prov. FC a recibir.
- Recepciones (cant. OC = 1; 3 COM de cant=1):
  - COM **156895** (05/09/2025): a Prov. FC (sin anticipo)
  - COM **159064** (01/12/2025): Haber anticipos **−4.088,88** USD
  - COM **159895** (22/12/2025): Haber anticipos **−5.451,83** USD
- Exceso ME = 9.540,71 − 5.451,83 = **4.088,88** USD (= COM 159064);
  en pesos (cotiz. de cada asiento) **6.109.468,90**.
- Decisión 21/11/2026: **no corregir** en ERP; documentar como diferencia
  **transferida desde Anita** y dejarla en base aceptada / ya informada.

A partir de esta base, si los excesos de 223218, 215834, 206539 y 211276 no cambian,
el mail no alerta por ellas (sección “Desvíos ya informados”). Solo alerta si algún
exceso cambia o aparece otra OC con recepción > factura anticipada.

### Por qué la 215834 alertó recién el 14/11

El desvío contable existe desde febrero/2026, pero el auditor solo suma recepciones vía
`asiento.recepcionproveedor_id`. Hasta el **13/11/2026** la COM 160058 no estaba enganchada
al asiento 119510: el control veía solo la COM 160721 (−545.826,62) vs la FA (+545.826,65)
y el neto cerraba (sin desvío). El enganche Anita de ese día vinculó la 1ª COM; la corrida
del **14/11** la vio por primera vez → desvío nuevo vs la base del 13/11 → alerta.

### Por qué 206539 y 211276 alertaron el 21/11

Mismo mecanismo: el enganche `recepcionproveedor_id` del **19/11 ~15:15** y
**20/11 ~10:21** (después del cron 07:52) hizo visibles COM Anita de abril/dic 2025.
La corrida del **21/11** las vio vs la base del 20/11 → alerta. Aceptadas el mismo día
en la base del 20/11 para que queden como ya informadas.

---

## Comando

```bash
php artisan compras:auditar-factura-anticipada-recepcion --empresa=1 --sin-mail
```
