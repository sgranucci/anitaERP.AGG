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
- Concepto **5** solo por `axp_concepto` sin cuenta COM: **no pisa c20**; solo aplica si hay **FGA/CIB/FDT** conc=5 (no alcanza un FIB conc=5 solo). → PAPELERA queda en **varios**.

### Varios (20)
- Cheque con FIB conc=5 **sin** FGA/CIB/FDT gastro → **c20** (PAPELERA, etc.).

### Gaming (12)
- Anticipos `114040` con patrón TMB/FNB o **CHP+FNS conc=24** (ej. ERNESTO MAYER).
- Puede reclasificar desde mayor **0 o 24**; mant. edificio **no pisa** un 12 ya asignado.

### Mant. edificio (24)
- FIB/COM conc=24 sin puente bienes de uso; gasto `521180` en subdiario.
- **No sobrescribe** concepto 12.

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

---

## 5. Estado de desvíos BSA may/26 (última validación limpia)

Sumarias E68: **OK** · Mayor auditoría: **cuadra** (en corrida limpia).

| Concepto | Clase | Δ Excel≈ | Comentario para contaduría |
|---|---|---|---|
| C40 / C47 / C49 / C38 / C12 / C45 | `ESPERADO_MOTOR` | grandes | Excel Anita ≠ mayor nuevo; EFE no inventa. **Revisar motor / criterio histórico.** |
| **C5** | `AJUSTE_EFE` | **~−3.15M** | Posts OK (IVA + cheques gastro reales). Queda sobre todo **mayor** (~−1.6M) + casos puntuales. |
| **C20** | `AJUSTE_EFE` | **~+3.5M** | Mejoró con PAPELERA. Falta afinar otros. |
| **C24** | `AJUSTE_EFE` | **~−2.79M** | Ver §6. MAYER (~152k) corregido a c12. Resto: cheques FIB24 en Excel como c20. |
| **C13** | `AJUSTE_EFE` | **~−13.7M** | Mayor ya viene corto (~−16.8M vs Excel); post solo ~−3M. Gran parte es **mayor**, no post. |
| C7 / C17 / C55 / C18 / C65 | `AJUSTE_EFE` | chicos | Residuales; priorizar después de C24/C13. |

---

## 6. Pendiente C24 — decisión contaduría

El residual **~2.9M** son casi todos asientos que el ERP pone en **c24** y el Excel en **c20** (o c12):

| Ejemplo | Excel | ERP (antes fix MAYER) | Auxpag |
|---|---|---|---|
| DI NAPOLI / TERZAGHI / PROYECTOS DEL NORTE (cheques) | **c20** | c24 | FIB **conc=24**, piernas solo IVA `114010-002` + pasivo (sin `521180`) |
| BARCA VINCIGUERRA / LLANO / CARAMUTA (cheques) | **c24** | c24 | Mismo patrón FIB (a veces conc=20 o 24) sin `521180` en la factura |
| FARMACIA UOM | **c20** | c24 | FIB conc=20 |
| ERNESTO MAYER | **c12** | c12 (corregido) | CHP+FNS conc=24 |
| RELD (parte) | c24 + **c55** IIBB | c24 monto cheque completo | Falta partir IIBB `214010` |

**Problema:** con el mismo patrón contable (FIB sin `521180`), Anita Excel a veces deja el cheque en **c24** (BARCA) y a veces en **c20** (DI NAPOLI).  
No hay regla segura solo con `axp_concepto` / piernas sin arriesgar el otro grupo.

**Pedido a contaduría:** criterio explícito (¿lista de proveedores? ¿rubro? ¿otro campo Anita?) para cheques con FIB conc=24 sin cuenta 521180.

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
