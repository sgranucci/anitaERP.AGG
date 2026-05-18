@php
    $skuPadre = (string) ($sku ?? '');
    $articuloIdPadre = (int) ($articuloId ?? 0);
    $detallePadre = trim((string) ($detalle ?? ''));
@endphp
@if ($skuPadre !== '' && $skuPadre !== '—')
    @include('ventas.gastronomia.facturas_dia.partials.link_sku_articulo', [
        'sku' => $skuPadre,
        'articuloId' => $articuloIdPadre,
    ])
@else
    <span class="text-muted">—</span>
@endif
@if ($detallePadre !== '' && $detallePadre !== '—')
    <span class="text-muted small"> — {{ $detallePadre }}</span>
@endif
