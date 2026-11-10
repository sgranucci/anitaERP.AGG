# estoy viendo el POS de gastronomia, como abro una cuenta, veo las mesas pero no las cuentas. 2) la descripcion del item 

- **Fecha:** 2026-11-05 17:47
- **Chat id:** `644d152d-a9d9-442a-8f35-405d6c76a29b`
- **Módulos:** gastronomia
- **Origen local:** `agent-transcripts/644d152d-a9d9-442a-8f35-405d6c76a29b`

> Export redactado para viaje Crown. Sin tool dumps completos; solo diálogo útil.

## Usuario

estoy viendo el POS de gastronomia, como abro una cuenta, veo las mesas pero no las cuentas. 2) la descripcion del item esta muy chica no se ve que se tickea

## Asistente

Voy a revisar el POS de gastronomía para ver cómo se abre una cuenta desde las mesas y por qué la descripción del ítem queda tan chica que no se distingue lo tildado.
Voy a revisar el POS de gastronomía para ver cómo se abre una cuenta desde las mesas y por qué la descripción del ítem queda tan chica que no se distingue lo tildado.

## Asistente

El POS muestra las mesas y esconde las cuentas. Voy a ver cómo se abre una cuenta y dónde se dibuja la descripción del ítem.
El POS muestra las mesas y esconde las cuentas. Voy a ver cómo se abre una cuenta y dónde se dibuja la descripción del ítem.

## Asistente

Hay dos POS de gastronomía. Voy a ubicar el que estás mirando y cómo se abre la cuenta y se tilda el ítem.
Hay dos POS de gastronomía. Voy a ubicar el que estás mirando y cómo se abre la cuenta y se tilda el ítem.

## Asistente

En modo restaurant el panel izquierdo muestra solo las mesas: la cuenta se abre al tocar una mesa libre, y no hay una lista de cuentas. La descripción del ítem queda en una columna angosta y el tilde casi no se distingue. Voy a dejar las cuentas abiertas a la vista y agrandar la descripción y el tilde.
En modo restaurant el panel izquierdo muestra solo las mesas: la cuenta se abre al tocar una mesa libre, y no hay una lista de cuentas. La descripción del ítem queda en una columna angosta y el tilde casi no se distingue. Voy a dejar las cuentas abiertas a la vista y agrandar la descripción y el tilde.

## Asistente

En el POS Crown de gastronomía, con el puesto en modo restaurant, la cuenta no aparece sola: se abre tocando una mesa libre. Recargá la pantalla para ver el cambio.

**Cómo abrir la cuenta**

- Mesa verde o con el cartel **libre**: un toque abre la cuenta. Si el puesto pide cubiertos, primero pregunta cuántos.
- Mesa ocupada: el plano ahora muestra **Cta #** y, debajo, la lista **Cuentas abiertas**. Tocá la mesa o esa fila para seguir la cuenta que ya está abierta.
- El aviso verde del centro pasa a decir `Cuenta #… — mesa`.

El botón **+** sigue solo en sala o comandera, donde no hay mesas. Ahí la lista de la izquierda ya son las cuentas.

**Descripción y tilde**

La descripción del ítem salió de la columna angosta y quedó en una fila propia, más grande. En el detalle, el nombre del ítem y el casillero también son más grandes: la fila tildada queda en amarillo, con una barra naranja a la izquierda. En los opcionales, la opción elegida muestra el tilde y el texto en negrita.

No pude abrir el POS en el navegador de acá (la solapa no llega al servidor). Después de recargar, tocá una mesa libre y fijate que aparezca la cuenta y que el tilde del ítem se lea bien.
En el POS Crown de gastronomía, con el puesto en modo restaurant, la cuenta no aparece sola: se abre tocando una mesa libre. Recargá la pantalla para ver el cambio.

**Cómo abrir la cuenta**

- Mesa verde o con el cartel **libre**: un toque abre la cuenta. Si el puesto pide cubiertos, primero pregunta cuántos.
- Mesa ocupada: el plano ahora muestra **Cta #** y, debajo, la lista **Cuentas abiertas**. Tocá la mesa o esa fila para seguir la cuenta que ya está abierta.
- El aviso verde del centro pasa a decir `Cuenta #… — mesa`.

El botón **+** sigue solo en sala o comandera, donde no hay mesas. Ahí la lista de la izquierda ya son las cuentas.

**Descripción y tilde**

La descripción del ítem salió de la columna angosta y quedó en una fila propia, más grande. En el detalle, el nombre del ítem y el casillero también son más grandes: la fila tildada queda en amarillo, con una barra naranja a la izquierda. En los opcionales, la opción elegida muestra el tilde y el texto en negrita.

No pude abrir el POS en el navegador de acá (la solapa no llega al servidor). Después de recargar, tocá una mesa libre y fijate que aparezca la cuenta y que el tilde del ítem se lea bien.

## Usuario

por que el F1 de consulta de articulos no es el standard? ademas no trae nada si pongo texto 2) que modo tengo que poner para que pida cuentas tambien ademas de mesas?

## Asistente

El F1 está usando un cuadro de texto propio y no el modal de artículos del ERP. Voy a ver cómo está armada esa consulta y qué modo del POS muestra cuentas además de las mesas.
El F1 está usando un cuadro de texto propio y no el modal de artículos del ERP. Voy a ver cómo está armada esa consulta y qué modo del POS muestra cuentas además de las mesas.

## Asistente

El F1 abre un `prompt` del navegador y después busca solo por SKU exacto: por eso un texto como «café» no devuelve nada. Lo paso al modal de artículos del ERP. En los modos, ninguno muestra mesas y cuentas juntas; lo dejo explícito y habilito la cuenta suelta sin sacar las mesas.
El F1 abre un `prompt` del navegador y después busca solo por SKU exacto: por eso un texto como «café» no devuelve nada. Lo paso al modal de artículos del ERP. En los modos, ninguno muestra mesas y cuentas juntas; lo dejo explícito y habilito la cuenta suelta sin sacar las mesas.

## Asistente

El F1 no era el modal de artículos del ERP: abría el cuadro del navegador y buscaba el texto como SKU exacto. Por eso «café» no devolvía nada. Recargá el POS.

**F1.** Ahora F1 y la lupa del código abren la consulta de artículos de siempre. Ahí el texto busca por nombre o código, a partir de 2 caracteres. Elegir carga el SKU y la descripción. Enter en la caja de código sigue agregando ese SKU a la cuenta.

**Modo.** Ningún modo muestra las dos cosas a la vez. Quedate en **Restaurant (mesas)** o **Restaurant solo** si querés el plano.

| Modo | Qué muestra |
|---|---|
| Restaurant (mesas) / Restaurant solo | El plano. Una mesa libre abre la cuenta de esa mesa. |
| Sala / Comandera | Solo la lista de cuentas y el **+**. Las mesas no aparecen. |

En Restaurant el **+** del panel de mesas ahora abre una cuenta sin mesa, y abajo queda la lista **Cuentas abiertas**. Sala saca las mesas.
El F1 no era el modal de artículos del ERP: abría el cuadro del navegador y buscaba el texto como SKU exacto. Por eso «café» no devolvía nada. Recargá el POS.

**F1.** Ahora F1 y la lupa del código abren la consulta de artículos de siempre. Ahí el texto busca por nombre o código, a partir de 2 caracteres. Elegir carga el SKU y la descripción. Enter en la caja de código sigue agregando ese SKU a la cuenta.

**Modo.** Ningún modo muestra las dos cosas a la vez. Quedate en **Restaurant (mesas)** o **Restaurant solo** si querés el plano.

| Modo | Qué muestra |
|---|---|
| Restaurant (mesas) / Restaurant solo | El plano. Una mesa libre abre la cuenta de esa mesa. |
| Sala / Comandera | Solo la lista de cuentas y el **+**. Las mesas no aparecen. |

En Restaurant el **+** del panel de mesas ahora abre una cuenta sin mesa, y abajo queda la lista **Cuentas abiertas**. Sala saca las mesas.

## Usuario

1) el detalle no indica el numero de mesa o numero de cuenta al que pertenece 2) achicar cada linea en el cuadro de detalle porque esta muy alta 3) copiar el manejo del tickeo de articulos del POS de AGG, el manejo de cantidades para agregar o desagregar, los opcionales y lo que veas positivo para este modulo de Crown 4) como abre la cobranza? deberia verse tambien o lo haces en un modal?. 5) el articulo de ventas aca es con 5 digitos en lugar de los 4 de AGG

## Asistente

El detalle no dice de qué mesa o cuenta es, y las filas quedaron altas. Voy a mirar cómo lo resuelve el POS de AGG (tilde, cantidades, opcionales y cobranza) y el largo del código de artículo para traerlo a Crown.
El detalle no dice de qué mesa o cuenta es, y las filas quedaron altas. Voy a mirar cómo lo resuelve el POS de AGG (tilde, cantidades, opcionales y cobranza) y el largo del código de artículo para traerlo a Crown.

## Asistente

El POS de AGG carga el artículo con 4 dígitos, suma y resta la cantidad en la línea y deja la cobranza a la vista. Crown no hace nada de eso: el detalle no dice la mesa y las filas quedaron altas. Lo alineo con ese manejo.
El POS de AGG carga el artículo con 4 dígitos, suma y resta la cantidad en la línea y deja la cobranza a la vista. Crown no hace nada de eso: el detalle no dice la mesa y las filas quedaron altas. Lo alineo con ese manejo.

## Asistente

Recargá el POS. El detalle ahora dice de qué cuenta y mesa es, las filas quedaron bajas, y la carga de artículos sigue el mismo criterio que el de AGG.

**1. Mesa y cuenta.** El título del detalle pasa a `Cuenta #1 — Mesa 10` (código y nombre de la mesa). Sin cuenta abierta queda en «Detalle».

**2. Alto de la fila.** La grilla volvió a filas bajas: menos padding, texto más chico y el tilde en el tamaño de un casillero normal.

**3. Como en AGG.**
- **Enter** en el código agrega cantidad 1.
- La tecla **+** pregunta la cantidad y después agrega.
- En cada ítem, **−** y **+** bajan o suben la cantidad. No baja de 1, y un ítem ya comandado no se toca.
- El opcional elegido se lee debajo del artículo (`+ sin azúcar`).
- El tilde de la fila sigue sirviendo para marcar: F6 borra lo marcado y la marcha puede ir solo con eso.

**4. Cobranza.** No queda siempre en pantalla. F5, F8, F9, Hotel y el botón Cobrar abren un **modal** con la cuenta, la mesa y el total. Ahí se elige Efectivo, Interna / GST, Factura sola u Hotel, y recién eso emite la factura. Crown cobra de un saque; no arma la grilla de varios medios del facturador de AGG.

**5. Cuatro dígitos.** El catálogo es el mismo que AGG: prefijo **V** y **4** dígitos (`123` → `V0123`). La caja ahora pide solo esos 4, y la consulta F1 filtra ese catálogo. Antes el campo aceptaba el código completo y la lupa no filtraba, por eso aparecían artículos de otro largo.
Recargá el POS. El detalle ahora dice de qué cuenta y mesa es, las filas quedaron bajas, y la carga de artículos sigue el mismo criterio que el de AGG.

**1. Mesa y cuenta.** El título del detalle pasa a `Cuenta #1 — Mesa 10` (código y nombre de la mesa). Sin cuenta abierta queda en «Detalle».

**2. Alto de la fila.** La grilla volvió a filas bajas: menos padding, texto más chico y el tilde en el tamaño de un casillero normal.

**3. Como en AGG.**
- **Enter** en el código agrega cantidad 1.
- La tecla **+** pregunta la cantidad y después agrega.
- En cada ítem, **−** y **+** bajan o suben la cantidad. No baja de 1, y un ítem ya comandado no se toca.
- El opcional elegido se lee debajo del artículo (`+ sin azúcar`).
- El tilde de la fila sigue sirviendo para marcar: F6 borra lo marcado y la marcha puede ir solo con eso.

**4. Cobranza.** No queda siempre en pantalla. F5, F8, F9, Hotel y el botón Cobrar abren un **modal** con la cuenta, la mesa y el total. Ahí se elige Efectivo, Interna / GST, Factura sola u Hotel, y recién eso emite la factura. Crown cobra de un saque; no arma la grilla de varios medios del facturador de AGG.

**5. Cuatro dígitos.** El catálogo es el mismo que AGG: prefijo **V** y **4** dígitos (`123` → `V0123`). La caja ahora pide solo esos 4, y la consulta F1 filtra ese catálogo. Antes el campo aceptaba el código completo y la lupa no filtraba, por eso aparecían artículos de otro largo.
