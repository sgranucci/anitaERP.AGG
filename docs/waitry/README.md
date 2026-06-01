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
- Grilla QR/MP/Efectivo; porcentaje sobre total facturado; redistribución exacta QR ↔ efectivo.
- Preview de asientos contables antes de facturación masiva (ejecución pendiente de implementar).
- Configuración de cuentas ventas / IVA / impuesto interno por empresa (`gastronomia_cierre_jornada_config`).

## Cobro en POS (syncStatusPOS)

Al facturar una cuenta importada desde Waitry (`cuenta_gastronomia.waitry_order_id`), Anita notifica el pago:

`POST /interface/interface/syncStatusPOS`

| Campo | Valor |
|-------|--------|
| `order_id` | `waitry_order_id` de la cuenta |
| `event` | `accepted` (`WAITRY_SYNC_STATUS_POS_EVENT`) |
| `paid` | `true` |
| `payment.type` | `cash` \| `mercadopago` \| `totalcoin` |
| `payment.total_fee` | monto y moneda del medio de cobro principal |

**Tipo de pago (Anita → Waitry):** solo las cuentas mapeadas en `WAITRY_TIPO_PAGO_CUENTACAJA` como **Mercado Pago** o **Totalcoin** conservan su tipo; **cualquier otro medio** (efectivo, tarjetas, FISERV, etc.) se envía como `cash`.

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
| `payment` | Si hubo cobranza: `type` (`cash`, `credit_card`, `debit_card`, `mercadopago`, `totalcoin`, …) y `total_fee` (mismo formato que syncStatusPOS) |
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

Borrador mail a Waitry (push/KDS y push vs sync): [`consulta-endpoints-email.md`](consulta-endpoints-email.md).
