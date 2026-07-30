# EFE mensual — control contaduría (bitácora + posts)

Documento operativo para contrastar el EFE ERP vs Excel Anita **sin tocar el motor de mayor por concepto**.

Referencia de prueba: **BSA / empresa 1 / mayo 2026** — `Efe Anita BSA 31.05.26.xlsx`.  
Validación: `php scripts/efe_validar_vs_mayor.php [excel] 1 5 2026`

---

## 1. Principio de trabajo

| Capa | Rol |
|---|---|
| **Mayor por concepto** | Fuente de verdad del movimiento (subdiario + ctamov). **No se modifica** en estos ajustes. |
| **Post-procesos EFE** | Solo mutan el array en memoria de la solapa **Datos** (y por agregación el Resumen). |

Si Excel ≠ mayor y el post EFE ≈ 0 → clase **`ESPERADO_MOTOR`** (hablar con contaduría / motor).  
Si el post cambia el total → clase **`AJUSTE_EFE`** (regla de Datos Anita emulada en ERP).

---

## 2. Orden de posts (Datos)

`EfeMensualReporteService::armarFilasDatos`:

1. Pagos/Cobros — `EfeDatosPagosCobrosSupport`
2. Gaming supplies (12) — `EfeDatosGamingSuppliesSupport`
3. Mant. edificio (24) — `EfeDatosMantenimientoEdificioSupport`
4. Varios (20) — `EfeDatosVariosSupport`
5. OPP → gasto COM — `EfeDatosOppGastoComSupport` + `EfeOppComGastoResolverSupport`
6. Reimputa anticipo — `EfeDatosReimputaAnticipoSupport`
7. Gastronomía (5) — `EfeDatosGastronomiaSupport`
8. Bienes de uso (2) — `EfeDatosBienesUsoSupport`
9. Excluir IVA en c55 — `EfeDatosExcluirIvaOppGastoSupport`

Detalle funcional de cada uno: ver secciones siguientes y el histórico en §4.

---

## 3. Reglas vigentes (resumen para contaduría)

### Gastronomía (5)
- **IVA C. FISCAL GASTRO** (`114010-010`): en FGA conc 20/24 con piernas factura IVA + anticipo `114040`, se **parte** el pago (prorrateo RTP/RGP); no se duplica el CHP.
- Resto FIS puede ir a **c20** (cuenta gasto vía COM histórico) o a otra línea c24 del mismo asiento.

### OPP → COM
- Puente `123010` → **c2** (ej. DIEGER), aunque `axp_concepto` diga 24.
- Concepto del gasto: **`ctaconc` / `cuentacontable.conceptogasto_id`** de la cuenta de la factura (no `axp_concepto`).
- Concepto **5** solo por `axp_concepto` sin cuenta COM: **no pisa c20**; solo aplica si hay **FGA/CIB/FDT** conc=5 (no alcanza un FIB conc=5 solo). → PAPELERA queda en **varios**.

### Varios (20)
- Cheque con FIB conc=5 **sin** FGA/CIB/FDT gastro → **c20** (PAPELERA, etc.).
- Cheque con FIB conc=24 sin cuenta de gasto (solo IVA/pasivo) → **c20** (DI NAPOLI, etc.).
- **No** fuerza c24 por `axp_concepto` del FIB.

### Gaming (12)
- Anticipos `114040` con patrón TMB/FNB o **CHP+FNS conc=24** (ej. ERNESTO MAYER).
- Puede reclasificar desde mayor **0 o 24**; mant. edificio **no pisa** un 12 ya asignado.

### Mant. edificio (24) / cheques con FIB
- **Regla contaduría:** el concepto del cheque sale de la **cuenta contable** asociada (`ctaconc`), no de `axp_concepto`.
- Se marca c24 si alguna pierna FIB/COM tiene cuenta con concepto 24 (p.ej. `521180`) y **sin** puente `123010`.
- FIB solo con IVA `114010` + pasivo (concepto 63) **no** va a c24.

---

## 4. Bitácora de cambios (control)

### 2026-07-15/16 — C24 DIEGER / bienes de uso
| Cambio | Archivos | Efecto vs Excel BSA may/26 |
|---|---|---|
| OPP→COM: si FIB/COM tiene `123010`, destino **c2** (no c24) | `EfeOppComGastoResolverSupport`, `EfeDatosOppGastoComSupport` | C24 dejaba de “comerse” DIEGER (~−80M → ~−3M residual) |

### 2026-07-16 — C5 fracción gastro
| Cambio | Archivos | Efecto |
|---|---|---|
| Split IVA/anticipo FGA (prorrateo retenciones); no copiar CHP a c5 | `EfeDatosGastronomiaSupport`, helper `MayorConceptoAnitaBridgeReader::buscarCuentaGastoComPorPasivo` | C5 Δ Excel ~−13M → ~−8M (antes del fix OPP) |

### 2026-07-16 — C5 / C20 PAPELERA (OPP→COM)
| Cambio | Archivos | Efecto |
|---|---|---|
| No pisar c20→c5 sin cuenta COM; c5 “blando” solo con FGA/CIB/FDT | `EfeDatosOppGastoComSupport`, `EfeOppComGastoResolverSupport` | |
| Varios: FIB conc=5 sin FGA/CIB/FDT → c20 | `EfeDatosVariosSupport` | C5 Δ ~−8M → **~−3.15M**; C20 mejora |

### 2026-07-16 — Gaming vs mant. (MAYER)
| Cambio | Archivos | Efecto esperado |
|---|---|---|
| Gaming puede sacar de c24→c12 si CHP+FNS conc=24 | `EfeDatosGamingSuppliesSupport` | asi 5257120 → c12 |
| Mant. no pisa concepto 12 | `EfeDatosMantenimientoEdificioSupport` | idem |
| OPP→COM no pisa concepto 12 | `EfeDatosOppGastoComSupport` | evita que FNS conc=24 vuelva a c24 |

### 2026-07-17 — Cheques FIB: concepto por cuenta (ctaconc)
| Cambio | Archivos | Efecto esperado |
|---|---|---|
| OPP→COM: concepto desde cuenta (`ctaconc` / piernas factura); no `axp_concepto` | `EfeOppComGastoResolverSupport` | |
| Mant. c24: solo si piernas tienen cuenta con concepto 24 (p.ej. 521180); no por `axp_concepto=24` | `EfeDatosMantenimientoEdificioSupport` | DI NAPOLI/TERZAGHI salen de c24 |
| Varios: ya no fuerza c24 por FIB conc=20 | `EfeDatosVariosSupport` | FARMACIA UOM → c20 |

### 2026-07-29 — Fallback axp_concepto si anticipo queda en c0
| Cambio | Archivos | Efecto esperado |
|---|---|---|
| OPP→COM: si no hay cuenta de gasto (COM/piernas), fallback a `axp_concepto` (≠63) | `EfeOppComGastoResolverSupport` | EXCELL 5257724 FIS conc=24 → c24 |
| Ese fallback **solo** aplica si la fila EFE sigue en concepto **0** (no pisa c20/c12) | `EfeDatosOppGastoComSupport` | DI NAPOLI/PAPELERA ya en varios/gaming intactos |
| **No toca** el motor de mayor por concepto | — | mayor sigue en anticipo c0 |

---

## 5. Estado de desvíos BSA may/26 (validación 2026-07-17, regla cuenta)

Sumarias E68: **OK** · Mayor auditoría: **cuadra**.

| Concepto | Clase | Δ EFE−Excel≈ | Comentario |
|---|---|---|---|
| C40 / C47 / C49 / C38 / C7 / C45 | `ESPERADO_MOTOR` | grandes | Excel Anita ≠ mayor; EFE no inventa. |
| **C24** | `AJUSTE_EFE` | **~+32.6M** | Tras regla cuenta: EFE ≈ mayor (−63M); Excel (−95M) aún clasifica cheques por `axp_concepto`. Ver §6. |
| **C20** | `AJUSTE_EFE` | **~+1.35M** | Mejoró (antes ~+3.5M): cheques FIB sin cuenta conc. 24 pasan a varios. |
| **C5** | `AJUSTE_EFE` | **~−3.15M** | Sin cambio relevante. |
| **C13** | `AJUSTE_EFE` | **~+16.7M** | Mayor corto; post ≈ 0. |
| C12 / C55 / C8 / C17 / C18 | `AJUSTE_EFE` | chicos–medios | Residuales. |

---

## 6. C24 — regla cuenta vs Excel residual

**Regla vigente (contaduría):** cheques con FIB → concepto de la **cuenta contable** (`ctaconc`) cuando existe pierna/COM de gasto.  
**Fallback EFE (2026-07-29):** si el mayor deja el puente/anticipo en **c0** y no hay cuenta de gasto, usar `axp_concepto` del auxpag (solo mientras la fila siga en c0).

| Ejemplo | Excel | ERP | Motivo |
|---|---|---|---|
| DIEGER (`123010`) | c24 | **c2** | Puente bienes de uso gana sobre axp |
| EXCELL / FIS anticipo solo IVA+114040, axp=24 | c24 | **c24** | Fallback axp (mayor c0) |
| DI NAPOLI / TERZAGHI (ya varios) | c20 | **c20** | Fallback no pisa c20 |
| ERNESTO MAYER | c12 | **c12** | Gaming; no pisa c12 |
| BARCA / mant. con `521180` | c24 | **c24** | Concepto por cuenta |

Si BARCA u otros deben quedar en c24 sin `521180` ni `axp_concepto` en factura, contaduría debe indicar **otra señal**.

---

## 7. Pendiente C13 — mayor vs post

- Excel c13 ≈ **−139.8M**
- Mayor → EFE base ≈ **−123.0M** (faltan ~16.8M ya en el motor)
- EFE final ≈ **−126.1M** (posts aportan ~3M)
- Δ Excel−EFE ≈ **−13.7M** → mayormente **alineación mayor**, no un post faltante obvio

Cuentas Excel relevantes: `114040`, `521040-005`, `521210-002`, etc. Incluye asientos tipo `348xxx` de gran importe.

---

## 8. Scripts de control

| Script | Uso |
|---|---|
| `scripts/efe_validar_vs_mayor.php` | Auditoría mayor + consistencia + desvíos clasificados |
| `scripts/efe_auditar_c5.php` | Cadena de posts sobre c5 |
| `scripts/efe_auditar_opp_c5.php` | Qué mueve OPP→COM a c5 |
| `scripts/efe_diff_c24_excel.php` | C24 EFE vs Excel por asiento (requiere `/tmp/excel_c24_by_asi.json`) |
| `scripts/efe_auditar_c24_cadena.php` | Neto c24 por paso (ojo: filtra c0 al inicio; subestima mant.) |

---

## 9. Cómo presentar en reunión

1. Mostrar que **Sumarias** cierran y el **mayor cuadra**.
2. Separar tabla **ESPERADO_MOTOR** (ellos / motor) vs **AJUSTE_EFE** (nosotros / posts Datos).
3. En AJUSTE_EFE, recorrer bitácora §4 y residenciales §6–7.
4. No pedir “que el EFE copie el Excel viejo” en un `ESPERADO_MOTOR` sin aceptar cambiar el mayor.
