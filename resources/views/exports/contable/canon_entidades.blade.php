@php
    $resultado = $resultado ?? [];
    $identidad = $resultado['identidad'] ?? [];
    $totales = $resultado['totales'] ?? [];
    $conciliacion = $resultado['conciliacion'] ?? [];
    $filas = $resultado['filas'] ?? [];
    $bingoEscalonado = ! empty($identidad['bingo_escalonado']);
    $colspan = $bingoEscalonado ? 11 : 9;
@endphp
<table>
    <tr>
        <td colspan="{{ $colspan }}"><strong>{{ $titulo ?? 'F2015 · Canon entidades' }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim($subtitulo ?? '') !== '')
        <tr>
            <td colspan="{{ $colspan }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}">
            {{ $identidad['nombre'] ?? '' }}
            · {{ $identidad['codigo'] ?? '' }}
            · CUIT {{ $identidad['cuit_formato'] ?? '' }}
            · Bingo {{ $identidad['etiqueta_bingo'] ?? '' }}
        </td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">
            Máquinas {{ number_format((float) ($totales['canon_maq'] ?? 0), 2, ',', '.') }}
            · Bingo {{ number_format((float) ($totales['canon_bin'] ?? 0), 2, ',', '.') }}
            · Total {{ number_format((float) ($totales['canon_total'] ?? 0), 2, ',', '.') }}
            · Σ Haber {{ number_format((float) ($conciliacion['haber_total'] ?? 0), 2, ',', '.') }}
            · Dif. {{ number_format((float) ($conciliacion['diferencia'] ?? 0), 2, ',', '.') }}
            · {{ ! empty($conciliacion['cuadra']) ? 'Conforme' : 'Desvío' }}
        </td>
    </tr>
    <tr>
        <th>Fecha</th>
        <th>Win Electrónico</th>
        <th>Canon máquinas</th>
        <th>Ventas bingo</th>
        @if ($bingoEscalonado)
            <th>Bingo 2%</th>
            <th>Bingo 3,25%</th>
        @endif
        <th>Canon bingo</th>
        <th>Total día</th>
        <th>Σ Haber día</th>
        <th>Dif. día</th>
        <th>Estado</th>
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila['fecha'] ?? '' }}</td>
            <td>{{ (float) ($fila['win_electronico'] ?? 0) }}</td>
            <td>{{ (float) ($fila['canon_maq'] ?? 0) }}</td>
            <td>{{ (float) ($fila['ventas_bingo'] ?? 0) }}</td>
            @if ($bingoEscalonado)
                <td>{{ (float) ($fila['bingo_tramo_2'] ?? 0) }}</td>
                <td>{{ (float) ($fila['bingo_tramo_325'] ?? 0) }}</td>
            @endif
            <td>{{ (float) ($fila['canon_bin'] ?? 0) }}</td>
            <td>{{ (float) ($fila['canon_total'] ?? 0) }}</td>
            <td>{{ (float) ($fila['haber_total'] ?? 0) }}</td>
            <td>{{ (float) ($fila['dif_dia'] ?? 0) }}</td>
            <td>
                @if (empty($fila['tiene_flash']))
                    Sin flash
                @elseif (! empty($fila['excluido_maq']))
                    Win ≤ 0 · excluido
                @endif
            </td>
        </tr>
    @endforeach
    <tr>
        <td>Totales</td>
        <td>{{ (float) ($totales['base_maq'] ?? 0) }}</td>
        <td>{{ (float) ($totales['canon_maq'] ?? 0) }}</td>
        <td>{{ (float) ($totales['base_bingo'] ?? 0) }}</td>
        @if ($bingoEscalonado)
            <td></td>
            <td></td>
        @endif
        <td>{{ (float) ($totales['canon_bin'] ?? 0) }}</td>
        <td>{{ (float) ($totales['canon_total'] ?? 0) }}</td>
        <td>{{ (float) ($conciliacion['haber_total'] ?? 0) }}</td>
        <td>{{ (float) ($conciliacion['diferencia'] ?? 0) }}</td>
        <td></td>
    </tr>
</table>
