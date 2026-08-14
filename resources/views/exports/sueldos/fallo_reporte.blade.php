<table>
    <tr>
        <td colspan="11"><strong>{{ $titulo }}</strong><br>{{ $subtitulo }} ·
            Debe {{ number_format($resultado['total_debe'], 2, ',', '.') }} /
            Haber {{ number_format($resultado['total_haber'], 2, ',', '.') }} /
            Saldo {{ number_format($resultado['total_saldo'], 2, ',', '.') }}
        </td>
    </tr>
    <tr>
        <th>Legajo</th>
        <th>Empleado</th>
        <th>Ingreso</th>
        <th>Categoría</th>
        <th>Agrupamiento</th>
        <th>Lugar</th>
        <th>Fecha</th>
        <th>Concepto</th>
        <th>Debe</th>
        <th>Haber</th>
        <th>Observación</th>
    </tr>
    @foreach ($filas as $f)
        <tr>
            <td>{{ $f['legajo'] }}</td>
            <td>{{ $f['nombre'] }}</td>
            <td>{{ $f['fecha_ingreso'] ?? '' }}</td>
            <td>{{ $f['categoria'] ?? '' }}</td>
            <td>{{ $f['agrupamiento'] ?? '' }}</td>
            <td>{{ $f['lugar_trabajo'] ?? '' }}</td>
            <td>{{ $f['fecha_fmt'] ?? '' }}</td>
            <td>{{ $f['concepto'] ?? '' }}</td>
            <td>{{ (float) ($f['debe'] ?? 0) }}</td>
            <td>{{ (float) ($f['haber'] ?? 0) }}</td>
            <td>{{ $f['observacion'] ?? '' }}</td>
        </tr>
    @endforeach
</table>
