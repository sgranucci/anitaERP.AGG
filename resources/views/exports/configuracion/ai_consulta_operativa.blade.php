@php
    $tabla = is_array($tabla ?? null) ? $tabla : null;
    $columnas = is_array($tabla['columnas'] ?? null) ? $tabla['columnas'] : [];
    $filas = is_array($tabla['filas'] ?? null) ? $tabla['filas'] : [];
    $tieneTabla = $columnas !== [] && $filas !== [];
    $colspan = $tieneTabla ? max(count($columnas), 2) : 2;
@endphp
<table>
    <tr>
        <td colspan="{{ $colspan }}"><strong>{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">
            Generado {{ $generado }}
            @if ($interpretacion !== '') — {{ $interpretacion }} @endif
            @if ($intent !== '') — intent: {{ $intent }} @endif
            @if ($fuente !== '') — fuente: {{ $fuente }} @endif
        </td>
    </tr>
    @if ($pregunta !== '')
        <tr>
            <td colspan="{{ $colspan }}">Pregunta: {{ $pregunta }}</td>
        </tr>
    @endif

    @if ($tieneTabla)
        @foreach ($parrafos as $linea)
            <tr>
                <td colspan="{{ $colspan }}">{{ $linea }}</td>
            </tr>
        @endforeach
        <tr>
            @foreach ($columnas as $col)
                <th>{{ $col['label'] ?? $col['key'] ?? '' }}</th>
            @endforeach
        </tr>
        @foreach ($filas as $fila)
            <tr>
                @foreach ($columnas as $col)
                    @php $key = (string) ($col['key'] ?? ''); @endphp
                    <td>{{ is_array($fila) ? ($fila[$key] ?? '') : '' }}</td>
                @endforeach
            </tr>
        @endforeach
    @else
        <tr>
            <th>#</th>
            <th>Detalle</th>
        </tr>
        @forelse ($parrafos as $i => $linea)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $linea }}</td>
            </tr>
        @empty
            <tr>
                <td></td>
                <td>Sin filas</td>
            </tr>
        @endforelse
    @endif

    @if (! empty($datos) && is_array($datos))
        <tr>
            <td colspan="{{ $colspan }}"></td>
        </tr>
        <tr>
            <th colspan="{{ $colspan }}">Resumen</th>
        </tr>
        @foreach ($datos as $clave => $valor)
            @if (! is_array($valor))
                <tr>
                    <td>{{ $clave }}</td>
                    <td colspan="{{ max(1, $colspan - 1) }}">{{ $valor }}</td>
                </tr>
            @endif
        @endforeach
    @endif
</table>
