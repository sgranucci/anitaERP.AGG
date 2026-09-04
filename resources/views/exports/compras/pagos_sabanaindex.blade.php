@php
    $columnasExcel = $columnas ?? [];
    $colspan = max(1, count($columnasExcel));
    $importesTotales = $totales['importes'] ?? [];
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
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Pagos x Fecha de Movimiento' }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                Generado {{ date('d/m/Y H:i') }}
            </td>
        </tr>
        @if (! empty($subtitulo))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">{{ $subtitulo }}</td>
            </tr>
        @endif
        @if (! empty($totales))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    Movimientos: {{ (int) ($total_lineas ?? 0) }}
                    &middot; Total pago:
                    {{ number_format((float) ($importesTotales['total_pago'] ?? $totales['total_pago'] ?? 0), 2, ',', '.') }}
                </td>
            </tr>
        @endif
    </tbody>
    @include('compras.pagos_sabana_reporte.partials.tabla_datos', [
        'filas' => $filas ?? [],
        'columnas' => $columnasExcel,
        'solo_filas' => true,
        'cabecera_en_filas' => true,
        'para_excel' => true,
        'para_export' => true,
        'puede_ver_proveedor' => false,
        'puede_ver_pagoproveedor' => false,
        'puede_ver_ingresoegreso' => false,
        'puede_ver_comprobante' => false,
        'puede_ver_ordencompra' => false,
        'puede_ver_solicitudpago' => false,
    ])
</table>
