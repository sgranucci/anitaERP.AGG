# Gaps conocidos — reportes definibles sueldos

Tras smoke 2026-08-16 (empresa 1, liquidación `20260700` / id=1):

## Paridad Anita

| Ítem | Estado |
|---|---|
| Import `listmae/listcol/listcon` | OK — 95 listados + 2 plantillas |
| `lism_tipo_list` en datos AGG | Casi todos llegan como genérico (dato Anita); filtro OS/sindicato por `asociado` sigue disponible |
| Runtime sobre ERP (no auxhist) | OK — listados 3, 7, 16, 9001 ejecutan |
| Totales vs impresión Anita | No cerrado línea a línea; usar `sueldos:paridad-reporte-definible` + comparación manual |
| Conceptos ganancias (`concgmov`) | Columna tipo existe; motor aún no acumula ganancias (fase 2 parcial) |
| Consolidación multiempresa | No portada de Anita |
| Filtro lugares múltiples | API lista en filtros; UI aún sin multi-select |

## Premium (entregado vs pendiente)

| Feature | Estado |
|---|---|
| Fórmulas `C1+C2` | OK |
| Drill celda → detalle | OK |
| Variación 2 liquidaciones (Δ) | OK |
| Versiones / restaurar | OK |
| ACL por informe | OK |
| Suscripciones (alta/baja) | OK — distribución email cron pendiente |
| Manual usuario | Pantalla + README |

## Smoke ejecutado

- `#3 OBRA SOCIAL` — 275 filas
- `#7 SINDICATOS` — 287 filas / 27 cols
- `#16 INGRESOS Y EGRESOS` — 287 filas (solo campos)
- `#9001` plantilla neto — 287 filas, col neto ≈ $431.4M
- Drill, fórmula, Δ, versión — OK
