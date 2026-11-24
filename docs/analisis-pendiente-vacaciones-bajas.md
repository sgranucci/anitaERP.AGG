# Análisis — bajas (provisoria + definitiva)

Vacaciones gozadas: resuelto (ver historial git / comando `sueldos:importar-plan-vacaciones-anita`).

Este documento detalla la **estrategia** para convivir con las dos formas de baja. Aún no implementado.

---

## 1. Cómo funciona hoy el ERP

| Pieza | Comportamiento |
|-------|----------------|
| `EmpleadoIngresoService::darDeBaja` | Al confirmar: `estado = B` + `fecha_egreso` **al instante** |
| Historia (`empleado_ingreso_sueldos`) | Cierra el período abierto con egreso / tipo `B` |
| Listado empleados (chip Activos) | Filtra por `estado = A` (no mira vigencia) |
| `EmpleadoVigenciaSupport` | Si `estado = B` y `fecha_egreso` futura → sigue “vigente” a esa fecha (poco usado en nómina) |
| Liquidación **mensual / vacaciones / etc.** | Solo `estado = ACTIVO` → un dado de baja **sale de la corrida** aunque el egreso sea a fin de mes |
| Liquidación **final** | `estado = B` o con `fecha_egreso` en el período |
| Sync Anita | Copia `emp_flag_estado` + `emp_fec_egr` tal cual (`B` fuerza baja ERP) |
| UI | Un solo botón “Confirmar baja” (siempre definitiva) |

Estados actuales (`EmpleadoEstados`): `P` alta provisoria · `A` activo · `B` baja.  
`P` **no** se reutiliza para baja (es otra semántica).

## 2. Cómo lo pedían en Anita (baja provisoria)

1. Cargar **fecha de egreso** (y motivo) **sin** marcar baja definitiva.
2. El legajo **sigue activo** (entra en liquidaciones normales) hasta la baja definitiva.
3. En el mes del egreso (baja “parcial” / a mitad o fin de período), al liquidar el sistema **debe avisar**.
4. La baja definitiva es el paso que deja de liquidarlo en corridas normales (equivalente a flag `B` en Anita).

En Anita eso se modela naturalmente como: `emp_fec_egr` cargada + `emp_flag_estado` aún vacío/activo; luego flag `B`.

---

## 3. Objetivo

Que coexistán **dos formas** explícitas en el ERP:

| Modo | Nombre UX | Efecto |
|------|-----------|--------|
| **A — Baja provisoria** (Anita) | “Programar baja” / “Baja provisoria” | Guarda fecha + motivo; **sigue Activo** hasta confirmar definitiva o hasta que se efectivice |
| **B — Baja definitiva** (ERP actual) | “Baja definitiva” | `estado = B` ya; sale de nómina normal; entra en liquidación final |

El usuario elige en la solapa Historia. Se puede pasar de provisoria → definitiva sin re-cargar la fecha.

---

## 4. Modelo de datos propuesto (mínimo)

Sin inventar un 4.º valor en `estado` char(1) si se puede evitar.

### Opción recomendada: Activo + fecha_egreso = provisoria

| Situación | `estado` | `fecha_egreso` | Lectura |
|-----------|----------|----------------|---------|
| Activo normal | `A` | `null` | Vigente |
| **Baja provisoria** | `A` | fecha cargada | Activo con egreso programado |
| **Baja definitiva** | `B` | fecha | Baja |
| Alta provisoria | `P` | `null` | Sin cambios |

Helpers nuevos (p. ej. en `EmpleadoEstados` o `EmpleadoBajaSupport`):

- `esBajaProvisoria($emp)` → `estado === A && fecha_egreso !== null`
- `esBajaDefinitiva($emp)` → `estado === B`
- `tieneEgresoProgramado($emp)` → `fecha_egreso !== null`

### Historia (`empleado_ingreso_sueldos`) — DECIDIDO: registrar todo

| Evento | `tipo_movimiento` | Qué hace |
|--------|-------------------|----------|
| Baja provisoria | `BP` | Alta de fila de historia con `fecha_egreso`, motivo, comentario. Legajo sigue `A` + `fecha_egreso`. **No** cierra el período laboral como baja definitiva. |
| Baja definitiva | `B` | Cierra el período abierto (o confirma la provisoria): `estado = B`. Nueva fila o cierre explícito en historia. |
| Anular provisoria | (opcional `XA` / comentario) | Limpia egreso del legajo; rastro en historia de que se anuló. |
| Reincorporar | `I` | Como hoy |

La grilla de Historia muestra etiqueta legible: “Baja provisoria” / “Baja definitiva” / “Ingreso”.

### Columna opcional

Empezar **sin** `baja_modo` extra: derivar de `estado` + `fecha_egreso`. Historia lleva el detalle con `BP` / `B`.

---

## 5. Flujos UX (solapa Historia) — DECIDIDO: las dos al mismo nivel

```
Activo (sin egreso)
  ├─ [Baja provisoria]     → A + fecha + historia BP + aviso a Lorena
  └─ [Baja definitiva]     → B + fecha + historia B     (ERP actual)
       (ambos botones visibles, mismo peso visual)

Activo con baja provisoria
  ├─ [Anular provisoria]
  └─ [Confirmar baja definitiva] → B (+ historia B); usa fecha ya cargada o permite ajustar

Baja definitiva
  └─ [Reincorporar]       → como hoy
```

Textos:

- Provisoria: “Sigue activo en liquidaciones mensuales hasta que confirmen la baja definitiva. Lorena recibe aviso.”
- Definitiva: “Sale de la nómina mensual; entra en liquidación final del período de egreso.”

Filtros del index:

- **Activos**: `estado = A` (incluye provisorias) — badge “Baja prog. dd/mm/aaaa”.
- Opcional chip: “Con baja programada”.
- **Bajas**: solo `estado = B`.

---

## 6. Liquidación

### 6.1 Corridas no-final (mensual, vacaciones, etc.)

**Incluir** legajos con `estado = A` (también los que tienen `fecha_egreso`).

Reglas de días/proporcional: ya existen variables `empleado.fecha_egreso_n` / FEGR en fórmulas; la provisoria alimenta eso sin sacar al empleado de la corrida.

### 6.2 Aviso de “bajas parciales / programadas”

Al **calcular** (y opcionalmente en preview):

1. Detectar empleados del universo de la corrida con `fecha_egreso` en el período (o ≤ `periodo_hasta`).
2. Mostrar lista: legajo, nombre, fecha egreso, modo (provisoria / definitiva si también hay finales mezclados).
3. Pedir confirmación (“Hay N legajos con egreso en el período…”) antes de seguir, o flash warning post-cálculo con link a la lista.

No bloquear el cálculo; solo **avisar**.

### 6.3 Liquidación final — DECIDIDO

**Acepta provisoria:** alcanza con `fecha_egreso` en el período (esté en `A`+fecha o en `B`).  
El aviso de cálculo lista esos legajos y sugiere confirmar definitiva si aún están provisorios; no bloquea la final.

---

## 7. Efectivización (provisoria → definitiva) — DECIDIDO

| Política | Decisión |
|----------|----------|
| Pasar a definitiva | **Solo manual** (“Confirmar baja definitiva” en el legajo). No hay job que ponga `B` solo. |
| Robot de avisos | **Sí** — módulo aviso (mismo stack que `empleado_alta_provisoria`) para que **Lorena** reciba mail/aviso de bajas provisorias pendientes de confirmar. |

### Robot de avisos (Lorena)

Nuevo tipo de aviso sueldos, p. ej. `empleado_baja_provisoria_pendiente`:

- **Disparo al programar** la baja (evento puntual, como el alta provisoria).
- **Recordatorio periódico** (robot/schedule): legajos con `estado = A` y `fecha_egreso` no nula, sobre todo si `fecha_egreso <= hoy` o cae en el período de liquidación corriente.
- Destinataria inicial: **Lorena** (usuario/mail a configurar en la migración del aviso; ajustable después en Configuración → Módulo aviso).
- Contenido: legajo, nombre, empresa, fecha egreso, motivo, link al legajo / “Confirmar definitiva”.

No efectiviza solo: solo **avisa**.

---

## 8. Sync Anita

| Anita | ERP |
|-------|-----|
| `emp_fec_egr` con valor y flag **no** `B` | Baja **provisoria** (`A` + fecha) |
| flag `B` (+ egreso) | Baja **definitiva** |
| flag vacío y sin egreso | Activo |
| flag `A` | Alta provisoria (`P`) — sin cambios |

Al **exportar/espejar** hacia Anita (si aplica): provisoria → fecha egreso sin flag `B`; definitiva → flag `B`.

Cuidado: el re-sync actual pisa `estado` con el flag Anita; hay que mapear el caso “fecha + sin B” a provisoria y **no** forzar `B`.

---

## 9. Impacto por archivo (cuando implementemos)

| Área | Archivos |
|------|----------|
| Servicio baja | `EmpleadoIngresoService` — `programarBaja` / `confirmarBajaDefinitiva` / anular |
| UI | `form_historia.blade.php` — dos acciones + badge |
| Estados/helpers | `EmpleadoEstados` o `EmpleadoBajaSupport` |
| Liquidación | `LiquidacionCalculadorService::empleados` + aviso en controller/vista de cálculo |
| Listado | chips/badge en workbench empleado; opcional filtro |
| Sync | `Empleado_SueldosRepository` sync Anita egreso/estado |
| Historia | `tipo_movimiento` documentar `B` vs provisoria |
| Aviso Lorena | Migración `modulo_aviso_tipo` `empleado_baja_provisoria_pendiente` + destinatario; envío al programar; schedule/robot de pendientes |
| Docs/manual | guía rápida sueldos |

Migración DB legajo: **ninguna** obligatoria (`A` + `fecha_egreso`). Sí migración de **módulo aviso** (tipo + destinatario Lorena). Confirmar mail de Lorena al implementar.

---

## 10. Criterios de aceptación

1. Baja provisoria: sigue en **Activos**, entra en mensual; historia `BP`; aviso a Lorena.
2. Baja definitiva: sale de mensual; en **Bajas**; historia `B`; entra en final del período.
3. Liquidación **final** acepta provisoria con egreso en el período.
4. De provisoria → definitiva sin perder fecha/motivo; nueva fila/cierre en historia.
5. Anular provisoria: limpia egreso del legajo + rastro en historia.
6. Calcular liquidación con egresos en el período → aviso en pantalla con listado.
7. Robot/recordatorio a Lorena por provisorias pendientes (sobre todo vencidas).
8. Sync Anita: fecha sin flag B ≠ definitiva.
9. Reincorporar solo desde definitiva.
10. En Historia, botones provisoria y definitiva **al mismo nivel**.

---

## 11. Decisiones — CERRADAS (2026-11-24)

| # | Pregunta | Decisión |
|---|----------|----------|
| 1 | Efectivización | **Manual** + **robot de avisos a Lorena** (no auto-pasa a `B`) |
| 2 | Liquidación final | **Acepta provisoria** (fecha en período) |
| 3 | Historia | **Registra todo**: `BP` provisoria y `B` definitiva |
| 4 | UX botones | **Al mismo nivel** (provisoria y definitiva) |

## 12. Implementado (2026-11-24)

- `EmpleadoBajaSupport` + `EmpleadoIngresoService` (programar / definitiva / anular)
- UI Historia: dos botones al mismo nivel; historia `BP` / `B` / `XA`
- Liquidación final acepta provisoria; aviso `mensaje-aviso` al calcular con egresos en período
- Módulo aviso `sueldos.empleado_baja_provisoria` → **lfigueroa** (`lfigueroa@grupoagg.com`, usuario_id 227)
- Robot: `php artisan sueldos:avisar-baja-provisoria` (programado vía `avisos:despachar-programados`)
- Listado: estado muestra “· Baja prog. dd/mm/aaaa” si aplica
- Sync Anita: comentario — flag B = definitiva; fecha sin B = provisoria (estado A)

