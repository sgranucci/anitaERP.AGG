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
| Gastronomía | Abre los datos de X y notas de crédito. |
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

En la rendición se cargan las mismas monedas y, además, las fichas. Después van los medios, en el orden de la pantalla de Anita: número de Z cashless, pagos y ventas cashless, pagos parcial, apuestas, ventas y pagos de casino on-line, débito, crédito, recargo, cuenta corriente, transferencia, vouchers y QR. En sala el recargo no se tipea: es la tarjeta de crédito menos ese crédito dividido 1,05 (desde el 8/11/2024; antes, dividido 1,10). Gastronomía y hotel no calculan ese recargo. Si la caja se abrió como gastronomía u hotel, la rendición muestra los cierres de ventas pendientes de esa fecha. Primero salen los del ERP (`tesoreria_crown_cierre_fiscal` / Cierre X y `p-cierrefact`); si falta alguno del día, se completa con Anita (`cierrefiscal`), sin repetir el mismo sucursal+X/Z. No se lee `p-vtagastro`. El Z es el cierre fiscal de una barra. El X es el informe que la rendición de hotel toma. Se marcan los que corresponden a esa caja y **Aplicar a la rendición** completa el X, el Z, las notas de crédito y el último ticket. Esos importes se pueden corregir antes de guardar. Si falta un cierre, **Cargar un cierre a mano** lo deja pendiente del día. El cierre de Anita no se modifica. En hotel, además, se cargan la cuenta corriente y la transferencia. Si el cajero tiene código PXSol, **Traer de PXSol** pide facturado, crédito, IVA de facturas e IVA de notas de crédito entre la primera habilitación y el momento de la consulta. El facturado completa el X y el Z, y el crédito completa las notas de crédito. Esos importes se pueden corregir antes de guardar. El comando vive en `TESORERIA_CROWN_PXSOL_COMANDO`; si está vacío, el botón avisa que PXSol no está configurado.

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

Las dos firmas se hacen en la misma pantalla, con el teclado. El cajero tipea el número de cajero asignado a esa caja y su PIN. Tesorería firma con un usuario del sistema que tenga permiso para editar movimientos de Tesorería Crown. Ese usuario no puede ser el usuario vinculado al cajero de esa caja. El mensaje de error de firmante es genérico: no dice si falló el usuario, la clave o el permiso.

Cuando firman los dos, el comprobante pasa a conforme. Desde la pantalla se imprime el PDF del comprobante.

Hay un tope de intentos fallidos de PIN y de usuario/clave (cinco). Al superarlos hay que esperar unos minutos.

### PIN del cajero

El PIN es propio del cajero (4 a 8 dígitos). No puede ser el código ni el legajo, ni series obvias (1111, 1234, 4321…).

| Situación | Qué pasa |
|---|---|
| Primera firma o PIN blanqueado | El sistema pide PIN nuevo + repetición en la misma pantalla. Hasta no elegir uno propio, no firma. |
| PIN ya elegido | Firma con número + PIN actual. |
| Se olvidó el PIN | En el comprobante pendiente, tesorería abre **El cajero se olvidó el PIN**, firma con usuario y clave, y blanquea. En la próxima firma el cajero elige uno nuevo. |
| Banner amarillo | Si el cajero todavía no tiene PIN propio, el movimiento y el cierre lo avisan a tesorería. |

El blanqueo no genera un aviso en la bandeja: es un trámite de firma en el mostrador.

### No conforme y reversa

**No conforme** (borrador o pendiente) pide motivo y deja el comprobante en auditoría. Deja de sumar. Para cerrar la caja hace falta otra rendición conforme.

**Revertir** sobre un conforme crea un papel nuevo con el número siguiente, importes al revés y las dos firmas. Una sola vez; no se revierte una reversa. Hasta que la reversa esté conforme, la caja no cierra. Si la caja ya estaba cerrada, primero **Reabrir caja**.

## Cierre de la caja

El cierre no es una rendición. Es el control de esa caja: qué se le dio, qué devolvió y cuál es la diferencia.

En la jornada, la columna **Cierre** abre la pantalla. Para poder cerrar tienen que cumplirse las tres cosas:

- No queda ningún comprobante de esa caja en borrador o pendiente de firma. Una reversa sin firmar también frena el cierre.
- Hay al menos una rendición conforme.
- Hay cotización para cada moneda extranjera usada.

Al cerrar firman los dos en el momento: tesorería con usuario y clave, y el cajero con su clave. El resultado que eligen es **conforme** o **a revisión**.

### Cómo se calcula la diferencia

Todo el cierre se expresa en pesos. Entran solo los comprobantes conformes. Una reversa conforme resta, porque sus cantidades y sus medios van al revés.

La habilitación es el efectivo: pesos, más dólares y euros por la cotización de la jornada. En el listado, la rendición se ve en las mismas líneas que Anita: valores varios (efectivo, crédito, débito, QR y cuenta corriente, con el recargo incluido) y, debajo, el recargo en negativo. Las fichas no van dentro de valores varios.

La rendición pide los mismos importes que la pantalla de Anita: pagos y ventas cashless, pagos parcial, apuestas, ventas y pagos de casino on-line, débito, crédito, recargo, hotel, vouchers y QR. El cashless del cierre es pagos del Z menos ventas. El on-line es pagos menos ventas. Los vouchers son pagos menos ventas.

| Tarea | Diferencia |
|---|---|
| Venta y pago, kiosco y cashless | Efectivo rendido + fichas + (pagos Z − ventas cashless) + (pagos − ventas de vouchers) + (pagos − ventas online) + (pagos − ventas de apuestas) − efectivo habilitado. |
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

La tarea tiene que ser gastronomía u hotel desde la asignación. En la rendición aparecen los cierres de ventas pendientes del día: primero los del ERP; si falta alguno, se completa con Anita (mismo sucursal + X/Z, sin repetir). Se marcan y **Aplicar a la rendición** completa X, Z, NC y último ticket. Si falta un cierre y Anita no respondió, se puede **Cargar un cierre a mano**. En hotel, además, **Traer de PXSol** si el cajero tiene código. El cierre de la caja descuenta las notas de crédito; en hotel suma la cuenta corriente. Una caja de venta y pago no muestra esos campos.

### La diferencia es chica y quieren revisarla después

Al cerrar eligen **A revisión**. La caja ya no acepta movimientos. Tesorería, más tarde, la marca **Revisada** o la manda a **Auditoría**. La jornada no se cierra mientras siga a revisión.

### La diferencia supera la tolerancia

El cierre nace en auditoría. Otra persona, distinta de quien lo mandó a auditoría, con permiso **auditar-tesoreria-crown-caja** (Enc-contaduría lo tiene; administrador entra igual), firma **Cerrar auditoría**. Ahí la caja queda terminada y la jornada ya puede cerrarse.

Proceso diario al final del día: en el escritorio de la jornada aparece el panel **Resolver para cerrar la jornada** con las cajas que todavía bloquean. Si hay auditorías pendientes, el mismo panel pide usuario y clave del auditor y con una sola firma cierra todas las que esa persona pueda firmar. El resto (sin cierre, a revisión, no conforme) se abre con el botón de cada fila y se resuelve en la pantalla de cierre. Cuando el panel desaparece, **Cerrar jornada** ya puede correr.

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

## Slots

El drop de máquinas y el recuento del stacker entran en la solapa **Slots** de la jornada. Los dos salen del mismo archivo de Anita: el drop es el tipo Drop y el recuento es Contadora, Manual o Corrección. Cada movimiento trae el número de Anita, la máquina, el total y el billetaje.

La solapa lista el día y, al abrir un número, muestra las denominaciones que lo arman. Pdf, Excel y Csv salen de la barra de la sala con ese día.

## Valores y conversión de fichas

El catálogo de fichas y billetes está en **Tesorería Crown → Valores** (tipos FVAL/FCOL/ARS/USD/EUR). La **conversión del día** (antes en un ABM aparte de Fichas) se abre desde Valores o desde el enlace de la solapa Poder público. No hay menú propio de Fichas.

## Poder del público

Está en la jornada, solapa **Poder público**. El ERP lo **genera y persiste** en `tesoreria_crown_poder_publico` (día / mes / acumulado de fichas y de slot). El anexo I lee esa tabla; ya no depende de que Anita grabe `ppublico`.

### Fichas (`l-stockfichm.c`)

Por cada ficha, la conversión inicial es la conversión final de ayer, más la asignación inicial de hoy y los otros movimientos de ficha del día. La conversión final le suma la variación de mesas y la de cajeros:

- Mesas: estado final − (habilitación + reposición − crédito) + canje.
- Cajeros: rendición de pago + rendición de venta − habilitación.

El poder público del día es (conversión inicial − conversión final) × valor, sumado de todas las fichas. Si la conversión final sube, el día da negativo: volvió ficha de la sala. Ese importe es la línea **Fichas poder público** del anexo I. El mes y el acumulado arrancan en el día anterior y le suman este día. El 1 del mes, el mes arranca en cero.

La reserva es la de los cierres de ficha más las asignaciones iniciales posteriores a ese cierre. El poder público en cantidad es reserva − conversión final − mesas de la banca − habilitación del cajero.

El 30/10/2026 el cálculo da −6.500, el mismo día, mes y acumulado que Anita tiene en `ppublico`.

### Slot (`l-anexo1.c`)

`slot día = subtotal soft count − resultado slots online − voucher de mesa`.

- **Resultado slots** sale de resultado online (`coin_in − coin_out − pago_manual`).
- **Voucher de mesa** suma `tesoreria_crown_mesa.voucher` (importado de `efem_voucher`).
- **Subtotal soft count** se carga en resultado online (`subtotal_slot`). Sin ese dato el poder público de slot queda en cero (no se lee `ppublico` de Anita).

## Flash Crown

Pantalla `caja/flash-crown` (permiso `listar-flash-crown`). Un flash por sala/fecha armado desde Anexo I/II + resultado online (`FlashCrownCalculoSupport`). Cron `flash-crown:calcular-diario`. Reportes y suscripciones en `caja/flash-crown/reporte`. No usa el Flash AGG (`caja/flash`).

## Anexos

Están en la jornada, botón **Anexos** del escritorio (aparte de la carga). Pestañas: I, II, resumen de cajeros, III, VII y VIII. El permiso es `listar-tesoreria-crown-anexo`. Pdf, Excel y Csv salen de la barra de la sala. No graban en Anita. El cálculo es **ERP-first**: los imports Anita son carga histórica, no fuente viva del día.

### Anexo I

Sigue `l-anexo1.c` (día / mes / anual) y `l-anexo1new.c` (débito / crédito / saldo). Incluye **apuestas deportivas** como ingreso cuando hay dato ERP. Caja inicial = `caja_final_viii` de la jornada anterior. Poder público / propina / drop / clientes salen de tablas Crown (no de `acumanexo1` / `ppublico` en vivo).

### Anexo II

`l-resmesa.c`: selector **Mesas / Máquinas / Gastronomía / Todo** (`grupo_anexo2`). Cada botón pide su permiso (`listar-tesoreria-crown-anexo2-mesas`, `-maquinas`, `-gastronomia`, `-todo`); no vienen asignados a ningún rol — Sistemas los tilda en Permiso - Rol. Mesas agrupadas por juego; máquinas = cashless + validadoras + resultado; gastronomía = cierres fiscales ERP (G/H). El MTD usa solo días con jornada ERP.

### Resumen de cajeros y Anexo III

`l-rescajero.c` y `l-anexo3new.c`: por caja, egresos/ingresos, fichas, cashless, voucher, online, apuestas (III) y diferencia. Parte del cierre de caja del ERP.

### Anexo VII

`l-anexo7.c`: fallos (diferencia negativa + fallo de mozo) y excedentes. **Anticipos de sueldo: fuera de alcance** — no hay proceso ERP ni lectura Anita; la columna queda en cero a propósito.

### Anexo VIII

`l-estboveda.c`: total de caja final (ítem 130 del anexo I) =

- stock pesos = `tesoreria_crown_boveda_stock_dia` / último arqueo conforme ERP + slots de recuento sin CashBox a bóveda + drops de mesa confirmados
- + ítems resumen final (pre-depósito, fondos, cajas chicas, diferencias) desde bóveda ERP
- + propina acumulada (`tesoreria_crown_propina_dia` o Σ mesas F)
- + moneda extranjera convertida (`tesoreria_crown_moneda_extranjera` × cotización de ingreso)

Histórico Anita → ERP: `php artisan tesoreria-crown:importar-anexo8-anita --desde=… --hasta=…` (y reserva de fichas / cierre vía imports propios). Tras el import, el cálculo **no** vuelve a Anita.

Las fichas / poder público se listan aparte y **no** entran en ese total. La reserva de fichas alimenta el poder público / stock de fichas, no el total de caja final del VIII.

El total del VIII se persiste en `tesoreria_crown_jornada.caja_final_viii` al calcular el anexo (panel o export). El Anexo I lo lee de ahí para la fila «Caja final (anexo VIII)» y para la caja inicial del día siguiente. `caja_final_dia` / `caja_final_mes` son otra cosa: la caja final **por variación** del Anexo I. Si confirman bóveda, slots o mesas, se invalida `caja_final_viii` y se recalcula la próxima vez que se abra el VIII o lo pida el I.

## Bandeja de avisos de auditoría

Menú **Tesorería Crown → Avisos** (`caja/tesoreria-crown/aviso`). Es la bandeja de hallazgos del día/mes: no reemplaza el cierre de caja.

Cada aviso tiene regla, severidad (alta / media / baja), estado (abierto / revisado / descartado / superado) y enlace a la jornada o al comprobante. Desde la bandeja se marca **Revisado** o **Descartado** (con nota). El sistema puede pasar un aviso a **Superado** cuando la condición deja de cumplirse (por ejemplo, el no conforme se resolvió).

| Regla | Severidad | Qué detecta |
|---|---|---|
| Diferencia de cierre sobre la tolerancia | Alta | Cierre con \|diferencia\| > tolerancia |
| Comprobante no conforme sin resolver | Alta | Papel en auditoría sin cierre terminal |
| Rendición sin habilitación conforme | Alta | F sin H conforme |
| Comprobante modificado después del cierre | Alta | Edición post-cierre |
| Misma persona firma las dos partes | Alta | Misma persona en entrega y recibe |
| Reversa de comprobante | Media | Reversa creada |
| Caja reabierta | Media | Reapertura |
| Cajero en dos cajas el mismo día | Media | Mismo cajero en dos asignaciones |
| Rendición gastro/hotel con X sin cierre | Media | X cargado sin cierre de ventas |
| Fallos repetidos del cajero | Media | Varios cierres con diferencia en la ventana |
| Jornada abierta hace varios días | Media | Jornada abierta más de N días |
| Diferencia contra Anita | Media | Control Anita del día |
| Anexo VIII distinto de la variación del I | Media | `caja_final_viii` ≠ caja final por variación |
| Cotización de otra fecha | Baja | ME convertida con cotización ≠ día |
| Recálculo hacia adelante pendiente | Baja | Hay días posteriores a invalidar |

Corrida: comando `tesoreria-crown:avisos` (también al evento de cierre/comprobante) y schedule diario a la hora de **Mails del resumen de avisos** (default 07:30). Si hay mails cargados en Configuración → Tesorería Crown, manda el resumen de abiertos. Parámetros: cantidad/días/mínimo de fallos, días de jornada abierta, hora y lista de mails.

Permisos: `listar-tesoreria-crown-aviso` (ver) y `auditar-tesoreria-crown-caja` (resolver / firmar auditoría / reabrir en A). Enc-contaduría tiene ambos.

## Ajuste de auditoría

Está en el cierre de la caja, **Ajuste de caja** (pantalla del cierre, con caja ya cerrada). El permiso es `ajustar-tesoreria-crown-caja`. No se asigna a ningún rol al instalarlo: Sistemas lo tilda en Permiso - Rol. El rol administrador entra igual. Es la corrección habitual después del cierre, cuando el cajero ya no está.

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
