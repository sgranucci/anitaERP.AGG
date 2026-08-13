@php
    $colspan = (int) ($colspan_excel ?? 41);
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
                <strong style="font-size: 16pt;">{{ $titulo }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        @if (! empty($subtitulo))
            <tr>
                <td colspan="{{ $colspan }}">{{ $subtitulo }}</td>
            </tr>
        @endif
        @if (! empty($totales) || ! empty($kpis))
            <tr>
                <td colspan="{{ $colspan }}">
                    Líneas: {{ (int) ($kpis['cantidad_filas'] ?? $totales['cantidad_filas'] ?? 0) }}
                    · COM: {{ (int) ($kpis['cantidad_com'] ?? 0) }}
                    · Cantidad: {{ number_format((float) ($kpis['cantidad_total'] ?? 0), 2, ',', '.') }}
                    · Importe MN: {{ number_format((float) ($kpis['importe_mn'] ?? 0), 2, ',', '.') }}
                    · Con diferencias: {{ (int) ($kpis['con_diferencia'] ?? 0) }}
                    · Sin facturar: {{ (int) ($kpis['sin_facturar'] ?? 0) }}
                </td>
            </tr>
        @endif
        @if (! empty($advertencia_cotizacion))
            <tr>
                <td colspan="{{ $colspan }}">{{ $advertencia_cotizacion }}</td>
            </tr>
        @endif
        @if (! empty($total_lineas))
            <tr>
                <td colspan="{{ $colspan }}">Registros: {{ (int) $total_lineas }}</td>
            </tr>
        @endif
    </tbody>
    @include('stock.recepcion_proveedor_reporte.partials.tabla_datos', [
        'filas' => $filas ?? [],
        'modo' => $modo ?? 'detalle',
        'para_excel' => true,
        'para_pdf' => true,
        'columnas_completas' => true,
        'puede_ver_recepcion' => false,
        'puede_ver_articulo' => false,
        'puede_ver_ordencompra' => false,
        'puede_ver_requisicion' => false,
        'puede_ver_proveedor' => false,
        'puede_ver_cuentacontable' => false,
        'puede_ver_comprobante' => false,
    ])
</table>
