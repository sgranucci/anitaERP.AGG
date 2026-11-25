@php
    use App\Support\Sueldos\ComparativoConceptosListadoFiltros as F;
    $columnas = $resultado['columnas'] ?? [];
    $formato = $resultado['formato'] ?? F::FORMATO_IMPORTE;
    $muestraDif = ! empty($resultado['muestra_diferencia']);
    $mostrarCant = in_array($formato, [F::FORMATO_CANTIDAD, F::FORMATO_AMBOS], true);
    $mostrarImp = in_array($formato, [F::FORMATO_IMPORTE, F::FORMATO_AMBOS], true);
    $filas = $resultado['filas'] ?? [];
@endphp
<table>
    <tr>
        <td colspan="12">
            <strong>{{ $titulo }}</strong><br>
            {{ $subtitulo }} · {{ $resultado['totales']['filas_dato'] ?? 0 }} filas
        </td>
    </tr>
    <tr></tr>
    <tr>
        <th>N.Leg.</th>
        <th>Nombre</th>
        <th>F.Ingr.</th>
        <th>C.Costo</th>
        <th>Categoría</th>
        <th>Sindicato</th>
        <th>N.Con.</th>
        <th>Concepto</th>
        @foreach ($columnas as $col)
            @if ($formato === F::FORMATO_AMBOS)
                <th>{{ $col['etiqueta'] }} Cant</th>
                <th>{{ $col['etiqueta'] }} Imp</th>
            @elseif ($formato === F::FORMATO_CANTIDAD)
                <th>{{ $col['etiqueta'] }} Cant</th>
            @else
                <th>{{ $col['etiqueta'] }} Imp</th>
            @endif
        @endforeach
        @if ($muestraDif)
            @if ($mostrarCant)<th>Dif. Cant</th>@endif
            @if ($mostrarImp)<th>Dif. Imp</th>@endif
        @endif
        @if ($mostrarCant)<th>Total Cant</th>@endif
        @if ($mostrarImp)<th>Total Imp</th>@endif
    </tr>
    @foreach ($filas as $f)
        @if (($f['tipo'] ?? '') === 'grupo')
            <tr>
                <td colspan="8"><strong>{{ $f['grupo_label'] ?? '' }}</strong></td>
            </tr>
        @elseif (($f['tipo'] ?? '') === 'total_grupo' || !empty($f['es_total']))
            <tr>
                <td></td>
                <td><strong>{{ $f['nombre'] ?? 'Total' }}</strong></td>
                <td></td><td></td><td></td><td></td><td></td><td></td>
                @foreach ($columnas as $col)
                    @php $v = $f['valores'][$col['clave']] ?? ['importe' => 0, 'cantidad' => 0]; @endphp
                    @if ($mostrarCant)<td>{{ (float) ($v['cantidad'] ?? 0) }}</td>@endif
                    @if ($mostrarImp)<td>{{ (float) ($v['importe'] ?? 0) }}</td>@endif
                @endforeach
                @if ($muestraDif)
                    @if ($mostrarCant)<td>{{ (float) ($f['diferencia_cantidad'] ?? 0) }}</td>@endif
                    @if ($mostrarImp)<td>{{ (float) ($f['diferencia_importe'] ?? 0) }}</td>@endif
                @endif
                @if ($mostrarCant)<td>{{ (float) ($f['total_cantidad'] ?? 0) }}</td>@endif
                @if ($mostrarImp)<td>{{ (float) ($f['total_importe'] ?? 0) }}</td>@endif
            </tr>
        @else
            <tr>
                <td>{{ $f['legajo'] ?? '' }}</td>
                <td>{{ $f['nombre'] ?? '' }}</td>
                <td>{{ $f['fecha_ingreso'] ?? '' }}</td>
                <td>{{ $f['centrocosto'] ?? '' }}</td>
                <td>{{ $f['categoria'] ?? '' }}</td>
                <td>{{ $f['sindicato'] ?? '' }}</td>
                <td>{{ $f['concepto_codigo'] ?? '' }}</td>
                <td>{{ $f['concepto_descripcion'] ?? '' }}</td>
                @foreach ($columnas as $col)
                    @php $v = $f['valores'][$col['clave']] ?? ['importe' => 0, 'cantidad' => 0]; @endphp
                    @if ($mostrarCant)<td>{{ (float) ($v['cantidad'] ?? 0) }}</td>@endif
                    @if ($mostrarImp)<td>{{ (float) ($v['importe'] ?? 0) }}</td>@endif
                @endforeach
                @if ($muestraDif)
                    @if ($mostrarCant)<td>{{ (float) ($f['diferencia_cantidad'] ?? 0) }}</td>@endif
                    @if ($mostrarImp)<td>{{ (float) ($f['diferencia_importe'] ?? 0) }}</td>@endif
                @endif
                @if ($mostrarCant)<td>{{ (float) ($f['total_cantidad'] ?? 0) }}</td>@endif
                @if ($mostrarImp)<td>{{ (float) ($f['total_importe'] ?? 0) }}</td>@endif
            </tr>
        @endif
    @endforeach
</table>
