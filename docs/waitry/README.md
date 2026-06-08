# Waitry — integración gastronomía

## Cuentas externas (Get Orders, doc. pág. 21)

El facturador lista órdenes impagas solo con:

`GET /analytics/analytics/getOrdersPOS?placeId=…`

- Respuesta: `orders[]` con `cart.items[]` (`external_id`, `quantity`, `price.total_price`) y `payment`.
- **Impaga:** `paid` false/0, sin bloque `payment`, o `payment.total_fee.amount` ≤ 0 (p. ej. `type=cash` con monto 0).
- **Cobrada en tótem:** `paid` true/1 o monto de payment > 0 (ajustar regla cuando aparezcan casos distintos en producción).
- Rango horario: `from` / `to` en formato **`YYYY-MM-DD HH:mm:ss`** (hora `app.timezone`, sin offset ISO); por defecto últimos **N** minutos (`WAITRY_GET_ORDERS_MINUTOS_ATRAS`, default `20`). Ej.: `"from": "2026-05-26 07:00:00", "to": "2026-05-27 07:00:00"`.
- **Cache:** `WAITRY_GET_ORDERS_CACHE_SEGUNDOS` (default `15`). Botón refrescar o `?refresh=1` omite cache; importar invalida cache del placeId.
- Importación: crea cuenta libre, carga líneas por SKU (`external_id`) y guarda `waitry_order_id` (numérico Waitry) y `waitry_display_id` (código alfanumérico del papelito/tótem) en `cuenta_gastronomia`.
- **Por ID (papelito del tótem):** botón «Por ID» junto a «Cuentas externas»; consulta `getOrdersPOS?orderId=` (y listado amplio si hace falta) con el campo `id` del JSON. Acepta órdenes ya cobradas en Waitry (`incluir_orden_pagada`).
- **Cobro en tótem:** si la orden ya está pagada en Waitry, la cuenta queda con `waitry_cobro_totem` y cobranza automática con cuenta de caja **TOTEM** (`GASTRONOMIA_CUENTACAJA_TOTEM_CODIGO`, default `TOTEM`), bloqueada en el POS. No se re-sincroniza el pago a Waitry.
- **Sin doble factura:** `venta_gastronomia_emision.waitry_order_id` (índice único) y validación al importar/emitir. Órdenes impagas: se excluyen del listado si ya están facturadas; pendientes de pago siguen el flujo normal de cobranza en caja.

Rutas POS: `ventas/gastronomia/api/waitry-ordenes-pendientes`, `waitry-importar-orden`.

## Cierre de jornada (tesorería, getordersdetails)

Proceso de **auditoría/conciliación** bajo **Caja → Rendiciones → Cierre jornada Waitry** (`caja/waitry-cierre-jornada`).

- Consulta Waitry: `POST /analytics/analytics/getordersdetails` por **fecha de jornada** (`from` = `to` = `yyyy-mm-dd`).
- Cruce Anita: ventas con `venta_gastronomia_emision.waitry_order_id` y `venta.fechajornada` = esa fecha, filtradas por empresa.
- Estados: conciliada, Waitry sin factura, importada pendiente, diferencia de monto, solo Anita.
- **No** usa `getOrdersPOS` (ese endpoint es solo para el POS en vivo, doc. pág. 21).
- Permiso: `listar-waitry-cierre-jornada-caja`.

### Proceso de cierre (redistribución QR/efectivo, asientos, facturación)

En la misma pantalla (**Caja → Rendiciones → Cierre jornada Waitry**), sección inferior visible solo con permiso `proceso-cierre-jornada-waitry-caja` (roles: **administrador**, **Enc-tesorería**).

- Tramo Waitry: desde último `waitry_order_id` del cierre anterior hasta cierre de jornada (`getordersdetails` + ventana operativa).
- Grilla QR/MP/Efectivo; porcentaje sobre total facturado Anita; redistribución exacta QR ↔ efectivo.
- Preview de asientos contables **sin grabar** (tras **Recalcular**).
- Configuración por empresa: cuentas contables + punto de venta (`gastronomia_cierre_jornada_config`).

#### Medios Waitry → columnas (kiosco)

| Criterio Waitry | Columna cuadro | Informe Z (Posnet) |
|---|---|---|
| `totalcoin` | QR | No |
| `credit_card` + sin `payments` o gateway `KIOSK MP` | MP | **Sí** |
| `credit_card` + gateway `KIOSK MPQR` | QR | No |
| `cash` (kiosko pagado en mostrador) | Efectivo | No |
| `interface` / `external_reference_id` `E-…` (mostrador Anita) | Según gateway push | No |

`credit_card` en Waitry ya no es sinónimo de QR: distinguir por `payment.payments[].gateway`.

#### Órdenes canceladas

Waitry puede marcar órdenes `canceled=true` (a veces entregadas con descuento 100 % y sin pago). **No** entran en cuadro, totales ni candidatos a facturar; se muestran en meta/badge para auditoría. Ver `WaitryOrdenEstadoSupport`.

#### Cuadro y diagnóstico

- Filas: facturado Anita, TOTEM puente, Waitry sin facturar, impagos (referencia), cash no facturar.
- Clic en importe del cuadro → modal con comandas/fechas (`CierreJornadaCuadroDetalleSupport`).
- Consola: `php artisan gastronomia:diagnostico-cuadro-cierre {empresa} {fecha} {fila} {medio} [--csv=ruta]`
- NC Waitry: `php artisan gastronomia:diagnostico-nota-credito {empresa} {waitry_order_id}`

#### Pasos implementados vs pendientes

| Paso | Estado |
|---|---|
| Analizar tramo + cuadro + grupos | OK |
| % sobre facturado Anita + Recalcular | OK |
| Preview asientos (sin persistir) | OK |
| Grabado asientos | Pendiente |
| Facturación (masiva o una a una) | Pendiente — **una factura por permiso** alivia carga en servidor |

#### Punto de venta del proceso (por empresa)

Antes de emitir facturas del proceso (cuando se implemente), validar PV con `CierreJornadaProcesoPuntoventaSupport::resolverOError($empresaId)`.

Prioridad:

1. `gastronomia_cierre_jornada_config.puntoventa_id`
2. Mapa en `.env`: `GASTRONOMIA_CIERRE_JORNADA_PUNTOVENTA_CODIGO_POR_EMPRESA` (JSON `empresa_id` → código PV)

Reglas: PV de la misma empresa; `modofacturacion` ≠ manual (`M`).

**BSA (`empresa_id = 1`):** código `00003`.

```env
GASTRONOMIA_CIERRE_JORNADA_PUNTOVENTA_CODIGO_POR_EMPRESA={"1":"00003"}
```

#### Performance

- `GASTRONOMIA_CIERRE_TOTEM_ENRIQUECER_PAYMENT_INDIVIDUAL_MAX=0` desactiva consultas `getOrdersPOS` por orden (recomendado en producción).
- Regla Cursor: `.cursor/rules/cierre-jornada-waitry-proceso.mdc`

## Cobro en POS (syncStatusPOS)

Al facturar una cuenta importada desde Waitry (`cuenta_gastronomia.waitry_order_id`), Anita notifica el pago:

`POST /interface/interface/syncStatusPOS`

| Campo | Valor |
|-------|--------|
| `order_id` | `waitry_order_id` de la cuenta |
| `event` | `accepted` (`WAITRY_SYNC_STATUS_POS_EVENT`) |
| `paid` | `true` |
| `totalPaid` | suma de montos cobrados en Anita (obligatorio para registrar el pago en Waitry) |
| `payment.type` | `cash` \| `mercadopago` \| `totalcoin` (medio real de cobranza en caja) |
| `payment.total_fee` | monto y moneda del medio de cobro principal |

**Tipo de pago (Anita → Waitry):** solo las cuentas mapeadas en `WAITRY_TIPO_PAGO_CUENTACAJA` como **Mercado Pago** o **Totalcoin** conservan su tipo; **cualquier otro medio** (efectivo, tarjetas, FISERV, etc.) se envía como `cash`.

**`totalPaid`:** suma de la cobranza en Anita; sin este campo Waitry puede marcar la orden pagada pero mostrar `credit_card` por defecto.

Si falla la API, la factura **no** se revierte; el POS recibe aviso en `warn` (`waitry_pago` / `waitry_pago_mensaje`).

## Push Orders — pago externo (interface)

Waitry registra cobros de **pushExternalOrder** con `payment.type` = **`interface`** (no `cash` / `credit_card` en el tipo principal). El medio real va en `payment.payments[]`:

| Campo | Uso |
|-------|-----|
| `gateway` | Identificación Control Z (configurable: `WAITRY_PAGO_GATEWAY_POR_TIPO`; default `MERCADOPAGO`, `TOTALCOIN`, `CASH`, …) |
| `amount` | Importe de ese medio |

**Lectura tótem (getOrdersPOS):** distinción Posnet vs QR en `credit_card` vía `payment.payments[].gateway`:

| Gateway | Significado |
|---|---|
| *(vacío / sin payments)* | Terminal Posnet junto al kiosco |
| `KIOSK MP` | Terminal Posnet (config nueva Waitry) |
| `KIOSK MPQR` | QR Mercado Pago en kiosco |

Órdenes de mostrador Anita: `payment.type=interface` o `external_reference_id` con prefijo `E-`.

## Push Orders (pushExternalOrder, doc. pág. 28)

Tras facturar cuentas **sin** `waitry_order_id`, Anita envía la comanda a cocina vía **Push Orders** (`POST /interface/interface/pushExternalOrder`).

## Request

Ver [`push-external-order-request-ejemplo.json`](push-external-order-request-ejemplo.json).

| Campo | Origen Anita |
|-------|----------------|
| `placeId` | `WAITRY_PLACE_ID_POR_EMPRESA` por `empresa_id` |
| `table` | `WAITRY_TABLE_POR_EMPRESA` — ver [`table-por-empresa.json`](table-por-empresa.json) |
| `external_id` | **`venta.id`** (string) — correlación con la venta interna |
| `orderItems` | Líneas de `venta_emision` (SKU → `item.externalId`) |
| `paid` | `true` si hubo cobranza en el POS |
| `totalPaid` | Monto pagado (junto con `paid` registra el cobro en Waitry como **interface**) |
| `payment` | Si hubo cobranza: `type` = **`interface`**, `total_fee`, y `payments[]` con `gateway` + `amount` por medio (Control Z; ej. `MERCADOPAGO`, `CASH`) |
| `client_name` / `external_client_id` | Cliente de factura de la cuenta (si existe) |

## Response

Ver [`push-external-order-response-ejemplo.json`](push-external-order-response-ejemplo.json).

| Campo Waitry | Persistencia Anita |
|--------------|-------------------|
| `externalId` | Debe coincidir con `venta.id` enviado en `external_id` |
| `orderId` | `cuenta_gastronomia.waitry_order_id` y `waitry_comanda_envio.waitry_order_id` |
| `external_delivery_id` (respuesta push; también `display_id` en getOrdersPOS) | `cuenta_gastronomia.waitry_display_id` — código alfanumérico del papelito |

El papelito se toma de la **misma respuesta** de `pushExternalOrder` (`response.external_delivery_id`), sin consulta extra a getOrdersPOS en la emisión.

El ticket fiscal y el PDF de factura incluyen **Papelito Waitry: …** cuando hay `waitry_display_id` o `waitry_order_id`. Si la comanda se envía a Waitry al facturar (cuenta sin orden previa), la impresión del ticket se difiere hasta completar el push (misma respuesta HTTP).

## Configuración (.env)

```env
WAITRY_HABILITADO=true
WAITRY_GET_ORDERS_MINUTOS_ATRAS=20
WAITRY_TABLE_POR_EMPRESA={"1":{"tableId":101066},"2":{"tableId":101067},"3":{"tableId":101068}}
WAITRY_PLACE_ID_POR_EMPRESA={"1":11782,"2":11783,"3":11784}
```

Prueba: `php artisan waitry:probar-conexion --empresa=1 --renovar`

Borrador mail a Waitry (push/KDS y push vs sync): [`consulta-endpoints-email.md`](consulta-endpoints-email.md).
