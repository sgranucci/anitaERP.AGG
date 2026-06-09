@php
    use App\Support\Ventas\PedidoListadoSupport;
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="10" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="10">
                <h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de pedidos de clientes</h2>
            </td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Fecha entrega</th>
            <th>Cliente</th>
            <th>Cajas</th>
            <th>Piezas</th>
            <th>Kilos</th>
            <th>Pesada</th>
            <th>Reparto</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @php $totalCaja = 0; $totalPieza = 0; $totalKilo = 0; $totalPesada = 0; @endphp
        @foreach ($pedidos as $pedido)
            @php
                $totales = PedidoListadoSupport::totalesPedido($pedido);
                $totalCaja += $totales['caja'];
                $totalPieza += $totales['pieza'];
                $totalKilo += $totales['kilo'];
                $totalPesada += $totales['pesada'];
            @endphp
            @include('ventas.pedido.partials.export_listado_filas', compact('pedido', 'totales'))
        @endforeach
        @if (count($pedidos) > 0)
            <tr>
                <td colspan="4" style="font-weight: bold;">Totales</td>
                <td style="font-weight: bold;">{{ $totalCaja }}</td>
                <td style="font-weight: bold;">{{ $totalPieza }}</td>
                <td style="font-weight: bold;">{{ $totalKilo }}</td>
                <td style="font-weight: bold;">{{ $totalPesada }}</td>
                <td colspan="2"></td>
            </tr>
        @endif
    </tbody>
</table>
