@php
    use App\Support\Ventas\PedidoListadoSupport;
    $subtituloFiltros = $subtituloFiltros ?? '';
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
                <strong style="font-size: 16pt;">Listado de pedidos de clientes</strong>
            </td>
        </tr>
        <tr>
            <td colspan="10">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        @if (trim((string) $subtituloFiltros) !== '')
            <tr>
                <td colspan="10">{{ $subtituloFiltros }}</td>
            </tr>
        @endif
        @if (count($pedidos) > 0)
            <tr>
                <td colspan="10">Registros: {{ count($pedidos) }}</td>
            </tr>
        @endif
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
            @if (PedidoListadoSupport::esCierreReparto($pedido, $totalesPorReparto ?? []))
                @include('ventas.pedido.partials.fila_subtotal_reparto', [
                    'metaReparto' => PedidoListadoSupport::metaReparto($pedido, $totalesPorReparto ?? []),
                ])
            @endif
        @endforeach
        @if (count($pedidos) > 0)
            <tr>
                <td colspan="4" style="font-weight: bold;">Totales</td>
                <td style="font-weight: bold;">{{ PedidoListadoSupport::formatearTotal($totalCaja) }}</td>
                <td style="font-weight: bold;">{{ PedidoListadoSupport::formatearTotal($totalPieza) }}</td>
                <td style="font-weight: bold;">{{ PedidoListadoSupport::formatearTotal($totalKilo) }}</td>
                <td style="font-weight: bold;">{{ PedidoListadoSupport::formatearTotal($totalPesada) }}</td>
                <td colspan="2"></td>
            </tr>
        @endif
    </tbody>
</table>
