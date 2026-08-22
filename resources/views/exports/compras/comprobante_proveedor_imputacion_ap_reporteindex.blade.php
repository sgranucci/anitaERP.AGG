<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="16" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="16">
                <strong style="font-size: 16pt;">{{ $titulo }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="16">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        @if (!empty($subtitulo))
            <tr>
                <td colspan="16">{{ $subtitulo }}</td>
            </tr>
        @endif
        @if (!empty($totales))
            <tr>
                <td colspan="16">
                    Filas: {{ (int) ($totales['total_filas'] ?? 0) }}
                    · Distorsión: {{ (int) ($totales['con_distorsion'] ?? 0) }}
                    · Sin asiento: {{ (int) ($totales['sin_asiento'] ?? 0) }}
                    · Dif. $: {{ number_format((float) ($totales['diferencia_ars'] ?? 0), 2, ',', '.') }}
                </td>
            </tr>
        @endif
        @if (!empty($total_lineas))
            <tr>
                <td colspan="16">Líneas: {{ (int) $total_lineas }}</td>
            </tr>
        @endif
    </tbody>
    @include('compras.comprobante_proveedor_imputacion_ap_reporte.partials.tabla_datos', [
        'filas' => $filas ?? [],
        'para_pdf' => true,
        'para_excel' => true,
        'puede_ver_comprobante' => false,
        'puede_ver_proveedor' => false,
        'puede_ver_asiento' => false,
        'puede_ver_pagoproveedor' => false,
    ])
</table>
