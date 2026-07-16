@php
    $esExport = (bool) ($es_export ?? false);
@endphp
<table class="table table-striped table-bordered table-hover mb-0 @if($esExport) data @endif" id="{{ $esExport ? 'tabla-export' : 'tabla-paginada' }}">
    <thead style="background-color:#85C1E9;color:#17202A;">
        <tr>
            <th># Id</th>
            <th>Fecha</th>
            <th>Turno</th>
            <th>Caja</th>
            <th>Cajero</th>
            <th>Autorizante</th>
            <th class="text-right">Monto de Venta</th>
            <th class="text-right">Monto Ticket</th>
            <th>Documento</th>
            <th>Estado</th>
            <th>Hora</th>
            <th>Fecha canje</th>
            <th>Tip</th>
            <th>Numero</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $f)
            <tr>
                <td>{{ $f->vale ?? $f->etiquetaVale() }}</td>
                <td>{{ $f->fecha_fmt ?? ($f->fecha?->format('d/m/Y') ?? '') }}</td>
                <td>{{ $f->turno_nombre ?? '' }}</td>
                <td>{{ $f->caja ?? ($f->identificador_pc ?? '') }}</td>
                <td>{{ $f->cajero_nombre ?? '' }}</td>
                <td>{{ $f->autorizante_nombre ?? '' }}</td>
                <td class="text-right">{{ number_format((float) $f->monto_venta, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) $f->monto_ticket, 2, ',', '.') }}</td>
                <td>{{ $f->nro_documento }}</td>
                <td>{{ $f->estado_etiqueta ?? $f->estado }}</td>
                <td>{{ $f->hora_fmt ?? '' }}</td>
                <td>{{ $f->fecha_canje_fmt ?? '' }}</td>
                <td>{{ $f->tip_factura ?? '' }}</td>
                <td>{{ $f->numero_factura ?? '' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="14" class="text-center text-muted">Sin registros para los filtros indicados.</td>
            </tr>
        @endforelse
    </tbody>
</table>
