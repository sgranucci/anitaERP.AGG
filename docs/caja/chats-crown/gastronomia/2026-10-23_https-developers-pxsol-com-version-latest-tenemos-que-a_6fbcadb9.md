# https://developers.pxsol.com/?version=latest Tenemos que armar la integracion con PXsol, que es el sistema de hoteleria 

- **Fecha:** 2026-10-23 14:41
- **Chat id:** `6fbcadb9-56d5-4b31-b07a-bdc157cba9c6`
- **Módulos:** gastronomia
- **Origen local:** `agent-transcripts/6fbcadb9-56d5-4b31-b07a-bdc157cba9c6`

> Export redactado para viaje Crown. Sin tool dumps completos; solo diálogo útil.

## Usuario

https://developers.pxsol.com/?version=latest Tenemos que armar la integracion con PXsol, que es el sistema de hoteleria que maneja Crown, te paso las imagenes del mail de todo esto, creo que es lo mas importante. Podes analizar todo y armar un plan de desarrollo? la ultima imagen es como me comunique el año pasado con el anita viejo porque daba problemas. Hay que buscar vincular los cajeros del pxsol con los del anita en la solapa de cajeros externos para que los X se puedan rendir automaticamente con los totales precargados.

## Asistente

Voy a cruzar los mails, la documentación de PXSOL y cómo Anita ya rinde cajas y cajeros externos, para armar un plan de desarrollo concreto.
Voy a cruzar los mails, la documentación de PXSOL y cómo Anita ya rinde cajas y cajeros externos, para armar un plan de desarrollo concreto.

## Asistente

La documentación pública de PXSOL es una página que no carga el detalle sola. Sigo con el código de cajeros Crown y con cómo Anita ya arma las rendiciones X, que es el punto que pediste.
La documentación pública de PXSOL es una página que no carga el detalle sola. Sigo con el código de cajeros Crown y con cómo Anita ya arma las rendiciones X, que es el punto que pediste.

## Asistente

El cierre de caja del hotel se puede armar sobre lo que Anita ya tiene para los cajeros Crown. El X automático sale de PXSOL; la solapa de cajeros externos es el nexo que hoy existe en datos y todavía no en pantalla.

## Qué pide el mail

José arranca por tres cosas, en este orden operativo:

1. Bajar los comprobantes de PXSOL a la contabilidad de Administración Central.
2. Los de modalidad **Cuenta corriente** van a la cuenta corriente del cliente (hay que vincular clientes).
3. Generar el **X** y el **Z**, para que el cierre de caja del hotel no se cargue a mano.

La respuesta de PXSOL apunta a la carpeta Vouchers: `voucher/list`, `voucher/info` y, en 2025, un `voucherlist v2` por rango de fechas. Pablo Martino (13/05/2025) mandó **un token por hotel**: Cipolletti, Viedma, General Roca y Las Grutas. Cada token es de un usuario de esa propiedad (`jld+pxsolc`, `pxsolv`, `pxsolgr`, `pxsollg`). También dice «recordar la encriptación acordada»: en la documentación pública actual no aparece, hay que confirmar con ellos qué cifra.

Los tokens no van al código ni a este plan. Quedan solo en el servidor.

## Por qué el Anita viejo no traía comprobantes

El curl de 2025 pegaba a `GET https://gateway-prod.pxsol.com/v2/voucher/list/v2` con un JSON en el body (`updated_at_start_date` / `updated_at_end_date`). En la [colección pública vigente](https://developers.pxsol.com/?version=latest) ese path y esos campos ya no están.

El contrato de hoy es otro:

`GET https://gateway-prod.pxsol.com/v2/voucher/list?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD&hotel_id=`

Header `Authorization: Bearer …` y `Accept: application/json`. Sin body. Página de a 20. El detalle es `GET /v2/voucher/info/{id}`.

Un GET con body lo descartan muchos clientes HTTP. Eso alcanza para explicar el fallo del año pasado, además del path que cambió.

## Lo que ya está en anitaERP

Las tablas `cajero_crown` y `cajero_crown_externo` copian el Anita de caja (`cajero` + `cajeroexterno`). El sistema externo puede ser **PXSOL**, **GST** o **GASTRONOMIA**, y para PXSOL el vínculo es `codigo_externo`. El comando `caja:sincronizar-cajero-crown` (dry-run) ya importa ese mapeo desde Anita.

No hay ABM ni solapa en pantalla: el alta de «sistema + código externo» sigue solo en el C (`carga_cajeroexterno.fc`), un sistema por cajero.

El cierre X de gastronomía Crown ya hace el gesto que querés para el hotel: lista el total por mozo y crea la rendición con `total_x` cargado. Los medios de pago los sigue completando Caja. Para PXSOL el total y los medios tienen que venir del turno del hotel.

En el comprobante de PXSOL el cajero es `cash_pos_id` (en el ejemplo de la doc, `290`) y el medio es `payment_type`: Contado, Cuenta Corriente, tarjeta, cheque, transferencia, MercadoPago, etc.

## Plan

### 1. Cliente y una prueba de lectura

Un cliente HTTP por hotel, token en `.env`, timeout y paginación. Primera llamada, solo lectura, un día de un hotel: `voucher/list` con `date_from` / `date_to`. Si responde, el problema de 2025 queda cerrado. Si PXSOL insiste con el v2 viejo, lo vemos en esa prueba y no lo damos por vigente.

### 2. Solapa de cajeros externos

ABM de cajero Crown (Crown solamente), con solapa **Cajeros externos**: sistema (PXSOL / GST / Gastronomía) y código externo. Misma regla que el C: un sistema una sola vez por cajero.

Para PXSOL el código externo es el `cash_pos_id`. Se precarga con lo que ya está en Anita (`caje_cajero_ext`) y se puede corregir a mano. Sin esa fila, el X de ese punto de venta no tiene cajero Anita a quien rendir.

Configuración del módulo, no hardcode: hotel PXSOL (`hotel_id`) ↔ sala o empresa Anita, y el token de esa propiedad.

### 3. X con totales precargados

Espejo de la pantalla de cierre X de gastronomía Crown: sala, fecha, una fila por cajero vinculado.

Origen del total del turno:

- `GET /v2/reports/shift-closings?hotel_id=&start=&end=` (informe 10011), o
- el webhook `cash_shift.closed`, que manda `cash_pos_id`, `cash_pos_name`, `closed_at` y totales por moneda (`debit` / `credit` / `net`).

El webhook no parte por medio de pago. Los medios se arman sumando los comprobantes de ese `cash_pos_id` y ese turno, agrupados por `payment_type`, y se precargan en la rendición (el mismo lugar donde gastronomía precarga lo contado por el cajero). La rendición queda para que Caja la confirme; no se cierra sola.

Cuenta corriente no entra al arqueo de caja: ese importe va al punto 5.

### 4. Z del día

Cuando los X del hotel están rendidos, el Z es el cierre del día de esa sala. Candidato: la suma de los cierres de turno, contrastada con `GET /v2/reports/finance/sales/by-pos` y, para lo fiscal, con IVA ventas (`/v2/reports/finance/vat-sales`). El Z no reemplaza a los X.

### 5. Comprobantes a Administración Central

Import incremental por `voucher id` + CAE, idempotente.

| `payment_type` | Destino |
|---|---|
| Cuenta corriente | Cuenta corriente del cliente Anita |
| Contado, tarjeta, transferencia, MercadoPago, cheque | Ya cubiertos por el X de ese `cash_pos_id`; el comprobante fiscal se contabiliza aparte |
| Nota de crédito / débito | Mismo circuito, con signo |

El cliente se vincula por CUIT/DNI (`document_type` + `document_number`) y razón social. Si no hay cliente Anita, el comprobante queda pendiente de vínculo y no se contabiliza.

`voucher/list` trae el fiscal (factura, recibo). El consumo del folio (alojamiento, extras) es `GET /v2/reports/finance/folios`. No se suman las dos cosas.

### 6. Orden de construcción

1. Prueba de lectura de un hotel, un día.
2. Solapa de cajeros externos y cruce `cash_pos_id` ↔ cajero Anita.
3. Pantalla de X con total y medios precargados, rendición borrador.
4. Z del día.
5. Import contable y cuenta corriente de clientes.

Los puntos 3 y 5 pueden avanzar en paralelo después del 2. El 3 es el que destrabó el cierre de caja del hotel.

## Antes de codear

- Confirmar con Pablo qué es la encriptación acordada en el mail del 13/05/2025.
- Confirmar que el código de `cajeroexterno` en Anita es el `cash_pos_id` de PXSOL (se ve en la prueba del punto 1).
- Confirmar si el X es el cierre de turno (`cash_shift.closed`) y el Z el cierre del día del hotel.
- Los cuatro tokens de 2025 pueden estar vencidos: la doc actual recomienda rotarlos a 12 meses. Si la prueba da 401, hay que pedirlos de nuevo.
El cierre de caja del hotel se puede armar sobre lo que Anita ya tiene para los cajeros Crown. El X automático sale de PXSOL; la solapa de cajeros externos es el nexo que hoy existe en datos y todavía no en pantalla.

## Qué pide el mail

José arranca por tres cosas, en este orden operativo:

1. Bajar los comprobantes de PXSOL a la contabilidad de Administración Central.
2. Los de modalidad **Cuenta corriente** van a la cuenta corriente del cliente (hay que vincular clientes).
3. Generar el **X** y el **Z**, para que el cierre de caja del hotel no se cargue a mano.

La respuesta de PXSOL apunta a la carpeta Vouchers: `voucher/list`, `voucher/info` y, en 2025, un `voucherlist v2` por rango de fechas. Pablo Martino (13/05/2025) mandó **un token por hotel**: Cipolletti, Viedma, General Roca y Las Grutas. Cada token es de un usuario de esa propiedad (`jld+pxsolc`, `pxsolv`, `pxsolgr`, `pxsollg`). También dice «recordar la encriptación acordada»: en la documentación pública actual no aparece, hay que confirmar con ellos qué cifra.

Los tokens no van al código ni a este plan. Quedan solo en el servidor.

## Por qué el Anita viejo no traía comprobantes

El curl de 2025 pegaba a `GET https://gateway-prod.pxsol.com/v2/voucher/list/v2` con un JSON en el body (`updated_at_start_date` / `updated_at_end_date`). En la [colección pública vigente](https://developers.pxsol.com/?version=latest) ese path y esos campos ya no están.

El contrato de hoy es otro:

`GET https://gateway-prod.pxsol.com/v2/voucher/list?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD&hotel_id=`

Header `Authorization: Bearer …` y `Accept: application/json`. Sin body. Página de a 20. El detalle es `GET /v2/voucher/info/{id}`.

Un GET con body lo descartan muchos clientes HTTP. Eso alcanza para explicar el fallo del año pasado, además del path que cambió.

## Lo que ya está en anitaERP

Las tablas `cajero_crown` y `cajero_crown_externo` copian el Anita de caja (`cajero` + `cajeroexterno`). El sistema externo puede ser **PXSOL**, **GST** o **GASTRONOMIA**, y para PXSOL el vínculo es `codigo_externo`. El comando `caja:sincronizar-cajero-crown` (dry-run) ya importa ese mapeo desde Anita.

No hay ABM ni solapa en pantalla: el alta de «sistema + código externo» sigue solo en el C (`carga_cajeroexterno.fc`), un sistema por cajero.

El cierre X de gastronomía Crown ya hace el gesto que querés para el hotel: lista el total por mozo y crea la rendición con `total_x` cargado. Los medios de pago los sigue completando Caja. Para PXSOL el total y los medios tienen que venir del turno del hotel.

En el comprobante de PXSOL el cajero es `cash_pos_id` (en el ejemplo de la doc, `290`) y el medio es `payment_type`: Contado, Cuenta Corriente, tarjeta, cheque, transferencia, MercadoPago, etc.

## Plan

### 1. Cliente y una prueba de lectura

Un cliente HTTP por hotel, token en `.env`, timeout y paginación. Primera llamada, solo lectura, un día de un hotel: `voucher/list` con `date_from` / `date_to`. Si responde, el problema de 2025 queda cerrado. Si PXSOL insiste con el v2 viejo, lo vemos en esa prueba y no lo damos por vigente.

### 2. Solapa de cajeros externos

ABM de cajero Crown (Crown solamente), con solapa **Cajeros externos**: sistema (PXSOL / GST / Gastronomía) y código externo. Misma regla que el C: un sistema una sola vez por cajero.

Para PXSOL el código externo es el `cash_pos_id`. Se precarga con lo que ya está en Anita (`caje_cajero_ext`) y se puede corregir a mano. Sin esa fila, el X de ese punto de venta no tiene cajero Anita a quien rendir.

Configuración del módulo, no hardcode: hotel PXSOL (`hotel_id`) ↔ sala o empresa Anita, y el token de esa propiedad.

### 3. X con totales precargados

Espejo de la pantalla de cierre X de gastronomía Crown: sala, fecha, una fila por cajero vinculado.

Origen del total del turno:

- `GET /v2/reports/shift-closings?hotel_id=&start=&end=` (informe 10011), o
- el webhook `cash_shift.closed`, que manda `cash_pos_id`, `cash_pos_name`, `closed_at` y totales por moneda (`debit` / `credit` / `net`).

El webhook no parte por medio de pago. Los medios se arman sumando los comprobantes de ese `cash_pos_id` y ese turno, agrupados por `payment_type`, y se precargan en la rendición (el mismo lugar donde gastronomía precarga lo contado por el cajero). La rendición queda para que Caja la confirme; no se cierra sola.

Cuenta corriente no entra al arqueo de caja: ese importe va al punto 5.

### 4. Z del día

Cuando los X del hotel están rendidos, el Z es el cierre del día de esa sala. Candidato: la suma de los cierres de turno, contrastada con `GET /v2/reports/finance/sales/by-pos` y, para lo fiscal, con IVA ventas (`/v2/reports/finance/vat-sales`). El Z no reemplaza a los X.

### 5. Comprobantes a Administración Central

Import incremental por `voucher id` + CAE, idempotente.

| `payment_type` | Destino |
|---|---|
| Cuenta corriente | Cuenta corriente del cliente Anita |
| Contado, tarjeta, transferencia, MercadoPago, cheque | Ya cubiertos por el X de ese `cash_pos_id`; el comprobante fiscal se contabiliza aparte |
| Nota de crédito / débito | Mismo circuito, con signo |

El cliente se vincula por CUIT/DNI (`document_type` + `document_number`) y razón social. Si no hay cliente Anita, el comprobante queda pendiente de vínculo y no se contabiliza.

`voucher/list` trae el fiscal (factura, recibo). El consumo del folio (alojamiento, extras) es `GET /v2/reports/finance/folios`. No se suman las dos cosas.

### 6. Orden de construcción

1. Prueba de lectura de un hotel, un día.
2. Solapa de cajeros externos y cruce `cash_pos_id` ↔ cajero Anita.
3. Pantalla de X con total y medios precargados, rendición borrador.
4.

…[truncado]…

## Usuario

ahora pregunto, en el otro chat tengo el modulo de caja completo, esto lo corremos despues de armarlo?

## Asistente

Depende de qué parte del módulo de caja ya cierra la rendición. Miro el otro chat para decirte si PXSOL entra después o puede arrancar en paralelo.
Depende de qué parte del módulo de caja ya cierra la rendición. Miro el otro chat para decirte si PXSOL entra después o puede arrancar en paralelo.

## Asistente

Sí. El X de PXSOL se apoya en el módulo de caja, así que esa parte se corre cuando el módulo esté armado.

PXSOL no crea una rendición propia. Toma el cierre de turno del hotel y lo deja cargado en la rendición que Caja ya sabe confirmar: cajero Crown, solapa de cajeros externos y los medios de pago. Esas tres piezas están en el otro chat. Si las enganchamos ahora, el X queda apuntando a una pantalla que todavía se está moviendo.

Lo único que se puede hacer antes, sin tocar caja, es la prueba de lectura: un hotel, un día, `voucher/list` con fecha en la query. Eso solo confirma que el token responde y que el `cash_pos_id` de PXSOL es el código externo del cajero. No graba nada.

Cuando el módulo de caja cierre el ABM del cajero y la rendición, el orden queda así: solapa de externos, X con totales precargados, Z del día, y después los comprobantes a Administración Central y la cuenta corriente.
Sí. El X de PXSOL se apoya en el módulo de caja, así que esa parte se corre cuando el módulo esté armado.

PXSOL no crea una rendición propia. Toma el cierre de turno del hotel y lo deja cargado en la rendición que Caja ya sabe confirmar: cajero Crown, solapa de cajeros externos y los medios de pago. Esas tres piezas están en el otro chat. Si las enganchamos ahora, el X queda apuntando a una pantalla que todavía se está moviendo.

Lo único que se puede hacer antes, sin tocar caja, es la prueba de lectura: un hotel, un día, `voucher/list` con fecha en la query. Eso solo confirma que el token responde y que el `cash_pos_id` de PXSOL es el código externo del cajero. No graba nada.

Cuando el módulo de caja cierre el ABM del cajero y la rendición, el orden queda así: solapa de externos, X con totales precargados, Z del día, y después los comprobantes a Administración Central y la cuenta corriente.
