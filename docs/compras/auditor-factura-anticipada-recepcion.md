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

Corte **09/11/2026** · Biyemas (empresa 1), ampliada **14/11/2026**.  
Desvíos documentados y dejados como ya informados (sin corregir en ERP).

| Empresa | OC | Proveedor | Exceso recepción | Estado |
|---|---|---|---:|---|
| Biyemas | **223218** (id 29240) | 5176 ALVAREZ MIRIAM ANALIA | **280.000,00** | Aceptada / ya informada (09/11) |
| Biyemas | **215834** (id 6887) | 3904 CONGRESO INSUMOS SA | **498.461,50** | Aceptada / transferida desde Anita (14/11) |

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

A partir de esta base, si los excesos de 223218 (280.000) y 215834 (498.461,50) no cambian,
el mail queda sin alerta nueva (sección “Desvíos ya informados”). Solo alerta si algún exceso
cambia o aparece otra OC con recepción > factura anticipada.

### Por qué la 215834 alertó recién el 14/11

El desvío contable existe desde febrero/2026, pero el auditor solo suma recepciones vía
`asiento.recepcionproveedor_id`. Hasta el **13/11/2026** la COM 160058 no estaba enganchada
al asiento 119510: el control veía solo la COM 160721 (−545.826,62) vs la FA (+545.826,65)
y el neto cerraba (sin desvío). El enganche Anita de ese día vinculó la 1ª COM; la corrida
del **14/11** la vio por primera vez → desvío nuevo vs la base del 13/11 → alerta.

---

## Comando

```bash
php artisan compras:auditar-factura-anticipada-recepcion --empresa=1 --sin-mail
```
