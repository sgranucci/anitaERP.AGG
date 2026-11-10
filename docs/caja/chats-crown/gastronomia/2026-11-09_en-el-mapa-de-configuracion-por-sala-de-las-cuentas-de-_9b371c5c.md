# en el mapa de configuracion por sala de las cuentas de caja, al buscar con F1, en el modal de consulta quedan afuera el 

- **Fecha:** 2026-11-09 20:52
- **Chat id:** `9b371c5c-777c-4e9e-b49a-9a1021faa86c`
- **Módulos:** gastronomia
- **Origen local:** `agent-transcripts/9b371c5c-777c-4e9e-b49a-9a1021faa86c`

> Export redactado para viaje Crown. Sin tool dumps completos; solo diálogo útil.

## Usuario

en el mapa de configuracion por sala de las cuentas de caja, al buscar con F1, en el modal de consulta quedan afuera el boton de mas a la derecha

## Asistente

Voy a revisar el modal de consulta de cuentas de caja en el mapa por sala para ver por qué se corta el botón de la derecha.
Voy a revisar el modal de consulta de cuentas de caja en el mapa por sala para ver por qué se corta el botón de la derecha.

## Asistente

Encontré la pantalla de cuentas por sala; reviso el modal F1 y el markup de acciones.
Encontré la pantalla de cuentas por sala; reviso el modal F1 y el markup de acciones.

## Asistente

El modal tiene muchas columnas y los botones Elegir/Consultar se cortan; comparo con otros modales del ERP que ya lo resolvieron.
El modal tiene muchas columnas y los botones Elegir/Consultar se cortan; comparo con otros modales del ERP que ya lo resolvieron.

## Asistente

El modal de cuentas de caja tiene muchas columnas y sin `table-responsive` el botón **Consultar** se corta. Lo alineo al patrón de otros modales del ERP.
El modal de cuentas de caja tiene muchas columnas y sin `table-responsive` el botón **Consultar** se corta. Lo alineo al patrón de otros modales del ERP.

## Asistente

Para que **Consultar** no se aplaste, fijo un ancho mínimo en la columna Acciones.
Para que **Consultar** no se aplaste, fijo un ancho mínimo en la columna Acciones.

## Asistente

Listo. El modal de consulta de cuentas de caja cortaba el botón **Consultar** porque la tabla era muy ancha y no tenía scroll horizontal.

Ajustes en `includes/caja/modalconsultacuentacaja`:
- Modal más ancho (`max-width: 96%`)
- Tabla dentro de `table-responsive`
- Columna Acciones con ancho mínimo para que entren **Elegir** y **Consultar**

El cambio aplica a todas las pantallas que usan ese modal (incluido el mapa por sala). Recargá y probá F1 de nuevo.
Listo. El modal de consulta de cuentas de caja cortaba el botón **Consultar** porque la tabla era muy ancha y no tenía scroll horizontal.

Ajustes en `includes/caja/modalconsultacuentacaja`:
- Modal más ancho (`max-width: 96%`)
- Tabla dentro de `table-responsive`
- Columna Acciones con ancho mínimo para que entren **Elegir** y **Consultar**

El cambio aplica a todas las pantallas que usan ese modal (incluido el mapa por sala). Recargá y probá F1 de nuevo.
