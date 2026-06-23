@php
    $colspan = 7;
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
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
            <td colspan="{{ $colspan }}">
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Kilos por categoría' }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                Generado {{ date('d/m/Y H:i') }}
            </td>
        </tr>
        @if (! empty($subtitulo))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    {{ $subtitulo }}
                </td>
            </tr>
        @endif
        @if (! empty($totales))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    Piezas: {{ $formatear($totales['total_pieza'] ?? 0) }}
                    &middot; Kilos: {{ $formatear($totales['total_kilo'] ?? 0) }}
                    &middot; Cajas: {{ $formatear($totales['total_caja'] ?? 0) }}
                </td>
            </tr>
        @endif
        @if (($total_lineas ?? 0) > 0)
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    L&iacute;neas detalle: {{ (int) $total_lineas }}
                </td>
            </tr>
        @endif
    </tbody>
    @include('ventas.repkilocategoria.partials.tabla_datos', [
        'filas' => $filas,
        'para_pdf' => true,
    ])
</table>
