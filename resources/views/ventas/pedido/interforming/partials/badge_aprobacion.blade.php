@php
    $etiquetaAprob = $etiquetaAprob ?? ($pedido->etiquetaAprobacion() ?? '—');
    $badgeClass = $badgeClass ?? \App\Support\Ventas\PedidoEstadosInterforming::badgeClassAprobacion($etiquetaAprob);
@endphp
<span class="{{ $badgeClass }}" title="Estado de aprobación del árbol">{{ $etiquetaAprob }}</span>
