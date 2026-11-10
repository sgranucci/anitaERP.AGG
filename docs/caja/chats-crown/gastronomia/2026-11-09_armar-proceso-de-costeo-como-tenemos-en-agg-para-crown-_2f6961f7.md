# armar proceso de costeo como tenemos en AGG para crown, pero aca en crown las listas creo que son 1 y el mes

- **Fecha:** 2026-11-09 21:46
- **Chat id:** `2f6961f7-682f-4cba-b318-6a92e8483e0f`
- **Módulos:** gastronomia
- **Origen local:** `agent-transcripts/2f6961f7-682f-4cba-b318-6a92e8483e0f`

> Export redactado para viaje Crown. Sin tool dumps completos; solo diálogo útil.

## Usuario

armar proceso de costeo como tenemos en AGG para crown, pero aca en crown las listas creo que son 1 y el mes

## Asistente

Voy a revisar cómo está armado el costeo en AGG y qué hay hoy en Crown para adaptar el proceso a listas 1 + mes.
Voy a revisar cómo está armado el costeo en AGG y qué hay hoy en Crown para adaptar el proceso a listas 1 + mes.

## Asistente

No encontré “costeo” literal; busco el proceso equivalente en AGG (costos, listas de precios, mes).
No encontré “costeo” literal; busco el proceso equivalente en AGG (costos, listas de precios, mes).

## Asistente

Confirmé el proceso AGG: listas `5000+mes`. Antes de armar Crown, verifico en BD si la lista `1` es de venta o de costo, y cómo están las listas/precios.
Confirmé el proceso AGG: listas `5000+mes`. Antes de armar Crown, verifico en BD si la lista `1` es de venta o de costo, y cómo están las listas/precios.

## Asistente

En Crown las listas no son `5000+mes`: hay `1` (A-PUBLICO), `50` (COSTO) y `101–112` (STD por mes). Confirmo cuál usa el negocio.
En Crown las listas no son `5000+mes`: hay `1` (A-PUBLICO), `50` (COSTO) y `101–112` (STD por mes). Confirmo cuál usa el negocio.

## Asistente

El job AGG ya está corriendo en Crown y **falla todos los días** buscando listas `5000+mes` que acá no existen.

En Crown las listas reales son:

| Código | Nombre | Uso |
|---|---|---|
| **1** | A-PUBLICO | **Venta** del POS (casi todos los PV) |
| **50** | COSTO | **Costo/CMV** (ya tiene precios V… recientes, p.ej. oct/2026) |
| 101–112 | STD Enero…Diciembre | Históricas, casi sin uso reciente |

En AGG el costeo graba en **una lista por mes** (`5006` = junio). En Crown el patrón que ya usan (canjes marketing CMV) es **una sola lista de costo + el mes por `fechavigencia`**.

Antes de armarlo: **¿confirmás lista `50` (COSTO) + mes por vigencia?**

Si grabamos en la `1`, pisamos precios de venta del POS. Si es otra (p.ej. STD `100+mes`), decime cuál.
El job AGG ya está corriendo en Crown y **falla todos los días** buscando listas `5000+mes` que acá no existen.

En Crown las listas reales son:

| Código | Nombre | Uso |
|---|---|---|
| **1** | A-PUBLICO | **Venta** del POS (casi todos los PV) |
| **50** | COSTO | **Costo/CMV** (ya tiene precios V… recientes, p.ej. oct/2026) |
| 101–112 | STD Enero…Diciembre | Históricas, casi sin uso reciente |

En AGG el costeo graba en **una lista por mes** (`5006` = junio). En Crown el patrón que ya usan (canjes marketing CMV) es **una sola lista de costo + el mes por `fechavigencia`**.

Antes de armarlo: **¿confirmás lista `50` (COSTO) + mes por vigencia?**

Si grabamos en la `1`, pisamos precios de venta del POS. Si es otra (p.ej. STD `100+mes`), decime cuál.

## Usuario

el costo aca en crown en lugar de ser 5000 + mes es 100 + mes. La 50 es igual que en agg

## Asistente

Quedó claro: en Crown el costeo mensual es `100 + mes` (101–112), y la `50` sigue igual que en AGG. Adapto el proceso a esa base.
Quedó claro: en Crown el costeo mensual es `100 + mes` (101–112), y la `50` sigue igual que en AGG. Adapto el proceso a esa base.

## Asistente

La resolución ya usa `base + mes`; en Crown alcanza con base `100` y corregir mensajes que aún dicen `5000`.
La resolución ya usa `base + mes`; en Crown alcanza con base `100` y corregir mensajes que aún dicen `5000`.

## Asistente

Actualizo la base a `100` en Crown y hago dinámicos los mensajes que aún dicen `5000+mes`.
Actualizo la base a `100` en Crown y hago dinámicos los mensajes que aún dicen `5000+mes`.

## Asistente

Listo. En Crown el costeo mensual ya apunta a **100 + mes** (nov → lista **111 STD Noviemb.**).

Cambios:
- `.env` / `.env.example`: `GASTRONOMIA_INFORME_GERENTE_COSTO_LISTA_BASE=100` y `VIANDA_COSTO_LISTA_BASE=100`
- Mensajes/docs: “base + mes” (AGG 5000 / Crown 100); la **50 COSTO** no se tocó

Dry-run OK:
- Lista ERP id 20 — código **111**
- **3182** artículos catálogo candidatos

El schedule diario de las 07:00 ya va a usar esta base. ¿Corro el costeo de noviembre (sin Anita, como está en config)?
Listo. En Crown el costeo mensual ya apunta a **100 + mes** (nov → lista **111 STD Noviemb.**).

Cambios:
- `.env` / `.env.example`: `GASTRONOMIA_INFORME_GERENTE_COSTO_LISTA_BASE=100` y `VIANDA_COSTO_LISTA_BASE=100`
- Mensajes/docs: “base + mes” (AGG 5000 / Crown 100); la **50 COSTO** no se tocó

Dry-run OK:
- Lista ERP id 20 — código **111**
- **3182** artículos catálogo candidatos

El schedule diario de las 07:00 ya va a usar esta base. ¿Corro el costeo de noviembre (sin Anita, como está en config)?

## Usuario

correlo

## Asistente

Lanzo el costeo de noviembre a la lista 111 (sin Anita).
Lanzo el costeo de noviembre a la lista 111 (sin Anita).

## Asistente

Falló porque el usuario id `1` no existe en Crown. Busco un usuario válido y reejecuto.
Falló porque el usuario id `1` no existe en Crown. Busco un usuario válido y reejecuto.

## Asistente

Costeo de noviembre corrido.

| Resultado | Cantidad |
|---|---|
| Grabados en lista **111** | **680** |
| Sin fórmula | 1942 |
| Costo incompleto | 518 |
| Costo cero | 42 |

La primera corrida falló porque el usuario id `1` no existe en Crown; quedó `GASTRONOMIA_COSTO_MENSUAL_CATALOGO_USUARIO_ID=2` (sergio) y se reejecutó bien.
Costeo de noviembre corrido.

| Resultado | Cantidad |
|---|---|
| Grabados en lista **111** | **680** |
| Sin fórmula | 1942 |
| Costo incompleto | 518 |
| Costo cero | 42 |

La primera corrida falló porque el usuario id `1` no existe en Crown; quedó `GASTRONOMIA_COSTO_MENSUAL_CATALOGO_USUARIO_ID=2` (sergio) y se reejecutó bien.
