@php
    $filaLogo = !empty($logos);
@endphp
<table>
    @if ($filaLogo)
        <tr>
            @foreach ($logos as $logo)
                <td><img src="{{ $logo }}" height="40"></td>
            @endforeach
        </tr>
    @endif
    <tr>
        <td colspan="9"><strong>{{ $titulo }}</strong><br>{{ $subtitulo }} · Total $ {{ number_format($totalImporte, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <th>Legajo</th>
        <th>Empleado</th>
        <th>Ingreso</th>
        <th>Categoría</th>
        <th>Agrupamiento</th>
        <th>Lugar trab.</th>
        <th>Fecha</th>
        <th>Concepto</th>
        <th>Importe</th>
    </tr>
    @foreach ($filas as $f)
        <tr>
            <td>{{ $f['legajo'] }}</td>
            <td>{{ $f['nombre'] }}</td>
            <td>{{ $f['fecha_ingreso'] ?? '' }}</td>
            <td>{{ $f['categoria'] ?? '' }}</td>
            <td>{{ $f['agrupamiento'] ?? '' }}</td>
            <td>{{ $f['lugar_trabajo'] ?? '' }}</td>
            <td>{{ $f['fecha'] ?? '' }}</td>
            <td>{{ $f['concepto'] ?? '' }}</td>
            <td>{{ (float) ($f['importe'] ?? 0) }}</td>
        </tr>
    @endforeach
</table>
