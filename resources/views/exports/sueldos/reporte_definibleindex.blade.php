<table>
@if (!empty($reservarFilaLogoExcel))
    <tr><td colspan="{{ 2 + count($resultado['columnas'] ?? []) }}" style="height: 52px;"></td></tr>
@endif
<tr>
    <td colspan="{{ 2 + count($resultado['columnas'] ?? []) }}">
        <strong style="font-size:16pt;">{{ $titulo }}</strong>
    </td>
</tr>
<tr>
    <td colspan="{{ 2 + count($resultado['columnas'] ?? []) }}">Generado {{ date('d/m/Y H:i') }}</td>
</tr>
@if (!empty($subtitulo))
<tr>
    <td colspan="{{ 2 + count($resultado['columnas'] ?? []) }}">{{ $subtitulo }}</td>
</tr>
@endif
<thead>
<tr>
    <th>Legajo</th>
    <th>Nombre</th>
    @foreach ($resultado['columnas'] ?? [] as $col)
        <th>{{ $col['descripcion'] }}</th>
    @endforeach
</tr>
</thead>
<tbody>
@foreach ($resultado['filas'] ?? [] as $fila)
<tr>
    <td>{{ $fila['legajo'] ?? '' }}</td>
    <td>{{ $fila['nombre'] ?? '' }}</td>
    @foreach ($resultado['columnas'] ?? [] as $col)
        @php $val = $fila['c'.$col['nro']] ?? ''; @endphp
        <td>{{ !empty($col['numerica']) && is_numeric($val) ? number_format((float) $val, 2, ',', '.') : $val }}</td>
    @endforeach
</tr>
@endforeach
</tbody>
</table>
