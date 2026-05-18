@php
    $em = $movimiento->venta_emisiones ?? null;
    $skuPadre = (string) ($em?->articulos?->sku ?? '');
    $articuloIdPadre = (int) ($em?->articulo_id ?? 0);
    $detallePadre = (string) ($em?->articulos?->descripcion ?? $em?->detalle ?? '');
@endphp
@include('ventas.gastronomia.facturas_dia.partials.item_facturado_insumos', [
    'sku' => $skuPadre,
    'articuloId' => $articuloIdPadre,
    'detalle' => $detallePadre,
])
