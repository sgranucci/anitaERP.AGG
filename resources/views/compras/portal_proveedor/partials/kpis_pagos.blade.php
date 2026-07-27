@php
    $r = $resumen ?? [];
@endphp
<div class="row mb-3">
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="portal-kpi">
            <div class="kpi-label">Pagos en el período</div>
            <div class="kpi-valor">{{ number_format((int) ($r['cantidad'] ?? 0), 0, ',', '.') }}</div>
            <div class="kpi-ayuda">Órdenes de pago visibles</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="portal-kpi">
            <div class="kpi-label">Monto pagado</div>
            <div class="kpi-valor">$ {{ number_format((float) ($r['monto_pagado'] ?? 0), 2, ',', '.') }}</div>
            <div class="kpi-ayuda">Total de órdenes de pago</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="portal-kpi">
            <div class="kpi-label">Retenciones</div>
            <div class="kpi-valor">$ {{ number_format((float) ($r['monto_retenciones'] ?? 0), 2, ',', '.') }}</div>
            <div class="kpi-ayuda">{{ (int) ($r['cantidad_retenciones'] ?? 0) }} certificados</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="portal-kpi">
            <div class="kpi-label">Neto acreditado</div>
            <div class="kpi-valor">$ {{ number_format((float) ($r['monto_neto'] ?? 0), 2, ',', '.') }}</div>
            <div class="kpi-ayuda">Pagado menos retenciones</div>
        </div>
    </div>
</div>
