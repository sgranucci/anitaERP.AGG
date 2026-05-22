# Waitry — integración gastronomía

## Cuentas externas (Get Orders, doc. pág. 21)

El facturador puede listar órdenes sin pago desde:

`GET /analytics/analytics/getOrdersPOS?placeId=…`

- Respuesta: `orders[]` con `cart.items[]` (`external_id`, `quantity`, `price.total_price`).
- Sin pago: sin bloque `payment` con cobro, o `paid = 0` si viene en el JSON.
- Rango horario: `from` / `to` en la consulta; por defecto últimos **N** minutos (`WAITRY_GET_ORDERS_MINUTOS_ATRAS`, default `20`). `0` = sin filtro.
- Importación: crea cuenta libre, carga líneas por SKU (`external_id`) y guarda `waitry_order_id` en `cuenta_gastronomia`.

Rutas POS: `ventas/gastronomia/api/waitry-ordenes-pendientes`, `waitry-importar-orden`.

## Cobro en POS (syncStatusPOS)

Al facturar una cuenta importada desde Waitry (`cuenta_gastronomia.waitry_order_id`), Anita notifica el pago:

`POST /interface/interface/syncStatusPOS`

| Campo | Valor |
|-------|--------|
| `order_id` | `waitry_order_id` de la cuenta |
| `event` | `accepted` (`WAITRY_SYNC_STATUS_POS_EVENT`) |
| `paid` | `true` |
| `payment.type` | `cash` \| `credit_card` \| `debit_card` (enum Waitry) |
| `payment.total_fee` | monto y moneda del medio de cobro principal |

**Tipo de pago:** la cuenta de efectivo de `GASTRONOMIA_CUENTACAJA_EFECTIVO_POR_EMPRESA` → `cash`. Tarjetas: inferencia por nombre/código de `cuentacaja` o mapeo explícito `WAITRY_CUENTACAJA_TIPO_PAGO` (JSON `{"43":"credit_card","44":"debit_card"}`).

Si falla la API, la factura **no** se revierte; el POS recibe aviso en `warn` (`waitry_pago` / `waitry_pago_mensaje`).

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
| `client_name` / `external_client_id` | Cliente de factura de la cuenta (si existe) |

## Response

Ver [`push-external-order-response-ejemplo.json`](push-external-order-response-ejemplo.json).

| Campo Waitry | Persistencia Anita |
|--------------|-------------------|
| `externalId` | Debe coincidir con `venta.id` enviado en `external_id` |
| `orderId` | `cuenta_gastronomia.waitry_order_id` y `waitry_comanda_envio.waitry_order_id` |

Si `externalId` de la respuesta no coincide con el `venta_id` enviado, el envío se trata como error (no se graba `orderId`).

## Configuración (.env)

```env
WAITRY_HABILITADO=true
WAITRY_GET_ORDERS_MINUTOS_ATRAS=20
WAITRY_TABLE_POR_EMPRESA={"1":{"tableId":101066},"2":{"tableId":101067},"3":{"tableId":101068}}
WAITRY_PLACE_ID_POR_EMPRESA={"1":11782,"2":11783,"3":11784}
```

Prueba: `php artisan waitry:probar-conexion --empresa=1 --renovar`
