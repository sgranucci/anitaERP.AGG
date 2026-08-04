@php
    $columnas = is_array($columnas ?? null) ? $columnas : [];
    $filas = is_array($filas ?? null) ? $filas : [];
    $parrafos = is_array($parrafos ?? null) ? $parrafos : [];
    $resumen = is_array($resumen ?? null) ? $resumen : [];
    $tieneTabla = (bool) ($tieneTabla ?? false);
    $colspan = (int) ($colspan ?? 2);
    $interpretacion = (string) ($interpretacion ?? '');
    $pregunta = (string) ($pregunta ?? '');
    $fuente = (string) ($fuente ?? '');
    $intent = (string) ($intent ?? '');
@endphp
<table>
    <tr>
        <td colspan="{{ $colspan }}"><strong>{{ $titulo ?? 'Consulta operativa IA' }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">
            Generado {{ $generado ?? date('d/m/Y H:i') }}
            @if ($intent !== '')
                — {{ $intent }}
            @endif
            @if ($fuente !== '')
                — {{ $fuente }}
            @endif
        </td>
    </tr>
    @if ($interpretacion !== '')
        <tr>
            <td colspan="{{ $colspan }}">{{ $interpretacion }}</td>
        </tr>
    @endif
    @if ($pregunta !== '')
        <tr>
            <td colspan="{{ $colspan }}">Consulta: {{ $pregunta }}</td>
        </tr>
    @endif
    @if ($tieneTabla)
        @foreach ($parrafos as $linea)
            <tr>
                <td colspan="{{ $colspan }}">{{ $linea }}</td>
            </tr>
        @endforeach
    @endif
    @foreach ($resumen as $lineaResumen)
        <tr>
            <td colspan="{{ $colspan }}">{{ $lineaResumen }}</td>
        </tr>
    @endforeach

    @if ($tieneTabla)
        <tr>
            @foreach ($columnas as $col)
                <th>{{ $col['label'] ?? $col['key'] ?? '' }}</th>
            @endforeach
        </tr>
        @forelse ($filas as $fila)
            <tr>
                @foreach ($columnas as $col)
                    @php $key = (string) ($col['key'] ?? ''); @endphp
                    <td>{{ is_array($fila) ? ($fila[$key] ?? '') : '' }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ $colspan }}">Sin movimientos en el período.</td>
            </tr>
        @endforelse
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
                <td>Sin detalle para exportar.</td>
            </tr>
        @endforelse
    @endif
</table>
