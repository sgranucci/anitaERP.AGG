@php
    $filasMeta = 3;
    if (trim($subtitulo ?? '') !== '') {
        $filasMeta++;
    }
@endphp
<table>
    @if (! empty($hayFilaLogos))
        <tr>
            <td colspan="12" style="height:52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="12"><strong>Precargas de cash flow</strong></td>
    </tr>
    <tr>
        <td colspan="12">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim($subtitulo ?? '') !== '')
        <tr>
            <td colspan="12">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="12">{{ (int) ($totalFilas ?? 0) }} registros</td>
    </tr>
    <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Empresa</th>
            <th>Tipo</th>
            <th>Rubro</th>
            <th>Detalle</th>
            <th>Cuenta</th>
            <th>Monto</th>
            <th>Cotización</th>
            <th>Moneda</th>
            <th>Estado</th>
            <th>Comprobante</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ $row->fecha?->format('d/m/Y') }}</td>
                <td>{{ $row->empresa->nombre ?? '' }}</td>
                <td>{{ $row->etiquetaTipo() }}</td>
                <td>{{ $row->etiquetaRubro() }}</td>
                <td>{{ $row->detalle }}</td>
                <td>{{ $row->etiquetaCuenta() }}</td>
                <td>{{ number_format((float) $row->monto, 2, '.', '') }}</td>
                <td>{{ number_format((float) $row->cotizacion, 4, '.', '') }}</td>
                <td>{{ $row->moneda->abreviatura ?? '' }}</td>
                <td>{{ $row->etiquetaEstado() }}</td>
                <td>{{ $row->cajaMovimiento->numerotransaccion ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
