@php
    $badgePolitica = \App\Support\Ventas\PedidoListadoSupport::badgePoliticaCliente($pedido ?? null);
@endphp
@if ($badgePolitica)
    <span class="badge {{ $badgePolitica['clase'] }} ml-1"
          title="{{ $badgePolitica['titulo'] }}">{{ $badgePolitica['etiqueta'] }}</span>
@endif
