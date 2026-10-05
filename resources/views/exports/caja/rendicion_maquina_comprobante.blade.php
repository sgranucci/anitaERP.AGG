@php
    $filas = $filas ?? [];
@endphp
<table>
    @foreach ($filas as $fila)
        @php
            $celdas = $fila['celdas'] ?? [];
            $tipo = $fila['tipo'] ?? '';
            $unaCelda = in_array($tipo, ['logo', 'titulo', 'subtitulo', 'seccion', 'texto'], true);
        @endphp
        <tr>
            @if ($unaCelda)
                <td colspan="3">{{ $celdas[0] ?? '' }}</td>
            @else
                <td>{{ $celdas[0] ?? '' }}</td>
                <td>{{ $celdas[1] ?? '' }}</td>
                <td>{{ $celdas[2] ?? '' }}</td>
            @endif
        </tr>
    @endforeach
</table>
