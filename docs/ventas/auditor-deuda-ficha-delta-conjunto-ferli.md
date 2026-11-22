# Auditor deuda vs ficha — delta conjunto clientes (C.Ferli SA)

Bitácora del control diario `ventas:auditar-deuda-ficha-cliente`  
Empresa **1 — C.Ferli SA** · expresión **origen** · tabla `cliente_deuda_mayor_delta`

El número que se controla es el **delta conjunto** = delta clientes + delta anticipos (COA)  
(`mayor − deuda` por cuenta, en pesos, signo del mayor).

---

## Base vigente (aceptada)

| Corte | Delta clientes | Delta anticipos | **Delta conjunto** |
|---|---:|---:|---:|
| **21/11/2026** | 973.070.034,89 | 29.674.917,32 | **1.002.744.952,21** |
| 20/11/2026 | 991.663.983,89 | 11.080.968,32 | **1.002.744.952,21** |

Detalle por cuenta al 21/11/2026 (post-saneamiento):

| Cuenta | Deuda | Mayor | Delta |
|---|---:|---:|---:|
| 113100001 DEUDORES POR VENTAS | −991.889.634,75 | −18.819.599,86 | 973.070.034,89 |
| 211100003 ANTICIPO CLIENTES | 2.694.898,33 | 32.369.815,65 | 29.674.917,32 |

Si el conjunto no se mueve más de ±1,00, el mail queda en verde (ficha OK).

---

## Alerta 21/11/2026 — apps huérfanas NCP→FAC (Aguirre 2516)

| | 20/11/2026 | 21/11 (antes) | 21/11 (después) |
|---|---:|---:|---:|
| Delta conjunto | 1.002.744.952,21 | 1.002.467.196,15 | **1.002.744.952,21** |
| Variación | — | **−277.756,06** | **0,00** |
| Ficha (dif. origen) | 0 | −277.756,06 (1 cliente) | **0** |

### Causa

Cliente **2516 AGUIRRE, DIEGO MIGUEL**. El 20/11 se emitieron NDP que revierten NCP ya aplicadas a FAC. La ND se aplicó solo a la NC; las apps NCP→FAC quedaron huérfanas. Ficha: NC+ND = 0. Deuda: las FAC seguían bajadas → hueco **277.756,06**.

| NCP | NDP | Apps borradas | Importe |
|---|---|---|---:|
| 9478 | 959 | 73117 / 73118 | 103.599,85 |
| 9488 | 960 | 73585 / 73586 | 64.893,21 |
| 9489 | 961 | 73587 / 73588 | 26.353,80 |
| 9490 | 962 | 73589 / 73590 | 26.281,20 |
| 9491 | 963 | 73591 / 73592 | 56.628,00 |
| **Total** | | 10 filas | **277.756,06** |

### Acción

1. **Datos:** borradas las 10 apps (consola; `AUDIT_CONSOLE=false` → sin fila en `audits`).
2. **Código:** `NotaDebitoReversionNotaCreditoSupport::desaplicarNcDeFacturas` + llamada desde `FacturacionService` al emitir ND/DIM/NER contra NC.
3. Auditor `--sin-mail` al 21/11: **OK**, delta conjunto estable vs 20/11.

### Pendiente operativo (Mónica)

Tras el saneamiento reaparecen saldos en FAC por esos importes. Hay **COA 77787** (−264.628,26) de la CIE 78062 para imputar. Ver informe del chat.

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
