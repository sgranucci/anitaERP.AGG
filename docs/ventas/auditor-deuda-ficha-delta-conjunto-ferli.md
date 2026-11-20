# Auditor deuda vs ficha — delta conjunto clientes (C.Ferli SA)

Bitácora del control diario `ventas:auditar-deuda-ficha-cliente`  
Empresa **1 — C.Ferli SA** · expresión **origen** · tabla `cliente_deuda_mayor_delta`

El número que se controla es el **delta conjunto** = delta clientes + delta anticipos (COA)  
(`mayor − deuda` por cuenta, en pesos, signo del mayor).

---

## Base vigente (aceptada)

| Corte | Delta clientes | Delta anticipos | **Delta conjunto** |
|---|---:|---:|---:|
| **20/11/2026** | 991.663.983,89 | 11.080.968,32 | **1.002.744.952,21** |

Detalle por cuenta al 20/11/2026:

| Cuenta | Deuda | Mayor | Delta |
|---|---:|---:|---:|
| 113100001 DEUDORES POR VENTAS | −990.822.802,48 | 841.181,41 | 991.663.983,89 |
| 211100003 ANTICIPO CLIENTES | 19.107.407,07 | 30.188.375,39 | 11.080.968,32 |

Si el conjunto no se mueve más de ±1,00, el mail queda en verde (ficha OK).

---

## Alerta 20/11/2026 — salto mayor-only en DEUDORES

| | 19/11/2026 | 20/11/2026 | Variación |
|---|---:|---:|---:|
| Delta clientes | 992.590.283,89 | 991.663.983,89 | **−926.300,00** |
| Delta anticipos | 11.080.968,32 | 11.080.968,32 | 0,00 |
| **Delta conjunto** | **1.003.671.252,21** | **1.002.744.952,21** | **−926.300,00** |

Ficha deuda vs ficha: **cuadrada** (0 diferencias). La alerta del mail fue solo por el delta.

### Cuenta que movió el conjunto

Solo **113100001 DEUDORES POR VENTAS** — hueco solo en mayor (−926.300) sin el mismo movimiento firmado en la CC abierta. Anticipos: deuda y mayor subieron +10.920; el delta de anticipos no cambió.

### Origen

No se halló asiento ni audit Eloquent del importe exacto 926.300. La actividad del 19/11 (FAC + cobranzas) empareja deuda ↔ mayor. Mismo patrón ruidoso de días previos (+184.250 el 18/11, +1.738.500 el 19/11) con ficha siempre OK.

### Lectura

- **No es descuadre de ficha.**
- **Sí es un salto de delta** (mayor-only en deudores) sin causa puntual documentada.
- El conjunto **1.002.744.952,21** se **acepta como base vigente** al 20/11/2026.

---

## Historial reciente (pre-base)

| Corte | Delta clientes | Delta anticipos | Delta conjunto | Var. vs día ant. |
|---|---:|---:|---:|---:|
| 17/11/2026 | 990.667.533,93 | 11.080.968,32 | 1.001.748.502,25 | — |
| 18/11/2026 | 990.851.783,91 | 11.080.968,32 | 1.001.932.752,23 | +184.250,00 |
| 19/11/2026 | 992.590.283,89 | 11.080.968,32 | 1.003.671.252,21 | +1.738.500,00 |
| **20/11/2026** | **991.663.983,89** | **11.080.968,32** | **1.002.744.952,21** | **−926.300,00** |

---

## Referencias

- Comando: `php artisan ventas:auditar-deuda-ficha-cliente`
- Support: `App\Support\Ventas\ClienteDeudaMayorDeltaSupport`
- Mail: `resources/views/mails/ventas/cliente_cuentacorriente_deuda_ficha_auditor.blade.php`
- Cron: diario ~07:55 (`ventas.deuda_ficha_auditor`)
- Bitácora compras (mismo cliente): `docs/compras/auditor-deuda-ficha-delta-conjunto-ferli.md`
