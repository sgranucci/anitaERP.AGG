# Auditor deuda vs ficha — delta conjunto (C.Ferli SA)

Bitácora del control diario `compras:auditar-deuda-ficha-proveedor`  
Empresa **1 — C.Ferli SA** · expresión **origen** · tabla `proveedor_deuda_mayor_delta`

El número que se controla es el **delta conjunto** = delta proveedores + delta anticipos  
(`mayor − deuda` por cuenta, en pesos, signo del mayor).

---

## Base vigente (aceptada)

| Corte | Delta proveedores | Delta anticipos | **Delta conjunto** |
|---|---:|---:|---:|
| **04/11/2026** | 124.695.483,51 | 1.941.669,28 | **126.637.152,79** |

Detalle por cuenta al 04/11/2026:

| Cuenta | Deuda | Mayor | Delta |
|---|---:|---:|---:|
| 211100001 PROVEEDORES | 151.378.716,14 | 98.411.544,85 | −52.967.171,29 |
| 211100002 PROVEEDORES DEL EXTERIOR | 0,00 | 177.662.654,80 | 177.662.654,80 |
| 114110001 ANTICIPO PROVEEDORES | −671.463,66 | 1.270.205,62 | 1.941.669,28 |

A partir del 05/11 el auditor debe comparar contra esta base. Si el conjunto no se mueve más de ±1,00, el mail queda en verde (ficha OK).

---

## Salto documentado: 03/11 → 04/11/2026

| | 03/11/2026 | 04/11/2026 | Variación |
|---|---:|---:|---:|
| Delta proveedores | 22.178.459,71 | 124.695.483,51 | +102.517.023,80 |
| Delta anticipos | 1.941.669,28 | 1.941.669,28 | 0,00 |
| **Delta conjunto** | **24.120.128,99** | **126.637.152,79** | **+102.517.023,80** |

Ficha deuda vs ficha: **cuadrada** (0 diferencias). La alerta del mail fue solo por el delta.

### Cuenta que movió el conjunto

Solo **211100002 PROVEEDORES DEL EXTERIOR**:

| | 03/11 | 04/11 | Variación |
|---|---:|---:|---:|
| Deuda | 102.517.023,80 | 0,00 | −102.517.023,80 |
| Mayor | 177.662.654,80 | 177.662.654,80 | 0,00 |
| Delta | 75.145.631,00 | 177.662.654,80 | **+102.517.023,80** |

El mayor no cambió de un día al otro: se reemplazó asiento por asiento. La deuda de CC de esa cuenta pasó a cero.

### Origen operativo (03/11/2026, usuario Adriana)

Se borraron comprobantes FAM de proveedores del exterior (y su cuenta corriente). Los asientos se recrearon en la misma cuenta **sin** `comprobante_proveedor_id` ni fila de CC.

Deuda que tenía la cuenta 211100002 al corte del 03/11 (reconstituida):

| Proveedor | Comprobante borrado | Importe |
|---|---|---:|
| MOULD IND. DE MATRIZEL LTDA | A-1-26136 (cp 31699) | 71.615.858,40 |
| NOVOPE CALZADOS LTDA. | A-26-2026 (cp 31697) | 23.082.420,60 |
| MOON KIN (ASIA) CO., LIMITED | A-1-260702 (cp 31983) | 8.466.036,80 |
| OPA HOFFLLES (resta) | cc 21813 | −647.292,00 |
| **Total = variación del conjunto** | | **102.517.023,80** |

Asientos que quedaron en mayor (sin vínculo a comprobante / CC):

| Asiento | Fecha | Nro | Observación | Monto en 211100002 |
|---|---|---|---|---:|
| 182892 | 25/09/2026 | 427031 | NOVOPE CAL. LTDA - 26042IC05025535V | −23.082.420,60 |
| 183017 | 25/09/2026 | 427157 | MOULD IND. DE MATRIZES LTDA - 26042IC05025536W | −71.615.858,40 |
| 183018 | 23/10/2026 | 427158 | MOON KIN (ASIA) CO. LIMITED - 26001IC04217285U | −8.466.036,80 |
| 182936 | 30/09/2026 | 427076 | CALZADOS HOFFLLES LTDA - 26042IC05025006Y | −24.417.792,00 |

(HOFFLLES además tuvo anulación/pago el mismo día; el neto de mayor en la cuenta no alteró el saldo de cierre 177.662.654,80 entre los dos snapshots.)

### Lectura

- **No es descuadre de ficha.** La deuda (CC) y la ficha siguen alineadas.
- **Sí es un hueco mayor − deuda** en proveedores del exterior: el pasivo contable quedó; la CC de esos FAM no.
- El salto se **acepta como nueva base** al 04/11/2026. No implica corregir el mayor ni reabrir los FAM borrados, salvo decisión operativa aparte (recrear CC / re-vincular asientos).

---

## Referencias

- Comando: `php artisan compras:auditar-deuda-ficha-proveedor`
- Support: `App\Support\Compras\ProveedorDeudaMayorDeltaSupport`
- Mail: `resources/views/mails/compras/proveedor_cuentacorriente_deuda_ficha_auditor.blade.php`
- Cron: diario ~07:50 (`compras.deuda_ficha_auditor`)
