<table>
    @if ($reservarFilaLogoExcel ?? false)
    <tr><td colspan="10"></td></tr>
    @endif
    <tr>
        <td colspan="10" style="font-weight:bold;font-size:16px;">
            Cierres de turno gastronomía
            @if (!empty($filtros['fecha_desde'])) — desde {{ $filtros['fecha_desde'] }} @endif
            @if (!empty($filtros['fecha_hasta'])) hasta {{ $filtros['fecha_hasta'] }} @endif
        </td>
    </tr>
    <tr>
        <th>Tipo</th>
        <th>Fecha / hora</th>
        <th>Referencia</th>
        <th>Empresa</th>
        <th>PC</th>
        <th>Turno</th>
        <th>Jornada</th>
        <th>Usuario</th>
        <th>Total</th>
        <th>Hab.</th>
    </tr>
    @foreach ($filas as $f)
    <tr>
        <td>{{ $f->tipo_etiqueta }}</td>
        <td>{{ $f->fecha_hora }}</td>
        <td>{{ $f->referencia }}</td>
        <td>{{ $f->nombreempresa }}</td>
        <td>{{ $f->identificador_pc }}</td>
        <td>{{ $f->turno_nombre }}</td>
        <td>{{ $f->fecha_jornada }}</td>
        <td>{{ $f->usuario }}</td>
        <td>{{ (float) $f->total }}</td>
        <td>{{ $f->monto_habilitacion !== null ? (float) $f->monto_habilitacion : '' }}</td>
    </tr>
    @endforeach
</table>
