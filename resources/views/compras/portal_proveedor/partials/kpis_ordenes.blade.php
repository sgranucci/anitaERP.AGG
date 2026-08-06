@php
    $r = $resumen ?? [];
@endphp
<div class="row mb-3">
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="portal-kpi">
            <div class="kpi-label">Órdenes de compra</div>
            <div class="kpi-valor">{{ number_format((int) ($r['cantidad'] ?? 0), 0, ',', '.') }}</div>
            <div class="kpi-ayuda">Según filtros activos</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="portal-kpi">
            <div class="kpi-label">Con factura</div>
            <div class="kpi-valor">{{ number_format((int) ($r['con_factura'] ?? 0), 0, ',', '.') }}</div>
            <div class="kpi-ayuda">{{ (int) ($r['sin_factura'] ?? 0) }} sin factura aún</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="portal-kpi">
            <div class="kpi-label">Monto OC</div>
            <div class="kpi-valor">$ {{ number_format((float) ($r['monto_oc'] ?? 0), 2, ',', '.') }}</div>
            <div class="kpi-ayuda">Suma de líneas de las OC</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="portal-kpi">
            <div class="kpi-label">Facturado</div>
            <div class="kpi-valor">$ {{ number_format((float) ($r['monto_facturado'] ?? 0), 2, ',', '.') }}</div>
            <div class="kpi-ayuda">Total de facturas asociadas</div>
        </div>
    </div>
</div>
