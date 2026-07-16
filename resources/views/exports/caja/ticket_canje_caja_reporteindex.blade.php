<table>
@if (! empty($reservarFilaLogoExcel))
    <tr>
        <td colspan="14" style="height: 52px;"></td>
    </tr>
@endif
    <tr>
        <td colspan="14"><strong style="font-size:16pt;">{{ $titulo ?? 'Informe de Datos de Ventas / Canjes' }}</strong></td>
    </tr>
    <tr>
        <td colspan="14">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
@if (! empty($subtitulo))
    <tr>
        <td colspan="14">{{ $subtitulo }}</td>
    </tr>
@endif
    <tr>
        <td colspan="14">
            {{ (int) (($totales['cantidad'] ?? 0)) }} tickets
            · Venta {{ number_format((float) ($totales['monto_venta'] ?? 0), 2, ',', '.') }}
            · Ticket {{ number_format((float) ($totales['monto_ticket'] ?? 0), 2, ',', '.') }}
        </td>
    </tr>
    <thead>
        <tr>
            <th># Id</th>
            <th>Fecha</th>
            <th>Turno</th>
            <th>Caja</th>
            <th>Cajero</th>
            <th>Autorizante</th>
            <th>Monto de Venta</th>
            <th>Monto Ticket</th>
            <th>Documento</th>
            <th>E</th>
            <th>Hora</th>
            <th>Fecha canje</th>
            <th>Tip</th>
            <th>Numero</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $f)
            <tr>
                <td>{{ $f->vale ?? '' }}</td>
                <td>{{ $f->fecha_fmt ?? '' }}</td>
                <td>{{ $f->turno_nombre ?? '' }}</td>
                <td>{{ $f->caja ?? '' }}</td>
                <td>{{ $f->cajero_nombre ?? '' }}</td>
                <td>{{ $f->autorizante_nombre ?? '' }}</td>
                <td>{{ number_format((float) $f->monto_venta, 2, '.', '') }}</td>
                <td>{{ number_format((float) $f->monto_ticket, 2, '.', '') }}</td>
                <td>{{ $f->nro_documento }}</td>
                <td>{{ $f->estado_etiqueta ?? $f->estado }}</td>
                <td>{{ $f->hora_fmt ?? '' }}</td>
                <td>{{ $f->fecha_canje_fmt ?? '' }}</td>
                <td>{{ $f->tip_factura ?? '' }}</td>
                <td>{{ $f->numero_factura ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
