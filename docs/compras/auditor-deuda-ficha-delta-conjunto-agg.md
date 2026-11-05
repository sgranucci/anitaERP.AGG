# Auditor deuda vs ficha — delta conjunto (AGG)

Bitácora del control diario `compras:auditar-deuda-ficha-proveedor`  
Empresas **1 Biyemas, 2 Kandiko, 3 Rebisco** · expresión **origen** · tabla `proveedor_deuda_mayor_delta`

El número que se controla es el **delta conjunto** = delta proveedores + delta anticipos  
(`mayor − deuda` por cuenta, en pesos, signo del mayor).

---

## Base vigente (aceptada)

Corte **05/11/2026**. Ficha deuda vs ficha cuadrada en las tres (0 diferencias).  
A partir del 06/11 el auditor compara contra esta base. Si el conjunto no se mueve más de ±1,00, el mail queda en verde.

| Empresa | Delta proveedores | Delta anticipos | **Delta conjunto** |
|---|---:|---:|---:|
| Biyemas | 407.510.558,72 | 200.184.577,02 | **607.695.135,74** |
| Kandiko | −161.877.377,17 | 7.827.452,37 | **−154.049.924,80** |
| Rebisco | −297.971.512,89 | −273.597,05 | **−298.245.109,94** |

### Biyemas — cuentas al 05/11/2026

| Cuenta | Deuda | Mayor | Delta |
|---|---:|---:|---:|
| 211010001 PROVEEDORES MONEDA NACIONAL | −60.290.231,22 | 220.046.664,98 | 280.336.896,20 |
| 211010011 PROVEEDORES MONEDA EXTRANJERA | 3.250.186.915,41 | 2.912.249.592,27 | −337.937.323,14 |
| 216010003 DS. FINANC. REBISCO SA. | 583.254,23 | 513.877,69 | −69.376,54 |
| 216030002 DS. COMER. INTERCO KAN CTE | 205.396,08 | 465.385.758,28 | 465.180.362,20 |
| 114040001 ANTICIPOS A PROVEEDORES | −473.589.511,71 | −273.404.934,69 | 200.184.577,02 |

### Kandiko — cuentas al 05/11/2026

| Cuenta | Deuda | Mayor | Delta |
|---|---:|---:|---:|
| 211010001 PROVEEDORES MONEDA NACIONAL | 36.804.923,38 | 36.310.638,95 | −494.284,43 |
| 211010011 PROVEEDORES MONEDA EXTRANJERA | 2.657.006.269,59 | 2.495.623.176,55 | −161.383.093,04 |
| 221010004 PROVEEDORES MON EXTR. NO CTES | 0,00 | 0,30 | 0,30 |
| 114040001 ANTICIPOS A PROVEEDORES | −27.995.082,96 | −20.167.630,59 | 7.827.452,37 |

### Rebisco — cuentas al 05/11/2026

| Cuenta | Deuda | Mayor | Delta |
|---|---:|---:|---:|
| 211010001 PROVEEDORES MONEDA NACIONAL | 70.016.648,13 | 61.096.767,04 | −8.919.881,09 |
| 211010011 PROVEEDORES MONEDA EXTRANJERA | 1.670.867.520,60 | 1.381.815.888,80 | −289.051.631,80 |
| 114040001 ANTICIPOS A PROVEEDORES | −99.792.339,15 | −100.065.936,20 | −273.597,05 |

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

## Referencias

- Comando: `php artisan compras:auditar-deuda-ficha-proveedor`
- Support: `App\Support\Compras\ProveedorDeudaMayorDeltaSupport`
- Mail: `resources/views/mails/compras/proveedor_cuentacorriente_deuda_ficha_auditor.blade.php`
- Cron: diario ~07:50 (`compras.deuda_ficha_auditor`)
