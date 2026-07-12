<table>
    @if(!empty($reservarFilaLogoExcel))
        <tr><td colspan="10" style="height: 52px;"></td></tr>
    @endif
    <tr>
        <td colspan="10"><strong style="font-size: 16px;">Flash diario</strong></td>
    </tr>
    <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Empresa</th>
            <th>AyB</th>
            <th>Estac.</th>
            <th>Bingo venta</th>
            <th>Slot win</th>
            <th>Gaming</th>
            <th>Revenues</th>
            <th>Comentario</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
        <tr>
            <td>{{ $data->id }}</td>
            <td>{{ $data->fecha?->format('d/m/Y') }}</td>
            <td>{{ $data->empresa->nombre ?? '' }}</td>
            <td>{{ number_format((float) $data->ayb, 2, '.', '') }}</td>
            <td>{{ number_format((float) $data->estac, 2, '.', '') }}</td>
            <td>{{ number_format((float) $data->bingo_total_venta, 2, '.', '') }}</td>
            <td>{{ number_format((float) $data->slot_r, 2, '.', '') }}</td>
            <td>{{ number_format($data->total_gaming, 2, '.', '') }}</td>
            <td>{{ number_format($data->total_revenues, 2, '.', '') }}</td>
            <td>{{ $data->comentario }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
