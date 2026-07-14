@php
    use App\Support\Ventas\RemitoListadoSupport;
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
                <strong style="font-size: 16pt;">Listado de remitos de clientes</strong>
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
        @if (count($remitos) > 0)
            <tr>
                <td colspan="10">Registros: {{ count($remitos) }}</td>
            </tr>
        @endif
    </tbody>
    <thead>
    <tr>
        <th>ID</th>
        <th>Código</th>
        <th>Fecha</th>
        <th>Fecha entrega</th>
        <th>Cliente</th>
        <th>Cajas</th>
        <th>Piezas</th>
        <th>Kilos</th>
        <th>Reparto</th>
        <th>Estado</th>
    </tr>
    </thead>
    <tbody>
        @php $totalCaja = 0; $totalPieza = 0; $totalKilo = 0; @endphp
        @foreach ($remitos as $remito)
            @php
                $totales = RemitoListadoSupport::totalesRemito($remito);
                $totalCaja += $totales['caja'];
                $totalPieza += $totales['pieza'];
                $totalKilo += $totales['kilo'];
            @endphp
            @include('ventas.remito.partials.export_listado_filas', compact('remito', 'totales'))
        @endforeach
        @if (count($remitos) > 0)
            <tr>
                <td colspan="5" style="font-weight: bold;">Totales</td>
                <td style="font-weight: bold;">{{ $totalCaja }}</td>
                <td style="font-weight: bold;">{{ $totalPieza }}</td>
                <td style="font-weight: bold;">{{ $totalKilo }}</td>
                <td colspan="2"></td>
            </tr>
        @endif
    </tbody>
</table>
