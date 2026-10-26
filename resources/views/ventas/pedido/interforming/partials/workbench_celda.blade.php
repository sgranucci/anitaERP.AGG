@switch ($key)
    @case ('codigo')
        <td class="text-nowrap">{{ $pedido->codigo }}</td>
        @break
    @case ('tipo')
        <td class="text-nowrap">{{ $pedido->tipo_comprobante }}</td>
        @break
    @case ('fecha')
        <td class="text-nowrap">{{ optional($pedido->fecha)->format('d/m/Y') ?? substr((string) $pedido->fecha, 0, 10) }}</td>
        @break
    @case ('fechaentrega')
        <td class="text-nowrap">{{ optional($pedido->fechaentrega)->format('d/m/Y') ?? substr((string) $pedido->fechaentrega, 0, 10) }}</td>
        @break
    @case ('cliente_codigo')
        <td>{{ $pedido->clientes->codigo ?? '' }}</td>
        @break
    @case ('cliente')
        <td>{{ $pedido->clientes->codigo ?? '' }} — {{ $pedido->clientes->nombre ?? '' }}</td>
        @break
    @case ('orden_compra')
        <td>{{ $pedido->orden_compra }}</td>
        @break
    @case ('estado')
        <td>@include('ventas.pedido.interforming.partials.badge_estado', ['pedido' => $pedido])</td>
        @break
    @case ('aprobacion')
        <td>@include('ventas.pedido.interforming.partials.badge_aprobacion', ['pedido' => $pedido])</td>
        @break
    @case ('vendedor')
        <td>{{ $pedido->vendedores->nombre ?? '' }}</td>
        @break
    @case ('transporte')
        <td>{{ $pedido->transportes->nombre ?? '' }}</td>
        @break
    @case ('moneda')
        <td>{{ $pedido->moneda->nombre ?? '' }}</td>
        @break
    @case ('numero')
        <td class="text-nowrap">{{ $pedido->numero_comprobante }}</td>
        @break
    @case ('lugarentrega')
        <td>{{ $pedido->lugarentrega }}</td>
        @break
    @case ('leyenda')
        <td>{{ $pedido->leyenda }}</td>
        @break
    @case ('id')
        <td>{{ $pedido->id }}</td>
        @break
    @default
        <td></td>
@endswitch
