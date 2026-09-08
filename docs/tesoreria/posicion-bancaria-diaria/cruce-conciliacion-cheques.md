# Cruce: posición bancaria ↔ conciliación bancaria (cheques)

## Idea clave

Los cheques de la **posición diaria** y los de la **conciliación contable** son el mismo universo **CHP** (cheques propios), cortado con reglas distintas:

| Proceso | Pregunta | Corte |
|---------|----------|-------|
| **Posición** | ¿Cuánto tengo “atado” en cheques y cuándo cae? | Aging vs fecha de posición: retenidos (≤ −30d) / tránsito (−30…hoy) / diferidos (> hoy) |
| **Conciliación** | ¿Qué falta acreditar vs extracto IB? | Pendientes CHP; **carátula** = vencimiento en el **mes de corte** |

No hace falta Anita online en el armado diario: el stock vive en el ERP.

## Fuentes hoy (as-is)

```
Anita cpromae (che_ban)
    ├─ Conciliación (live bridge) → snapshot conciliacion_bancaria_cheque_pendiente
    └─ (histórico) sync parcial → tabla cheque (casi vacía: 1 fila)

Excel Posición (Cheques BSA/KSA/RSA)
    └─ Portfolio operativo tesorería (~380 filas) — NO es el dump completo de cpromae
```

Cuentas canónicas (AGG):

| Empresa | MACRO | ITAU (BMA / ex Itaú) | BIND |
|---------|-------|----------------------|------|
| 1 Biyemas (BSA) | cuentacaja `127` | `1112` | `1118` |
| 2 Kandiko (KSA) | `226` | `2112` | `2118` |
| 3 Rebisco (RSA) | `326` | `3112` | `3118` |

`cpromae` Macro BSA tiene ~47k filas (~45k “abiertas”); el Excel BSA solo ~163. La posición trabaja el **portfolio curado**, no el padrón completo.

## To-be (ERP como dato base)

```
posicion_bancaria_cheque   ← stock vivo CHP (import Excel / altas ERP / sync batch)
        │
        ├─ Posición diaria → aging retenidos/tránsito/diferidos
        │
        └─ Conciliación   → pendientes / carátula (misma clave empresa+cuenta+número)
                              sin llamar Anita en el request online
```

Tabla nueva: `posicion_bancaria_cheque`  
Misma semántica de campos que `conciliacion_bancaria_cheque_pendiente` (tip, numero, fechas, importe, estado, entregado_a…), pero **no** atada a `ejecucion_id`.

## Estado actual (2026-09-07)

- Sync `tesoreria:sync-cheques-cpromae --anio=2026`: **5245** CHP en ERP (Macro/ITAU/BIND + cuentas banco).
- Portfolio Excel previo: ~100 filas pre-2026 (ITAU históricos) conservadas.
- Conciliación `armar('127', …)` → `fuente=erp_cuenta` (sin Anita online).
- Plantilla con cheques: `/home/sergio/tmp/Posicion_Bancos_con_cheques.xlsx`

## Próximos pasos sugeridos

1. Que la plantilla Posición lea cheques desde `posicion_bancaria_cheque` (Fase 2).
2. Que `ConciliacionBancariaPendientesCpromaeSupport` pueda armar pendientes desde ERP stock (Anita solo en job batch `tesoreria:sync-cheques-cpromae`).
3. Al emitir CHP desde caja/pagos ERP, upsert en `posicion_bancaria_cheque` (y en `cheque`).
