@php
    $totales = $totales ?? App\Support\Ventas\PedidoListadoSupport::totalesPedido($pedido);
    $fecha = $pedido['fecha'] ?? $pedido->fecha ?? null;
    $fechaEntrega = $pedido['fechaentrega'] ?? $pedido->fechaentrega ?? null;
@endphp
<tr>
    <td>{{ App\Support\Ventas\PedidoListadoSupport::codigoParaListado($pedido) }}</td>
    <td>
        @if ($fecha)
            {{ date('d/m/Y', strtotime((string) $fecha)) }}
        @endif
    </td>
    <td>
        @if ($fechaEntrega)
            {{ date('d/m/Y', strtotime((string) $fechaEntrega)) }}
        @endif
    </td>
    <td>{{ $pedido['nombrecliente'] ?? $pedido->nombrecliente ?? '' }}</td>
    <td>{{ App\Support\Ventas\PedidoListadoSupport::formatearTotal($totales['caja']) }}</td>
    <td>{{ App\Support\Ventas\PedidoListadoSupport::formatearTotal($totales['pieza']) }}</td>
    <td>{{ App\Support\Ventas\PedidoListadoSupport::formatearTotal($totales['kilo']) }}</td>
    <td>{{ App\Support\Ventas\PedidoListadoSupport::formatearTotal($totales['pesada']) }}</td>
    <td>{{ $pedido->nombretransporte ?? '' }}</td>
    <td>{{ $pedido['estado'] ?? $pedido->estado ?? '' }}</td>
</tr>
