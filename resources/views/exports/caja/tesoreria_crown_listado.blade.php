<table>
@if (!empty($reservarFilaLogoExcel))
<tbody>
<tr>
    <td colspan="{{ count($columnas) }}" style="height:52px;"></td>
</tr>
</tbody>
@endif
<tbody>
<tr>
    <td colspan="{{ count($columnas) }}"><strong>{{ $titulo }}</strong></td>
</tr>
<tr>
    <td colspan="{{ count($columnas) }}">Generado {{ date('d/m/Y H:i') }}</td>
</tr>
@if (!empty($mostrarSubtitulo))
<tr>
    <td colspan="{{ count($columnas) }}">{{ $subtitulo }}</td>
</tr>
@endif
<tr>
    <td colspan="{{ count($columnas) }}">Registros: {{ $totalFilas }}</td>
</tr>
</tbody>
<thead>
    <tr>
        @foreach ($columnas as $columna)
            <th>{{ $columna['label'] }}</th>
        @endforeach
    </tr>
</thead>
<tbody>
    @foreach ($filas as $fila)
        <tr>
            @foreach ($fila as $i => $celda)
                @if (!empty($columnas[$i]['numero']))
                    <td>{{ number_format((float) $celda, 2, '.', '') }}</td>
                @else
                    <td>{{ $celda }}</td>
                @endif
            @endforeach
        </tr>
    @endforeach
</tbody>
</table>
