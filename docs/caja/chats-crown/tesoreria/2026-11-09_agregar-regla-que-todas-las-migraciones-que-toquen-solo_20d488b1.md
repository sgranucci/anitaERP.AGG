# agregar regla que todas las migraciones que toquen solo tablas de crown esten gated

- **Fecha:** 2026-11-09 09:59
- **Chat id:** `20d488b1-31b5-48e5-9bba-76cd6d94dcf2`
- **Módulos:** tesoreria, gastronomia
- **Origen local:** `agent-transcripts/20d488b1-31b5-48e5-9bba-76cd6d94dcf2`

> Export redactado para viaje Crown. Sin tool dumps completos; solo diálogo útil.

## Usuario

agregar regla que todas las migraciones que toquen solo tablas de crown esten gated

## Asistente

Voy a revisar cómo se gatean hoy las migraciones de Crown y la skill de crear reglas, para dejar una regla alineada al patrón del repo.
Voy a revisar cómo se gatean hoy las migraciones de Crown y la skill de crear reglas, para dejar una regla alineada al patrón del repo.

## Asistente

Reviso ejemplos gated y no gated de tablas Crown para acotar bien el alcance de la regla.
Reviso ejemplos gated y no gated de tablas Crown para acotar bien el alcance de la regla.

## Asistente

Creo la regla: migraciones que solo toquen tablas Crown (`tesoreria_crown_*`, `gastro_crown_*`, etc.) deben ir gated con `esCrown()`.
Creo la regla: migraciones que solo toquen tablas Crown (`tesoreria_crown_*`, `gastro_crown_*`, etc.) deben ir gated con `esCrown()`.

## Asistente

Listo: `.cursor/rules/migraciones-crown-tablas-gated.mdc` (`alwaysApply`).

Exige `EntornoEmpresaSupport::esCrown()` en `up()` y `down()` cuando la migración toca **solo** tablas Crown (`tesoreria_crown_*`, `gastro_crown_*`, `cajero_crown*`). No obliga gate en columnas de tablas compartidas con default seguro; menú/rol sigue la regla de entorno que ya existía.
Listo: `.cursor/rules/migraciones-crown-tablas-gated.mdc` (`alwaysApply`).

Exige `EntornoEmpresaSupport::esCrown()` en `up()` y `down()` cuando la migración toca **solo** tablas Crown (`tesoreria_crown_*`, `gastro_crown_*`, `cajero_crown*`). No obliga gate en columnas de tablas compartidas con default seguro; menú/rol sigue la regla de entorno que ya existía.
