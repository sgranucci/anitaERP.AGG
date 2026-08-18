<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endif
    <tr>
        <td>{{ $titulo }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>
    <tr>
        <td>Generado {{ now()->format('d/m/Y H:i') }}{{ $subtitulo ? ' · '.$subtitulo : '' }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>
    <tr>
        <td>Col.</td>
        <td>Descripción</td>
        <td>ERP</td>
        <td>Anita</td>
        <td>Diferencia</td>
        <td>Tolerancia</td>
        <td>Estado</td>
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila->columna_nro }}</td>
            <td>{{ $fila->columna_descripcion }}</td>
            <td>{{ $fila->total_erp }}</td>
            <td>{{ $fila->total_anita }}</td>
            <td>{{ $fila->diferencia }}</td>
            <td>{{ $fila->tolerancia }}</td>
            <td>{{ $fila->coincide ? 'Coincide' : 'Diferencia' }}</td>
        </tr>
    @endforeach
</table>
