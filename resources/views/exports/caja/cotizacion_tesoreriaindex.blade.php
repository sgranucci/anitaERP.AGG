@php
    use App\Support\Caja\CotizacionTesoreriaMonedasSupport;
    $totalColumnas = $totalColumnas ?? CotizacionTesoreriaMonedasSupport::totalColumnasDatos();
    $monedasColumnas = $monedasColumnas ?? CotizacionTesoreriaMonedasSupport::monedasParaColumnas();
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $totalColumnas }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $totalColumnas }}"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de cotización tesorería</h2></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>ID</th>
            <th>Empresa</th>
            <th>Fecha</th>
            @foreach ($monedasColumnas as $moneda)
                <th>{{ $moneda->label }}</th>
                <th></th>
            @endforeach
        </tr>
        <tr>
            <th></th>
            <th></th>
            <th></th>
            @foreach ($monedasColumnas as $moneda)
                <th>Compra</th>
                <th>Venta</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->nombreempresa ?? $data->empresas?->nombre ?? $data->empresa_id }}</td>
                <td>{{ $data->fecha ? $data->fecha->format('d/m/Y') : '' }}</td>
                @foreach ($monedasColumnas as $moneda)
                    <td>{{ CotizacionTesoreriaMonedasSupport::formatear($data->tasaCompra((int) $moneda->codigo)) }}</td>
                    <td>{{ CotizacionTesoreriaMonedasSupport::formatear($data->tasaVenta((int) $moneda->codigo)) }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
