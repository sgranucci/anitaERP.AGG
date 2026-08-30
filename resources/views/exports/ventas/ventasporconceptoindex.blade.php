@php
    $colspan = 12;
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
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Ventas por concepto' }}</strong>
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
                    Neto: {{ $formatear($totales['neto'] ?? 0) }}
                    &middot; IVA: {{ $formatear($totales['iva'] ?? 0) }}
                    &middot; Total: {{ $formatear($totales['total'] ?? 0) }}
                </td>
            </tr>
        @endif
        @if (($total_lineas ?? 0) > 0)
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    Renglones detalle: {{ (int) $total_lineas }}
                </td>
            </tr>
        @endif
    </tbody>
    @include('ventas.ventas_por_concepto.partials.tabla_datos', [
        'filas' => $filas ?? [],
        'para_pdf' => true,
        'puede_ver_venta' => false,
        'puede_ver_cliente' => false,
        'puede_ver_concepto' => false,
    ])
</table>
