# Waitry — Push Orders (pushExternalOrder)

Integración gastronomía: tras facturar, Anita envía la comanda a cocina vía **Push Orders** (`POST /interface/interface/pushExternalOrder`).

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
WAITRY_TABLE_POR_EMPRESA={"1":{"tableId":101066},"2":{"tableId":101067},"3":{"tableId":101068}}
WAITRY_PLACE_ID_POR_EMPRESA={"1":11782,"2":11783,"3":11784}
```

Prueba: `php artisan waitry:probar-conexion --empresa=1 --renovar`
