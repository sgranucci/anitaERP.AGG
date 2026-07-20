<table>
    @if(!empty($reservarFilaLogoExcel))
        <tr><td colspan="10" style="height: 52px;"></td></tr>
    @endif
    <tr>
        <td colspan="10"><strong style="font-size: 16px;">Parámetros flash</strong></td>
    </tr>
    <thead>
        <tr>
            <th>ID</th>
            <th>Período</th>
            <th>Empresa</th>
            <th>Budget total</th>
            <th>Slots</th>
            <th>Bingo</th>
            <th>F&amp;B</th>
            <th>Estac.</th>
            <th>POS</th>
            <th>Días</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
        <tr>
            <td>{{ $data->id }}</td>
            <td>{{ $data->periodo_label ?? $data->periodo }}</td>
            <td>{{ $data->empresa->nombre ?? '' }}</td>
            <td>{{ number_format((float) $data->budget_total, 2, '.', '') }}</td>
            <td>{{ number_format((float) $data->budget_slot, 2, '.', '') }}</td>
            <td>{{ number_format((float) $data->budget_bingo, 2, '.', '') }}</td>
            <td>{{ number_format((float) $data->budget_f_b, 2, '.', '') }}</td>
            <td>{{ number_format((float) $data->budget_estac, 2, '.', '') }}</td>
            <td>{{ $data->budget_pos }}</td>
            <td>{{ $data->indices->count() }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
