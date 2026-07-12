<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr><td colspan="7" style="height:52px;"></td></tr>
    @endif
    <tr><td colspan="7"><strong>Cumplimientos de requisición de compra</strong></td></tr>
    <thead>
        <tr>
            <th>N°</th>
            <th>Fecha</th>
            <th>Usuario</th>
            <th>Empresa</th>
            <th>Estado</th>
            <th>Líneas</th>
            <th>Requisiciones / Leyenda</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $row)
            @php
                $reqNros = $row->articulos->pluck('requisicion.numerorequisicion')->filter()->unique()->implode(', ');
                $detalle = trim($reqNros.(($reqNros !== '' && $row->leyenda) ? ' — ' : '').($row->leyenda ?? ''));
            @endphp
            <tr>
                <td>{{ $row->numero }}</td>
                <td>{{ optional($row->fecha)->format('d/m/Y H:i') }}</td>
                <td>{{ $row->usuario?->nombre ?? '' }}</td>
                <td>{{ $row->empresa?->nombre ?? '' }}</td>
                <td>{{ $row->estado === \App\Models\Compras\CumplimientoRequisicionCompra::ESTADO_ACTIVO ? 'ACTIVO' : 'REVERTIDO' }}</td>
                <td>{{ $row->articulos_count ?? $row->articulos->count() }}</td>
                <td>{{ $detalle }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
