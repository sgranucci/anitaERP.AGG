# Consulta Waitry — borrador mail (enfocado)

**Asunto:** Validación flujo push → KDS y uso de pushExternalOrder vs syncStatusPOS

---

Estimados,

Somos el equipo de **Anita ERP**. Tenemos integrado Waitry con nuestro POS de gastronomía y en producción ya usamos con éxito:

- **`GET getOrdersPOS`** — listado e importación de órdenes del tótem (rango `from`/`to` en `YYYY-MM-DD HH:mm:ss`).
- **`POST syncStatusPOS`** — al cobrar en caja una orden importada desde Waitry.
- **`POST getordersdetails`** — cierre de jornada / conciliación.

Lo que **aún no pudimos validar en cocina** es que una venta **creada y facturada solo en Anita** (sin orden previa en Waitry) dispare la comanda en el **KDS** vía **`POST pushExternalOrder`**.

Les pedimos confirmación del flujo y de la configuración necesaria; el resto de la integración no requiere definición adicional de su parte salvo lo indicado abajo.

---

## Cómo operamos hoy (para que validen el criterio)

```text
Orden nace en Waitry (tótem)
  → Importamos en Anita (getOrdersPOS)
  → Si ya pagó en tótem: facturamos con medio TOTEM, NO llamamos syncStatusPOS
  → Si impaga: cobramos en caja → facturamos → syncStatusPOS
  → NO hacemos pushExternalOrder (la orden ya tiene waitry_order_id)

Orden nace en Anita (mesa / cuenta libre, sin waitry_order_id)
  → Facturamos (con o sin cobranza según el caso)
  → pushExternalOrder (paid según hubo cobranza en POS)
  → Esperamos ver la comanda en KDS  ← pendiente de probar
```

| Escenario | Acción Anita hacia Waitry |
|-----------|---------------------------|
| Importada del tótem, **impaga**, cobro en caja | `syncStatusPOS` tras facturar |
| Importada del tótem, **ya pagada** en tótem | Sin `syncStatusPOS` |
| Creada en Anita, **sin** `waitry_order_id` previo | `pushExternalOrder` tras facturar |
| Ya tiene `waitry_order_id` (importada) | Sin `pushExternalOrder` |

**¿Este criterio es el correcto según Waitry?** ¿Hay algún caso en el que debamos llamar **ambos** endpoints o ninguno?

---

## Preguntas concretas (lo que nos falta cerrar)

### 1. pushExternalOrder → KDS (prioridad)

1. ¿**`pushExternalOrder`** es el endpoint correcto para que la comanda aparezca en el **KDS** de un `placeId` dado?
2. Con nuestros datos de prueba — `placeId` 11782 / 11783 / 11784 y `tableId` 101066 / 101067 / 101068 (mesa virtual por sucursal) — ¿debería verse en cocina? ¿Falta alguna configuración en su panel (ruteo a KDS, tipo de mesa, etc.)?
3. En el push enviamos `external_id` = ID de nuestra venta, `orderItems` con `item.externalId` = SKU del ERP, y `paid: true/false` según hubo cobranza en el POS al facturar. ¿Es correcto? ¿Influye en el KDS el valor de **`paid`** si la comanda ya se cobró en caja?
4. Si el API responde `ok: true` con `orderId`, ¿ese ID es el mismo que luego aparecería en `getOrdersPOS`?

### 2. push vs syncStatusPOS (solo dudas de proceso)

5. Orden **importada** desde Waitry y cobrada en Anita: ¿alcanza con **`syncStatusPOS`** o Waitry espera también un push?
6. Orden **solo Anita**: ¿solo **`pushExternalOrder`**, sin sync previo?
7. **Pago mixto** en caja (efectivo + tarjeta): hoy enviamos en sync el medio de mayor monto (`cash` / `credit_card` / `debit_card`). ¿Es aceptable?
8. Factura **sin cobranza** (cortesía / descuento 100%): ¿`paid: false` en push y omitir `syncStatusPOS`?

---

## Lo que pedimos para destrabar la prueba de KDS

- Confirmación escrita de que el flujo de la tabla y el diagrama son correctos.
- Si pueden, indicar **checklist en su backoffice** (placeId, tableId, KDS asignado) para el push de prueba.
- Un ejemplo de respuesta exitosa de **pushExternalOrder** que haya generado ticket en KDS en otro cliente (anonimizado), o coordinar una ventana para probar juntos.

---

Quedamos atentos. Gracias.

**[Nombre]**  
**[Cargo — Anita ERP]**  
**[Email]**

---

## Nota interna (no enviar a Waitry)

- **getOrdersPOS / importación:** operativo; formato de fechas alineado; no incluir en el mail salvo incidencias nuevas en producción.
- **Prueba pendiente:** facturar cuenta sin `waitry_order_id` y verificar KDS + logs `waitry.comanda.ok` / `waitry_comanda_envio`.
- **Doc técnica:** [`README.md`](README.md).
