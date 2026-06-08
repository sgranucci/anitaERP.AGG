<p class="mb-2">
    Concilia las órdenes de Waitry (<code>getordersdetails</code>) con las ventas facturadas en Anita
    para la <strong>fecha de jornada</strong> (<code>venta.fechajornada</code>).
    Proceso de tesorería/auditoría bajo <strong>Caja → Rendiciones</strong>; el POS en vivo usa <code>getOrdersPOS</code>.
</p>
<p class="mb-0">
    <strong>Importadas</strong> = cuenta gastronomía Waitry en ERP (<code>cuenta_gastronomia.waitry_order_id</code>).
    <strong>Impaga en Waitry</strong> = criterio getOrdersPOS: <code>paid</code> false/0 o cobro ≤ 0.
    El recuadro amarillo agrupa todas las importadas impagas; el gris, las mismas con cobranza en Anita (medio a validar).
</p>
