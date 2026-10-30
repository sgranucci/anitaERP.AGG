# Tesorería Crown — circuito de caja

Manual de operación de **Tesorería Crown** en anitaERP. Vale para Crown y para cada sala de juego por separado: Cipolletti, Catriel, General Roca, Choele Choel, Villa Regina, Viedma, Las Grutas, El Faro y Cinco Saltos. Cada sala tiene su propia jornada, sus cajeros, sus cotizaciones y su numeración. Una sala no mezcla comprobantes con otra.

El menú es **Tesorería Crown**. La operación del día está en **Jornada**. El listado de habilitaciones y rendiciones está en **Habilitaciones**.

## Qué recorre el día

1. Se abre la jornada de la sala y la fecha.
2. Se asigna cada caja: turno, cajero y tarea.
3. Tesorería habilita valores. El cajero los recibe y firma.
4. El cajero rinde. Tesorería recibe y firma.
5. Se cierra la caja. Ahí se ve la diferencia.
6. Cuando todas las cajas con movimiento tienen un cierre terminado, se cierra la jornada.

Una misma caja puede tener varias habilitaciones y varias rendiciones en el día. Cada una lleva su número y sus dos firmas.

## Numeración

Hay una secuencia por sala para las habilitaciones y otra para las rendiciones. El número se ve con ocho dígitos. La reversa de una habilitación consume el siguiente número de habilitaciones. La reversa de una rendición consume el siguiente de rendiciones. El cierre de caja tiene su propia secuencia, también por sala.

El número se asigna al crear el comprobante, antes de cargar las cantidades. Un borrador ya numerado se puede seguir editando mientras la jornada esté abierta y la caja no esté cerrada.

## La jornada

La jornada es el día de una sala. Estados:

| Estado | Qué se puede hacer |
|---|---|
| Abierta | Asignar cajas, habilitar, rendir, firmar, revertir y cerrar cada caja. |
| Cerrada | Consultar. Los comprobantes y los cierres quedan como estaban. |

Para cerrar la jornada, cada caja que tenga al menos un comprobante tiene que tener un cierre terminado: **conforme**, **revisada** o **cierre de auditoría**. Una caja asignada sin ningún comprobante no frena el cierre del día. Un cierre en **a revisión** o en **auditoría** sí lo frena.

## La caja del día

Al asignar se elige la caja, el cajero, el turno (mañana, tarde o noche) y la tarea:

| Tarea | Para qué se usa |
|---|---|
| Venta y pago | Caja de sala. |
| Gastronomía | Abre los datos de X, notas de crédito e IVA. |
| Hotel | Igual que gastronomía, y además entra la cuenta corriente del hotel en el resultado. |
| Kiosco | Caja de kiosco. |
| Empleados | Caja de empleados. |
| Cashless | Caja cashless. |

La tarea se elige al abrir la caja y no cambia. Gastronomía y hotel son las únicas que piden el informe X en la rendición. El bloque de cashless, tarjetas, apuestas, online, vouchers y QR se carga en todas las rendiciones.

Quitar la asignación solo se puede si todavía no tiene comprobantes y la jornada está abierta.

## Habilitación y rendición

La habilitación es lo que tesorería entrega. La rendición es lo que el cajero devuelve.

La rendición pide una habilitación conforme de esa misma caja, que no haya sido revertida. Puede haber más de una habilitación y más de una rendición.

### Qué se carga

En la habilitación se cargan cantidades de pesos, dólares y euros. Las fichas no entran en la habilitación.

En la rendición se cargan las mismas monedas y, además, las fichas. Después van los medios: cashless, apuestas, online, débito, crédito, recargo, vouchers y QR. Si la caja se abrió como gastronomía u hotel, la rendición muestra los cierres de ventas pendientes de esa fecha. Salen de `p-cierrefact` (tabla `cierrefiscal`), no de `p-vtagastro`. El Z es el cierre fiscal de una barra. El X es el informe que la rendición de hotel toma. Se marcan los que corresponden a esa caja y **Aplicar a la rendición** completa el X, el Z, las notas de crédito, el IVA y el último ticket. Esos importes se pueden corregir antes de guardar. El cierre de Anita no se modifica. En hotel, además, se cargan la cuenta corriente y la transferencia.

Los dólares y los euros quedan en su moneda en el comprobante. El cierre los pasa a pesos con la cotización de venta de esa jornada. Pesos y fichas de cotización fija entran por su valor. Si falta la cotización de una moneda usada, la caja no se puede cerrar hasta cargarla. Una cotización en cero no se usa.

### Estados del comprobante

| Estado | Significado | Qué sigue |
|---|---|---|
| Borrador | Cantidades y medios editables. | Guardar, o pedir firmas. |
| Pendiente de firma | Cantidades congeladas. | El cajero firma con número y PIN. Tesorería firma con usuario y clave. O vuelve a borrador. |
| Conforme | Firmaron los dos lados. | Se puede imprimir y, mientras la caja siga abierta, revertir una sola vez. |
| Auditoría | Alguien lo marcó no conforme, con un motivo. | No suma en el cierre. No reemplaza a una rendición conforme. |

Pedir firmas graba primero lo que está en pantalla y deja el comprobante pendiente. Si todavía no firmó nadie, **Modificar cantidades** lo devuelve a borrador. Si ya hay una firma, **Volver a borrador** borra las firmas. Si cambian las cantidades, hay que pedir firmas de nuevo.

El botón **No conforme** está en el borrador y en el pendiente. Pide un motivo y deja el comprobante en auditoría. Ese comprobante deja de sumar. Para cerrar la caja tiene que existir otra rendición conforme.

### Firmas

Cada comprobante tiene dos lados y los firman dos personas distintas.

| Comprobante | Quién entrega | Quién recibe |
|---|---|---|
| Habilitación | Tesorería, con usuario y clave del sistema. | El cajero, con su número y su PIN. |
| Rendición | El cajero, con su número y su PIN. | Tesorería, con usuario y clave del sistema. |

Las dos firmas se hacen en la misma pantalla, con el teclado. El cajero tipea el número de cajero asignado a esa caja y su PIN. El PIN inicial es ese mismo número. Tesorería firma con un usuario del sistema que tenga permiso para editar movimientos de Tesorería Crown. Ese usuario no puede ser el usuario vinculado al cajero de esa caja.

Cuando firman los dos, el comprobante pasa a conforme. Desde la pantalla se imprime el PDF del comprobante.

## Cierre de la caja

El cierre no es una rendición. Es el control de esa caja: qué se le dio, qué devolvió y cuál es la diferencia.

En la jornada, la columna **Cierre** abre la pantalla. Para poder cerrar tienen que cumplirse las tres cosas:

- No queda ningún comprobante de esa caja en borrador o pendiente de firma. Una reversa sin firmar también frena el cierre.
- Hay al menos una rendición conforme.
- Hay cotización para cada moneda extranjera usada.

Al cerrar firman los dos en el momento: tesorería con usuario y clave, y el cajero con su clave. El resultado que eligen es **conforme** o **a revisión**.

### Cómo se calcula la diferencia

Todo el cierre se expresa en pesos. Entran solo los comprobantes conformes. Una reversa conforme resta, porque sus cantidades y sus medios van al revés.

El efectivo de cada comprobante es pesos, más dólares y euros por la cotización de la jornada, más la tarjeta de crédito menos el recargo, más el débito y el QR. Las fichas no van dentro de ese efectivo.

| Tarea | Diferencia |
|---|---|
| Venta y pago, kiosco y cashless | Efectivo rendido + fichas + cashless + vouchers + online + apuestas − efectivo habilitado. |
| Gastronomía y hotel | Efectivo rendido + fichas + transferencia + cuenta corriente − efectivo habilitado − (X − notas de crédito − fallo de mozo). |
| Empleados | Cero. |

Un negativo es **faltante**: el cajero devolvió de menos. Un positivo es **excedente**. Cero es **sin diferencia**.

Esos importes y el desglose quedan grabados en el cierre. No se recalculan solos si después cambia una cotización. Un cierre anterior a este desglose sigue mostrando los cuatro números que se grabaron en su momento.

### Tolerancia

Si el valor absoluto de la diferencia supera la tolerancia, el cierre entra en **auditoría** aunque hayan elegido conforme o a revisión. La pantalla lo avisa antes de confirmar.

La tolerancia se cambia en **Configuración general**, grupo **Tesorería Crown**, campo **Tolerancia de auditoría**. Mientras nadie la cargue, el respaldo es 50.000 pesos.

### Estados del cierre

| Estado | Cómo se llega | ¿Termina la caja? | ¿Deja cerrar la jornada? |
|---|---|---|---|
| Conforme | Cierre bajo la tolerancia, elegido conforme. | Sí | Sí |
| A revisión | Cierre bajo la tolerancia, elegido a revisión. | La caja ya no admite comprobantes nuevos. El caso sigue abierto. | No |
| Revisada | Tesorería la marca desde conforme, no conforme o a revisión. Firma con usuario y clave. | Sí | Sí |
| Auditoría | La diferencia superó la tolerancia, o tesorería la envió a auditoría. | El caso sigue abierto. | No |
| Cierre de auditoría | Otra persona, distinta de quien la mandó a auditoría, firma el cierre. | Sí | Sí |
| No conforme | Estado previsto para un rechazo del cierre. Desde ahí también se puede pasar a revisada o a auditoría. | No | No |

La historia del cierre guarda cada paso: fecha, estado, quién firmó y el detalle. El cierre original no se pisa.

Con la caja cerrada, en cualquiera de estos estados, los comprobantes quedan en consulta. No se habilita, no se rinde, no se edita, no se pide firma y no se revierte.

## Casos de uso

### Día de una caja de venta

1. Abrir la jornada de la sala.
2. Asignar la caja, el cajero, el turno y la tarea Venta y pago.
3. Habilitar, cargar los billetes y pedir firmas. Tesorería firma con usuario y clave. El cajero firma con su número y su PIN.
4. Rendir, cargar billetes, fichas y medios, y pedir firmas. El cajero firma con su número y su PIN. Tesorería recibe con usuario y clave.
5. Cerrar la caja. Si la diferencia está dentro de la tolerancia y eligen conforme, la caja queda terminada.
6. Repetir con el resto de las cajas y cerrar la jornada.

### El cajero se equivoca antes de firmar

El comprobante está en borrador. Se corrigen las cantidades y se guarda. Si ya se habían pedido firmas y nadie firmó, **Modificar cantidades** lo abre de nuevo. Si alguien ya firmó, **Volver a borrador** borra esas firmas. Después se pide de nuevo.

### Falta una firma

El comprobante queda pendiente. La jornada lo lista para abrirlo y completar la firma que falta. La caja no se cierra mientras siga pendiente.

### No están de acuerdo con el comprobante

En el borrador o en el pendiente, **No conforme** pide el motivo y lo pasa a auditoría. Ese papel deja de sumar. Hace falta otra rendición conforme para cerrar la caja.

### Varias entregas en el mismo turno

**Otra habilitación** y **Otra rendición** crean un comprobante nuevo, con el número siguiente. Cada uno se firma aparte. El cierre suma todos los conformes.

### Gastronomía u hotel

La tarea tiene que ser gastronomía u hotel desde la asignación. En la rendición se eligen los cierres de ventas de esa fecha y se aplican al X y al Z. En el cierre de la caja, el resultado descuenta las notas de crédito. En hotel también suma la cuenta corriente. Una caja de venta y pago no muestra esos campos y su resultado de cierre es cero.

### La diferencia es chica y quieren revisarla después

Al cerrar eligen **A revisión**. La caja ya no acepta movimientos. Tesorería, más tarde, la marca **Revisada** o la manda a **Auditoría**. La jornada no se cierra mientras siga a revisión.

### La diferencia supera la tolerancia

El cierre nace en auditoría. Otra persona, con el mismo permiso de edición y distinta de quien cerró, firma **Cerrar auditoría**. Ahí la caja queda terminada y la jornada ya puede cerrarse.

### Falta la cotización del dólar o del euro

La pantalla de cierre lo dice y no deja cerrar. Se carga la cotización de esa jornada, con venta mayor que cero, y se vuelve a intentar. El cierre usa la última cotización anterior con valor, si la del día no existe.

### Reversa con la caja todavía abierta

Sobre un comprobante conforme, **Revertir** arma otro comprobante, con el número siguiente, las cantidades y los medios al revés, y la observación de qué número revierte. Queda pendiente de las dos firmas. No se puede revertir dos veces, ni revertir una reversa.

Hasta que esa reversa esté conforme, la caja no se cierra. Cuando está conforme, el original y la reversa se compensan en el cierre.

### Caja ya cerrada

El comprobante avisa que quedó en consulta. Para anular una habilitación o una rendición, primero se reabre la caja y después se revierte.

## Bóveda

La bóveda es el lugar de tesorería, no un puesto de cajero. El depósito se carga en Depósitos con tipo Bóveda. El pre-depósito y la caja chica usan la misma pantalla.

Un movimiento tiene número propio de la sala, un tipo tomado del catálogo de Anita (INGR entra, OEGR sale, PDEP sale hacia el pre-depósito, y los marcados «no mueve saldo» quedan asentados sin cambiar la cantidad) y las cantidades de pesos, dólares, euros y fichas. Dólares y euros no se pasan a pesos en el saldo.

El borrador no mueve el saldo. Tesorería lo confirma con usuario y clave. Anularlo, con la jornada abierta, lo saca del saldo y deja el número. Con la jornada cerrada queda en consulta.

Al quedar conforme, la habilitación saca de la bóveda los pesos, dólares y euros. La rendición los vuelve a entrar. Las fichas no pasan por la bóveda. La reversa, cuando queda conforme, hace el movimiento al revés porque las cantidades van con signo contrario. Ese papel se confirma solo, queda vinculado al comprobante y no se puede cargar de nuevo a mano. Anularlo lo saca del saldo. Desde el número se abre el comprobante, y desde el saldo se abre el movimiento.

En la jornada del día se ve el saldo de la bóveda y la lista de movimientos. El menú Bóveda abre la misma pantalla.

## Mesas

La mesa es un depósito de tipo Mesa. El movimiento del día puede ser habilitación, reposición, crédito, estado final, drop o propina. Tesorería lo confirma con usuario y clave. El borrador no entra en el stock.

La habilitación y la reposición suman fichas a la mesa. El crédito las saca. El estado final es el conteo, no un ajuste. El drop es el conteo de la caja de drop y no mueve el flotante de fichas.

Cada mesa apunta a un código de banca. La banca que se usa es la última vigencia con fecha menor o igual a la jornada. Confirmar el movimiento guarda esa banca. Si al día siguiente se carga otra vigencia, el stock y los papeles de ayer siguen con la anterior. Una vigencia nueva no puede fecharse para atrás.

En la jornada, la solapa Mesas es el piso del día: cada mesa muestra si está sin movimiento, en juego, con estado final o con un borrador. Al elegirla se ven apertura, stock, estado final y drop, y desde ahí se abre el movimiento. El número del movimiento abre el detalle para cargar cantidades y confirmar. La vigencia de la banca se cambia desde el enlace de esa mesa.

## Ajuste de auditoría

Este tramo está definido para Crown y todavía no está en la pantalla. Es la corrección habitual después del cierre, cuando el cajero ya no está.

El ajuste es un comprobante nuevo. No reescribe la habilitación, la rendición ni los números originales del cierre.

- Tiene su propia numeración por sala.
- Dice el motivo y qué corrige: la habilitación, la rendición, o el resultado de gastronomía u hotel.
- Lleva el importe de la corrección, a favor o en contra.
- Lo firman dos personas distintas, las dos con usuario y clave del sistema: quien audita y quien confirma en tesorería.
- Se carga con la caja cerrada y también con la jornada cerrada. No reabre el día y no habilita una caja nueva.
- El cierre original sigue en la historia. Cada ajuste agrega una fila **Ajuste de caja** y la pantalla muestra el cierre vigente: originales más ajustes. La diferencia se vuelve a calcular con la misma fórmula.
- Si la diferencia nueva supera la tolerancia, el caso vuelve a auditoría y otra persona firma de nuevo el cierre de auditoría.

## Reapertura

Sirve cuando hay que habilitar o rendir de nuevo, o cuando hay que revertir un comprobante que quedó del otro lado del cierre.

Está en el cierre de la caja, **Reabrir caja**. El permiso es `reabrir-tesoreria-crown-caja`. No se asigna a ningún rol al instalarlo: Sistemas lo tilda en Permiso - Rol, y ahí el signo de pregunta dice qué habilita. El rol administrador entra igual, porque ese rol no consulta los permisos.

Pide el motivo y la clave de un usuario que tenga ese permiso. Queda anotado quién autorizó. La caja vuelve a operativa. Si la jornada estaba cerrada, también vuelve a abierta, salvo que la sala ya tenga otra jornada abierta: si no, la reversa no se puede firmar.

Con la caja reabierta se puede revertir una habilitación o una rendición conforme. El comprobante original no se toca. La reversa es un comprobante nuevo, con el número siguiente, los importes al revés y las dos firmas de siempre: el cajero con número y PIN, y tesorería con usuario y clave. Una sola vez, y no se revierte una reversa.

El cierre anterior no se borra. Al cerrar la caja otra vez, la diferencia se calcula de nuevo con los comprobantes conformes, la reversa incluida.

La reapertura y el ajuste no se reemplazan. El ajuste corrige un importe sin anular el papel y sin reabrir. La reapertura devuelve la caja a la operación para cargar o anular comprobantes.
