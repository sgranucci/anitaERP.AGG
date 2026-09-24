@php
    $etiquetaEstado = $pedido->etiquetaEstado();
    $badgeEstado = $pedido->badgeClassEstado();
@endphp
<span class="{{ $badgeEstado }}" title="Estado del pedido">{{ $etiquetaEstado }}</span>
