# EFE mensual — post-procesos (solapa Datos)

Documento para **contaduría / análisis vs Excel Anita**.

> **Control operativo y bitácora de cambios:** ver también  
> [`efe-mensual-control-contaduria.md`](./efe-mensual-control-contaduria.md)

## Alcance (importante)

| Capa | Qué hace | ¿Toca el mayor por concepto? |
|---|---|---|
| **Mayor por concepto** | Motor Anita (subdiario + ctamov + conceptos) | — fuente de verdad del movimiento |
| **Post-procesos EFE** | Reclasifican / parten / agregan filas **solo** en el array de la solapa Datos del EFE | **No** |

Flujo:

```
Mayor (motor) → filas en memoria → columnas Pagos/Cobros → post-procesos EFE → Datos / Resumen / Sumarias
```

Referencia de contraste: Excel Anita BSA (ej. mayo/2026 empresa 1).  
Script de validación: `php scripts/efe_validar_vs_mayor.php [excel] [empresa] [mes] [anio]`

Clasificación de desvíos Excel vs EFE:

| Clase | Significado |
|---|---|
| `ESPERADO_MOTOR` | Excel Anita ≠ mayor nuevo; el EFE sigue al mayor (post ≈ 0). Tema de motor/contaduría, no del post. |
| `AJUSTE_EFE` | Un post EFE cambia el total respecto del mayor. Revisar regla del post. |
| `BUG_CALCULO` | Inconsistencia interna (Resumen ≠ Datos, Sumarias, etc.). |

---

## Orden de aplicación (Datos)

Implementación: `EfeMensualReporteService::armarFilasDatos`.

1. Clasificación O/P + Pagos/Cobros (`EfeDatosPagosCobrosSupport`)
2. Gaming supplies (12) — `EfeDatosGamingSuppliesSupport`
3. Mantenimiento de edificio (24) — `EfeDatosMantenimientoEdificioSupport`
4. Varios (20) — `EfeDatosVariosSupport`
5. OPP → gasto COM — `EfeDatosOppGastoComSupport` (+ `EfeOppComGastoResolverSupport`)
6. Reimputa anticipo — `EfeDatosReimputaAnticipoSupport`
7. Gastronomía (5) — `EfeDatosGastronomiaSupport`
8. Bienes de uso (2) — `EfeDatosBienesUsoSupport`
9. Excluir IVA OPP en c55 — `EfeDatosExcluirIvaOppGastoSupport`
10. Filtro conceptos 0 / 63 fuera del informe

---

## Detalle de cada post

### 1. Pagos / Cobros
- **Clase:** `EfeDatosPagosCobrosSupport`
- **Qué:** Arma columnas O/P de Datos a partir del mayor (debe/haber → pagos/cobros).
- **No reclasifica conceptos.**

### 2. Gaming supplies (concepto 12)
- **Clase:** `EfeDatosGamingSuppliesSupport`
- **Qué:** Anticipos `114040-001` que el mayor deja en concepto 0 y Anita muestra en 12.
- **Señal:** auxpag / descripción gaming del OPP.

### 3. Mantenimiento de edificio (concepto 24)
- **Clase:** `EfeDatosMantenimientoEdificioSupport`
- **Qué:** OPP/cheques/anticipos de mant. edificio → concepto 24.
- **Señal:** piernas FIB/COM con cuenta cuyo `ctaconc` / `conceptogasto_id` es **24** (p.ej. `521180`), **sin** puente bienes de uso `123010`.
- **No** usa `axp_concepto=24` del FIB (solo IVA/pasivo no va a c24).
- **Nota:** si el FIB/COM trae `123010`, no fuerza 24 (va a bienes de uso vía OPP→COM).
- **No sobrescribe** concepto 12.

### 4. Varios (concepto 20)
- **Clase:** `EfeDatosVariosSupport`
- **Qué:** Cheques `117010` / anticipos `114040` → 20 (o 24 en patrones puntuales).
- **Reglas relevantes (BSA mayo/2026):**
  - FIS/FNS/FNB con concepto 20.
  - Cheque con uno o más FIB concepto 5 **sin** FGA/CIB/FDT gastro → **Varios** (ej. PAPELERA MAYOR). Anita Datos no los trata como gastronomía.
  - Anticipos con TMB + patrones FIB 65/24.

### 5. OPP → gasto COM
- **Clase:** `EfeDatosOppGastoComSupport` / `EfeOppComGastoResolverSupport`
- **Qué:** En cuentas puente (anticipo, cheque, IVAs de anticipo, etc.) toma el concepto/cuenta del COM/factura aplicada (`auxpag` → `aplicped` → subdiario COM).
- **Casos clave:**
  - Puente `123010` (bienes de uso) → concepto **2** (ej. DIEGER), aunque `axp_concepto` diga 24.
  - Concepto **5** solo por `axp_concepto` **sin** cuenta de gasto COM:  
    - **no** pisa concepto 20;  
    - **solo** aplica si el REC tiene FGA/CIB/FDT conc=5 (no alcanza un FIB conc=5 solo).
- **Objetivo vs Excel:** evitar que PAPELERA (FIB conc=5) pase de Varios a Gastronomía.
- **Fallback (2026-07-29):** si no hay cuenta de gasto COM/piernas → `axp_concepto` (≠63). Solo se aplica a filas que siguen en **concepto 0** (no pisa c20/c12). No modifica el mayor por concepto.

### 6. Reimputa anticipo
- **Clase:** `EfeDatosReimputaAnticipoSupport`
- **Qué:** Emula `reimputa_cuentas` del mayor Anita: muestra cuenta de gasto del pago en lugar del anticipo, sin romper clasificaciones 20/24 ya fijadas cuando corresponde.

### 7. Gastronomía (concepto 5)
- **Clase:** `EfeDatosGastronomiaSupport`
- **Qué:**
  1. Cheques `117010-001` con mayor c0 y aplicación gastro (CIB/FGA/CIB patrones).
  2. Split TEORA: pierna chica → c5 `114010-010`; grande → c47.
  3. **Fracción IVA gastro** en pagos FGA conc 20/24 con piernas factura `114010-010` (IVA) + `114040-001` (anticipo):  
     - prorratea retenciones (RTP/RGP) sobre el bruto del pago;  
     - agrega línea c5 con el IVA prorrateado;  
     - deja el anticipo neto en c24;  
     - el resto (ej. FIS neto) puede ir a c20 con cuenta de gasto del COM histórico, o sumarse a otra línea c24 del mismo asiento.  
     - **No duplica el CHP** (antes se copiaba casi todo el cheque a c5).
  4. Líneas TMB (Coca) en `115010-002` cuando aplica.

### 8. Bienes de uso (concepto 2)
- **Clase:** `EfeDatosBienesUsoSupport`
- **Qué:** Completa importes CHP / prorrateo subdiario y pares AGT en `211010-011` que Anita muestra en c2.

### 9. Excluir IVA OPP en concepto 55
- **Clase:** `EfeDatosExcluirIvaOppGastoSupport`
- **Qué:** Si el mismo OPP ya tiene gasto `521xxx` en otro concepto, Anita no deja el IVA crédito `214010` en c55; se excluye esa línea del EFE.

---

## Hallazgos BSA mayo/2026 (empresa 1) — estado de trabajo

Referencia Excel: `Efe Anita BSA 31.05.26.xlsx`.

| Concepto | Tipo desvío | Notas |
|---|---|---|
| C40 / C47 / C49 / C38 / C12 / C45 | `ESPERADO_MOTOR` | Excel Anita ≠ mayor nuevo (ej. prorrateo venta máquinas). EFE no “arregla” el motor. |
| C24 | `AJUSTE_EFE` | Post mant. + OPP→COM (DIEGER→c2). Residual chico vs Excel tras fix bienes de uso. |
| C5 | `AJUSTE_EFE` | Gastro IVA OK vs Excel (~−1.0M post). OPP→COM solo mueve a c5 cheques con FGA/CIB/FDT conc=5 (CLASPACK/GOYAIKE); PAPELERA queda en c20. Δ Excel residual ≈ mayor base + casos puntuales (ej. RED MARQUEZ Excel c13). |
| C13 / C20 / C7 / C17 / C55 | `AJUSTE_EFE` | Pendiente o residual menor. |
| Sumarias E68 | OK | Coincide Excel en la corrida de control. |

### Scripts útiles
- `scripts/efe_validar_vs_mayor.php` — auditoría mayor + consistencia + desvíos clasificados.
- `scripts/efe_auditar_c5.php` — cadena de posts sobre C5.
- `scripts/efe_auditar_opp_c5.php` — líneas que OPP→COM mueve a C5.

---

## Cómo hablarlo con contaduría

1. **Primero** los `ESPERADO_MOTOR`: son diferencias del **mayor nuevo** vs el mayor/EFE histórico de Anita. No se “parchean” en el EFE sin decisión de negocio.
2. **Después** los `AJUSTE_EFE`: son reglas de la solapa Datos que Anita aplica encima del mayor; el ERP las emula en los posts de arriba.
3. Cualquier pedido de “que el EFE coincida con el Excel viejo” en un concepto `ESPERADO_MOTOR` implica **revisar el motor o aceptar la diferencia**, no sumar un post que contradiga el mayor conciliado.
