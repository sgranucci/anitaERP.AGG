@php
    $colspan = 9;
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
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Comisiones de vendedores — detalle' }}</strong>
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
                    Gravado: {{ $formatear($totales['gravado'] ?? 0) }}
                    &middot; Comisi&oacute;n: {{ $formatear($totales['comision'] ?? 0) }}
                </td>
            </tr>
        @endif
        @if (($total_lineas ?? 0) > 0)
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    Comprobantes: {{ (int) $total_lineas }}
                </td>
            </tr>
        @endif
    </tbody>
    @include('ventas.comision_vendedor_detalle.partials.tabla_datos', [
        'filas' => $filas ?? [],
        'para_excel' => true,
        'para_pdf' => true,
        'puede_ver_venta' => false,
        'puede_ver_cliente' => false,
        'puede_ver_vendedor' => false,
    ])
</table>
