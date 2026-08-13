@php
    $kpis = $kpis ?? ($resultado['kpis'] ?? []);
@endphp
@if (! empty($kpis))
    <div class="d-flex flex-wrap px-3 py-2 border-bottom bg-light">
        <div class="mr-4 mb-1">
            <small class="text-muted d-block">L&iacute;neas</small>
            <span class="font-weight-bold">{{ number_format((int) ($kpis['cantidad_filas'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="mr-4 mb-1">
            <small class="text-muted d-block">COM</small>
            <span class="font-weight-bold">{{ number_format((int) ($kpis['cantidad_com'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="mr-4 mb-1">
            <small class="text-muted d-block">Cantidad</small>
            <span class="font-weight-bold">{{ number_format((float) ($kpis['cantidad_total'] ?? 0), 2, ',', '.') }}</span>
        </div>
        <div class="mr-4 mb-1">
            <small class="text-muted d-block">Importe (moneda orig.)</small>
            <span class="font-weight-bold">{{ number_format((float) ($kpis['importe_total'] ?? 0), 2, ',', '.') }}</span>
        </div>
        <div class="mr-4 mb-1">
            <small class="text-muted d-block">Importe MN</small>
            <span class="font-weight-bold text-primary">{{ number_format((float) ($kpis['importe_mn'] ?? 0), 2, ',', '.') }}</span>
        </div>
        <div class="mr-4 mb-1">
            <small class="text-muted d-block">Con diferencias</small>
            <span class="font-weight-bold text-warning">{{ number_format((int) ($kpis['con_diferencia'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="mr-4 mb-1">
            <small class="text-muted d-block">COM sin facturar</small>
            <span class="font-weight-bold text-danger">{{ number_format((int) ($kpis['sin_facturar'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="mr-4 mb-1">
            <small class="text-muted d-block">Devoluciones</small>
            <span class="font-weight-bold">{{ number_format((int) ($kpis['devoluciones'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="mr-4 mb-1">
            <small class="text-muted d-block">L&iacute;neas con rechazo</small>
            <span class="font-weight-bold">{{ number_format((int) ($kpis['rechazadas'] ?? 0), 0, ',', '.') }}</span>
        </div>
        @if (($kpis['aging_promedio'] ?? null) !== null)
            <div class="mr-4 mb-1">
                <small class="text-muted d-block">D&iacute;as sin facturar (prom.)</small>
                <span class="font-weight-bold">{{ (int) $kpis['aging_promedio'] }}</span>
            </div>
        @endif
        @if (! empty($kpis['precio_pendiente']))
            <div class="mb-1">
                <small class="text-muted d-block">Precio pendiente</small>
                <span class="font-weight-bold text-info">{{ (int) $kpis['precio_pendiente'] }}</span>
            </div>
        @endif
    </div>
@endif
