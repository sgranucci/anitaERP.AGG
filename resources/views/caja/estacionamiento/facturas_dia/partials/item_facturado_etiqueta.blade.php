@php
    $itemId = (int) ($itemId ?? $item_estacionamiento_id ?? 0);
    $nombre = trim((string) ($nombre ?? $detalle ?? ''));
@endphp
@include('caja.estacionamiento.facturas_dia.partials.link_item_estacionamiento', [
    'itemId' => $itemId,
    'nombre' => $nombre,
])
