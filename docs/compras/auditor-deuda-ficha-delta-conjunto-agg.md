# Auditor deuda vs ficha — delta conjunto (AGG)

Bitácora del control diario `compras:auditar-deuda-ficha-proveedor`  
Empresas **1 Biyemas, 2 Kandiko, 3 Rebisco** · expresión **origen** · tabla `proveedor_deuda_mayor_delta`

El número que se controla es el **delta conjunto** = delta proveedores + delta anticipos (OPA)  
(`mayor − deuda` por cuenta, en pesos, signo del mayor).

Desde 06/11/2026 el delta de anticipos **solo mira OPA** (mayor sin facturas anticipadas ni recepciones).  
Factura anticipada vs recepción: `compras:auditar-factura-anticipada-recepcion`.

---

## Base vigente (aceptada)

Corte **08/11/2026** · anticipos **solo OPA** (sin factura anticipada / recepción).  
Ficha deuda vs ficha cuadrada en las tres (0 diferencias).  
A partir del 09/11 el auditor compara contra esta base. Si el conjunto no se mueve más de ±1,00, el mail queda en verde.

| Empresa | Delta proveedores | Delta anticipos (OPA) | **Delta conjunto** |
|---|---:|---:|---:|
| Biyemas | 407.510.558,72 | 437.268.014,54 | **844.778.573,26** |
| Kandiko | −161.877.377,17 | 6.680.138,63 | **−155.197.238,54** |
| Rebisco | −297.971.512,89 | 38.874.018,07 | **−259.097.494,82** |

### Biyemas — cuentas al 08/11/2026

| Cuenta | Deuda | Mayor | Delta |
|---|---:|---:|---:|
| 211010001 PROVEEDORES MONEDA NACIONAL | −40.081.767,16 | 240.255.129,04 | 280.336.896,20 |
| 211010011 PROVEEDORES MONEDA EXTRANJERA | 3.321.172.908,64 | 2.983.235.585,50 | −337.937.323,14 |
| 216010003 DS. FINANC. REBISCO SA. | 583.254,23 | 513.877,69 | −69.376,54 |
| 216030002 DS. COMER. INTERCO KAN CTE | 205.396,08 | 465.385.758,28 | 465.180.362,20 |
| 114040001 ANTICIPOS A PROVEEDORES (OPA) | −86.530.179,99 | 350.737.834,55 | 437.268.014,54 |

### Kandiko — cuentas al 08/11/2026

| Cuenta | Deuda | Mayor | Delta |
|---|---:|---:|---:|
| 211010001 PROVEEDORES MONEDA NACIONAL | 43.458.091,79 | 42.963.807,36 | −494.284,43 |
| 211010011 PROVEEDORES MONEDA EXTRANJERA | 1.023.672.927,28 | 862.289.834,24 | −161.383.093,04 |
| 221010004 PROVEEDORES MON EXTR. NO CTES | 0,00 | 0,30 | 0,30 |
| 114040001 ANTICIPOS A PROVEEDORES (OPA) | −1.860.550,00 | 4.819.588,63 | 6.680.138,63 |

### Rebisco — cuentas al 08/11/2026

| Cuenta | Deuda | Mayor | Delta |
|---|---:|---:|---:|
| 211010001 PROVEEDORES MONEDA NACIONAL | 88.765.679,69 | 79.845.798,60 | −8.919.881,09 |
| 211010011 PROVEEDORES MONEDA EXTRANJERA | 1.679.681.914,90 | 1.390.630.283,10 | −289.051.631,80 |
| 114040001 ANTICIPOS A PROVEEDORES (OPA) | −81.754.650,23 | −42.880.632,16 | 38.874.018,07 |

---

## Salto documentado: 07/11 → 08/11/2026

La alerta del mail fue solo por el delta de Biyemas. La ficha siguió en cero. Kandiko y Rebisco no se movieron.

| Empresa | Conjunto 07/11 | Conjunto 08/11 | Variación |
|---|---:|---:|---:|
| Biyemas | 843.860.273,26 | 844.778.573,26 | **+918.300,00** |
| Kandiko | −155.197.238,54 | −155.197.238,54 | 0,00 |
| Rebisco | −259.097.494,82 | −259.097.494,82 | 0,00 |

En Biyemas solo se movió **114040001 ANTICIPOS A PROVEEDORES (OPA)** (+918.300,00). Proveedores delta igual. Deuda OPA igual (−86.530.179,99); el mayor OPA-only subió de 349.819.534,55 a 350.737.834,55.

### Origen

El 07/11 a las 09:38 se cargó/vinculó en anitaERP el comprobante **FIB A-1-784** (cp 35425, proveedor 3925 Roldán Gabriela Verónica, OC 9853) al asiento Anita `#5158177` (subhist, fecha 02/01/2025). Audit del asiento: `comprobante_proveedor_id` null → 35425 (y `ordencompra_id` → 9853).

Ese asiento tiene Debe 918.300 en 114040001. Sin `comprobante_proveedor_id` entraba en el mayor OPA-only del auditor. Al vincularlo sale de ese mayor (factura anticipada → auditor `compras:auditar-factura-anticipada-recepcion`). La deuda OPA no cambió; el Debe dejó de contarse en anticipos OPA y el delta subió exactamente 918.300.

### Lectura

- **No es descuadre de ficha.** Deuda y ficha en cero en las tres empresas.
- El salto es el vínculo de la FIB al comprobante ERP, coherente con el criterio “anticipos solo OPA”.
- Se **acepta como nueva base** al 08/11/2026.

---

## Salto documentado: 04/11 → 05/11/2026

La alerta del mail fue solo por el delta. La ficha siguió en cero.

| Empresa | Conjunto 04/11 | Conjunto 05/11 | Variación |
|---|---:|---:|---:|
| Biyemas | 474.903.343,53 | 607.695.135,74 | **+132.791.792,21** |
| Kandiko | −183.512.786,54 | −154.049.924,80 | **+29.462.861,74** |
| Rebisco | −347.438.971,93 | −298.245.109,94 | **+49.193.861,99** |

En Biyemas la variación se parte en proveedores +132.776.596,06 y anticipos +15.196,15. En Kandiko y Rebisco los anticipos no se movieron.

### Cuenta que movió el conjunto

Solo **211010001 PROVEEDORES MONEDA NACIONAL**. Moneda extranjera, intercompany y el resto quedaron con el mismo delta (deuda y mayor se corrieron juntos).

| Empresa | Delta 04/11 | Delta 05/11 | Variación |
|---|---:|---:|---:|
| Biyemas | 147.560.300,14 | 280.336.896,20 | +132.776.596,06 |
| Kandiko | −29.957.146,17 | −494.284,43 | +29.462.861,74 |
| Rebisco | −58.113.743,08 | −8.919.881,09 | +49.193.861,99 |

### Origen

Un asiento CONT por empresa, fecha 31/10/2026, observación «Reverso Asiento Total 31.12.2025. Aplicados con la Cta Cte.» (en Kandiko y Rebisco: «Total Coin»). Haber en 211010001, sin movimiento de la deuda abierta. Entró al mayor entre el corte del 04/11 07:50 y el del 05/11 07:50.

| Empresa | Asiento | Haber en 211010001 |
|---|---|---:|
| Biyemas | CONT 369339 | 132.776.596,06 |
| Kandiko | CONT 233171 | 29.462.861,74 |
| Rebisco | CONT 238475 | 49.193.861,99 |

Las facturas (COM) y las órdenes de pago (TES) del 04/11 movieron el mayor y la deuda por el mismo importe, así que no entran en la variación del conjunto.

En anticipos de Biyemas el mayor y la deuda se corrieron ~4,65 millones y dejaron +15.196,15 de delta. Ese residuo queda dentro de la base nueva.

### Lectura

- **No es descuadre de ficha.** Deuda y ficha siguen en cero en las tres empresas.
- El salto es el reverso del asiento total al 31/12/2025, que acreditó proveedores en el mayor y no en la cuenta corriente.
- Se **acepta como nueva base** al 05/11/2026. No implica revertir esos asientos ni ajustar la ficha.

---

## Cambio de criterio: anticipos solo OPA (06/11/2026)

Antes el delta de anticipos sumaba OPA + neto de facturas anticipadas (con tope de recepción).  
Eso hizo saltar el conjunto el 06/11 a la mañana (−105.196,15) por el exceso de recepción de la OC 223491 (Bordes) al entrar dos FGA nuevas.

Se cambió el criterio y se **aceptó como base** el corte 06/11 con anticipos solo OPA:
- **Delta conjunto**: proveedores (facturas/NC/OPA) + anticipos **solo OPA**.
- **Factura anticipada vs recepción**: auditor aparte; alerta si recepción > factura por OC.

Variación del rebase 05/11 → 06/11 (solo cambio de criterio en anticipos; proveedores delta igual):

| Empresa | Conjunto 05/11 (viejo) | Conjunto 06/11 (OPA) | Variación |
|---|---:|---:|---:|
| Biyemas | 607.695.135,74 | 843.860.273,26 | +236.165.137,52 |
| Kandiko | −154.049.924,80 | −155.197.238,54 | −1.147.313,74 |
| Rebisco | −298.245.109,94 | −259.097.494,82 | +39.147.615,12 |

## Referencias

- Comando: `php artisan compras:auditar-deuda-ficha-proveedor`
- Support: `App\Support\Compras\ProveedorDeudaMayorDeltaSupport`
- Mail: `resources/views/mails/compras/proveedor_cuentacorriente_deuda_ficha_auditor.blade.php`
- Cron: diario ~07:50 (`compras.deuda_ficha_auditor`)
- FA vs recepción: `php artisan compras:auditar-factura-anticipada-recepcion` (~07:52)
