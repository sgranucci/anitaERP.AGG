# Auditor deuda vs ficha — delta conjunto (C.Ferli SA)

Bitácora del control diario `compras:auditar-deuda-ficha-proveedor`  
Empresa **1 — C.Ferli SA** · expresión **origen** · tabla `proveedor_deuda_mayor_delta`

El número que se controla es el **delta conjunto** = delta proveedores + delta anticipos  
(`mayor − deuda` por cuenta, en pesos, signo del mayor).

---

## Base vigente (aceptada)

| Corte | Delta proveedores | Delta anticipos | **Delta conjunto** |
|---|---:|---:|---:|
| **14/11/2026** | 312.720.614,72 | 1.941.669,28 | **314.662.284,00** |

Detalle por cuenta al 14/11/2026 (tras corregir FAC ANDINA y con los tres despachos de importación):

| Cuenta | Deuda | Mayor | Delta |
|---|---:|---:|---:|
| 211100001 PROVEEDORES | 203.569.131,95 | 150.601.960,66 | −52.967.171,29 |
| 211100002 PROVEEDORES DEL EXTERIOR | 0,00 | 365.687.786,01 | 365.687.786,01 |
| 114110001 ANTICIPO PROVEEDORES | −1.635.853,71 | 307.815,57 | 1.941.669,28 |

Variación del conjunto 13→14 (después de ANDINA): **+175.702.535,00** · solo **211100002**. El delta de proveedores locales y el de anticipos quedan iguales a la base del 13/11. Si el conjunto no se mueve más de ±1,00, el mail queda en verde (ficha OK).

Base anterior documentada: **13/11/2026** = 138.959.749,00.

---

## Salto documentado: 13/11 → 14/11/2026 (despachos importación)

| | 13/11/2026 | 14/11 cron (antes ANDINA) | 14/11 (después ANDINA) |
|---|---:|---:|---:|
| Delta proveedores | 137.018.079,72 | 312.454.414,72 | 312.720.614,72 |
| Delta anticipos | 1.941.669,28 | 1.941.669,28 | 1.941.669,28 |
| **Delta conjunto** | **138.959.749,00** | **314.396.084,00** | **314.662.284,00** |

Variación cron 13→14: **+175.436.335,00** (exterior +175.702.535 y locales −266.200 por FAC ANDINA mal imputada). Tras corregir ANDINA: variación **+175.702.535,00** · solo **211100002**.

Ficha deuda vs ficha: **cuadrada** (0 diferencias). La alerta del mail fue solo por el delta.

### Cuenta que movió el conjunto (aceptada)

Solo **211100002 PROVEEDORES DEL EXTERIOR** — tres asientos de despacho cargados por Adriana el 13/11 **sin** `comprobante_proveedor_id` ni fila de CC (deuda de la cuenta sigue en 0):

| Asiento | Nro | Fecha | Observación | Monto en 211100002 |
|---|---|---|---|---:|
| 184566 | 428718 | 13/11/2026 | CALZADOS HOFFLLES — DESPACHO IC05 030518 S | 19.400.360,00 |
| 184616 | 428769 | 13/11/2026 | MOULD IND.DE MATRIZES LTDA — DESPACHO IC05 030519 T | 148.700.169,60 |
| 184622 | 428775 | 13/11/2026 | FOSHAN ANNA TRADING CO. LIMITED — DESPACHO 26001IC04 228949 G | 7.602.005,40 |
| **Total = variación del conjunto** | | | | **175.702.535,00** |

Contrapartidas: HOFFLLES y MOULD → `115000007 MERCADERIA DE REVENTA FERLI`; FOSHAN → `511000020 CAPELLADAS`.

Mismo patrón que el salto 03→04/11 (FAM exterior): crece el hueco mayor − deuda en proveedores del exterior; la CC no acompaña.

### Corregido aparte: FAC A-1-100 ANDINA (no forma parte de la base)

| | Cron 14/11 | Tras corrección |
|---|---:|---:|
| Delta 211100001 | −53.233.371,29 | −52.967.171,29 (= base 13/11) |
| Variación 001 vs 13/11 | −266.200,00 | 0,00 |

Origen: alta del proveedor **ANDINA** (44) el 13/11 11:05 con `cuentacontable_id = 542` (P-FLETES) en vez de PROVEEDORES. Al contabilizar FAC A-1-100 (cp **32212**, asiento **184508** / nro **428660**, Romina 11:08) el Haber fue a fletes. A las 11:46 corrigieron el maestro (542→209); el asiento quedó mal. OPP **46298** (mariana) ya aplicada → no se pudo descontabilizar.

Corrección (14/11): línea Haber mov **587688** `cuentacontable_id` **542 → 209**; resync `ctamov` Anita (nro interno **118602**). Debe fletes/IVA sin cambio.

### Lectura

- **No es descuadre de ficha.**
- **Sí es un hueco mayor − deuda** nuevo en proveedores del exterior por los tres despachos sin CC.
- El conjunto **314.662.284,00** se **acepta como base vigente** al 14/11/2026.

---

## Corregido: 13/11/2026 (NDR cheque rechazado sin asiento)

| | 12/11/2026 | 13/11 cron (antes) | 13/11 (después) |
|---|---:|---:|---:|
| Delta proveedores | 137.018.079,72 | 136.308.579,72 | 137.018.079,72 |
| Delta anticipos | 1.941.669,28 | 1.941.669,28 | 1.941.669,28 |
| **Delta conjunto** | **138.959.749,00** | **138.250.249,00** | **138.959.749,00** |

Variación cron 12→13: **−709.500,00** · solo **211100001**. Tras armar el asiento: variación **0,00**.

### Origen

Cheque rechazado **91379196** (int. 96928) entregado a **HEAL WATER S.A.** (prov. 870), usuario Adriana (12/11 ~14:05):

1. ND cliente venta **33623** → asiento **184398** (deudores / cheques rechazados / gastos).
2. NDR proveedor cp **32199** (A-0-1773) → CC por 709.500.
3. Tipo compra **NDR** (`tipotransaccion_compra` id **112**) tenía **`asientocontable = N`**: `contabilizar` dejó CC y **no** generó asiento. Deuda +709.500 sin Haber en proveedores.

### Corrección (13/11)

1. `tipotransaccion_compra` id 112: `asientocontable` **N → S** (quedan NDR futuras con asiento).
2. `descontabilizarSinPagos(32199)` + `contabilizar(32199)`.
3. Asiento ERP **184481** (nro 428632) / Anita nro interno **118596**, `SYNC_OK`:

| Cuenta | Monto |
|---|---:|
| 113100002 CHEQUES RECHAZADOS | +700.000,00 |
| 515000001 GASTOS BANCARIOS | +9.500,00 |
| 211100001 PROVEEDORES | −709.500,00 |

Ficha deuda vs ficha: **cuadrada**. La alerta del mail fue solo por el delta.

---

## Transitorio documentado: 05/11/2026 (revertido el 06/11)

| | 04/11/2026 | 05/11/2026 | 06/11/2026 |
|---|---:|---:|---:|
| Delta proveedores | 124.695.483,51 | 124.708.920,88 | 124.695.483,51 |
| Delta anticipos | 1.941.669,28 | 1.940.997,27 | 1.941.669,28 |
| **Delta conjunto** | **126.637.152,79** | **126.649.918,15** | **126.637.152,79** |

Variación 04→05: **+12.765,36** · 05→06: **−12.765,36** (vuelve a la base).  
Ficha deuda vs ficha: **cuadrada** (0 diferencias). La alerta del mail del 06/11 fue solo por el día a día contra el 05/11.

### Por cuenta (variación del delta)

| Cuenta | 04→05 | 05→06 |
|---|---:|---:|
| 211100001 PROVEEDORES | +13.437,37 | −13.437,37 |
| 114110001 ANTICIPO PROVEEDORES | −672,01 | +672,01 |
| 211100002 PROVEEDORES DEL EXTERIOR | 0,00 | 0,00 |

### Origen operativo (Monica)

1. **+13.437,37 en proveedores** — OPP **46116** GO CUOTAS: el 04/11 a la tarde quedó OPA/pago de retención **13.437,37** en CC sin línea en el mayor; el 05/11 09:31 se agregaron Debe anticipos / Haber IIBB CABA (213100017) al asiento 180131. El cron del 05/11 (07:50) midió el hueco a mitad de camino.
2. **−672,01 en anticipos** — corrección de IIBB al reemplazar OPP **46090**: un reemplazo intermedio llevó **148.359,56** en anticipos; el definitivo quedó en **147.687,55** (diferencia **672,01**). El snapshot del 05/11 tomó el intermedio; el del 06/11 ya el corregido.

### Lectura

- **No es descuadre de ficha** ni un salto nuevo del hueco estructural (exterior / FAM).
- El conjunto del 06/11 se **acepta como base vigente** (mismo número que el 04/11).

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
- Support: `App\Support\Compras\ProveedorDeudaMayorDeltaSupport` (anticipos = solo OPA; FA/recepción aparte)
- Mail: `resources/views/mails/compras/proveedor_cuentacorriente_deuda_ficha_auditor.blade.php`
- Cron: diario ~07:50 (`compras.deuda_ficha_auditor`)
- FA vs recepción: `php artisan compras:auditar-factura-anticipada-recepcion`
