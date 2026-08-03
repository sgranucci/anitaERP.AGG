@php
    use App\Support\Caja\Remesa\RemesaSupport;
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
            <td colspan="10"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de remesas</h2></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>ID</th>
            <th>N&deg;</th>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Estado</th>
            <th>Empresa</th>
            <th>Importe destino</th>
            <th>Importe origen</th>
            <th>Remito</th>
            <th>Observaci&oacute;n</th>
        </tr>
    </thead>
    <tbody>
        @php
            $tipoLabels = [
                RemesaSupport::TIPO_INTERNA => 'Interna',
                RemesaSupport::TIPO_EXTERNA => 'Externa',
            ];
            $estadoLabels = [
                RemesaSupport::ESTADO_CONFIRMADA => 'Confirmada',
                RemesaSupport::ESTADO_ANULADA => 'Anulada',
            ];
        @endphp
        @foreach ($datas as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ $row->numero }}</td>
                <td>{{ $row->fecha?->format('d/m/Y') }}</td>
                <td>{{ $tipoLabels[$row->tipo] ?? $row->tipo }}</td>
                <td>{{ $estadoLabels[$row->estado] ?? $row->estado }}</td>
                <td>{{ $row->nombreempresa ?? ($row->empresa->nombre ?? '') }}</td>
                <td>{{ number_format((float) $row->importe_destino, 2, '.', '') }}</td>
                <td>{{ number_format((float) $row->importe_origen, 2, '.', '') }}</td>
                <td>{{ $row->remito }}</td>
                <td>{{ $row->observacion }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
