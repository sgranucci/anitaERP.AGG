<p class="mb-0">
    Base del <strong>%</strong>: total neto facturado Anita (facturas − NC, fechajornada)
    <strong id="label-total-facturacion"></strong>.
    Pendiente a facturar (total Waitry cobrado sin facturar): <span id="label-pendiente-facturar"></span>.
    Impago Waitry (ref.): <span id="label-impago-waitry"></span>.
    <br>
    <span id="cuadro-desglose-anita" class="text-muted"></span>
    <br>Debajo del cuadro: comparación <strong>facturado Anita</strong> vs <strong>disponible a recodificar</strong>
    (QR/Totalcoin + MP → 3er asiento). Se aplica <strong>min(25% objetivo, tope del día)</strong>.
    Totalcoin entra en QR. El % recorre comandas por <code>waitry_order_id</code> (MP y QR mezclados).
    <br>Arriba: <strong>neto fiscal</strong> del día (facturas − NC). Abajo, fila 1: <strong>todas</strong> las cobranzas ERP
    a contabilizar (asiento 2), incluidas terminales sin Waitry; solo excluye cobro TOTEM (fila 2).
    <br>Haga clic en un importe del cuadro para ver el detalle (por línea de cobranza en Anita).
</p>
