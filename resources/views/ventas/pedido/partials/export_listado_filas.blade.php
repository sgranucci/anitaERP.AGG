@php
    $totales = $totales ?? App\Support\Ventas\PedidoListadoSupport::totalesPedido($pedido);
    $fecha = $pedido['fecha'] ?? $pedido->fecha ?? null;
    $fechaEntrega = $pedido['fechaentrega'] ?? $pedido->fechaentrega ?? null;
@endphp
<tr>
    <td>{{ $pedido['id'] ?? $pedido->id ?? '' }}</td>
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
    <td>{{ $totales['caja'] }}</td>
    <td>{{ $totales['pieza'] }}</td>
    <td>{{ $totales['kilo'] }}</td>
    <td>{{ $totales['pesada'] }}</td>
    <td>{{ $pedido->nombretransporte ?? '' }}</td>
    <td>{{ $pedido['estado'] ?? $pedido->estado ?? '' }}</td>
</tr>
