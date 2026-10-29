@php
    $modo = $filtros['tipo'] ?? 'todos';
    $colspan = match ($modo) {
        'rem', 'ap', 'com' => 5,
        default => 6,
    };
    $fmt = static fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">{{ $titulo }}</strong></td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">{{ $subtitulo }}</td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                @if ($modo === 'rem')
                    Entrada: {{ $fmt($totales['entrada'] ?? 0) }}
                    · Salida: {{ $fmt($totales['salida'] ?? 0) }}
                    · Kilos: {{ $fmt($totales['kilos_rem'] ?? 0) }}
                @elseif ($modo === 'ap')
                    Entrada: {{ $fmt($totales['entrada'] ?? 0) }}
                    · Salida: {{ $fmt($totales['salida'] ?? 0) }}
                    · Kilos: {{ $fmt($totales['kilos_ap'] ?? 0) }}
                @elseif ($modo === 'com')
                    Cantidad: {{ $fmt($totales['piezas_com'] ?? 0) }}
                    · Kilos: {{ $fmt($totales['kilos_com'] ?? 0) }}
                    · Rechazo: {{ $fmt($totales['rechazo'] ?? 0) }}
                @else
                    Kilos remito: {{ $fmt($totales['kilos_rem'] ?? 0) }}
                    · Kilos alta: {{ $fmt($totales['kilos_ap'] ?? 0) }}
                    · Piezas recepción: {{ $fmt($totales['piezas_com'] ?? 0) }}
                    · Kilos recepción: {{ $fmt($totales['kilos_com'] ?? 0) }}
                @endif
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">Artículos: {{ (int) ($totales['articulos'] ?? 0) }}</td>
        </tr>
        @if (! empty($advertencias))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">{{ implode(' ', $advertencias) }}</td>
            </tr>
        @endif
    </tbody>
    @include('stock.kilos_articulo_surmar.partials.tabla_datos', [
        'filas' => $filas,
        'totales' => $totales,
        'filtros' => $filtros,
        'presentacion' => 'excel',
        'puede_ver_articulo' => false,
    ])
</table>
