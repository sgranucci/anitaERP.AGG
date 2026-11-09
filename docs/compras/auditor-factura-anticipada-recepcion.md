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

Corte **09/11/2026** · Biyemas (empresa 1).  
Asiento de octubre sin corregir: se documenta y se deja como diferencia ya informada.

| Empresa | OC | Proveedor | Exceso recepción | Estado |
|---|---|---|---:|---|
| Biyemas | **223218** (id 29240) | 5176 ALVAREZ MIRIAM ANALIA | **280.000,00** | Aceptada / ya informada |

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

A partir de la base del 09/11, si el exceso de la OC 223218 sigue en 280.000 el mail
queda en verde (sección “Desvíos ya informados”). Solo alerta si el exceso cambia
o aparece otra OC con recepción > factura anticipada.

---

## Comando

```bash
php artisan compras:auditar-factura-anticipada-recepcion --empresa=1 --sin-mail
```
