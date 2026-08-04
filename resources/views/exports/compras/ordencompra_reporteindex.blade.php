@php
    $colspan = 36;
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
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Órdenes de compra' }}</strong>
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
                    OC: {{ (int) ($totales['total_ordenes'] ?? 0) }}
                    &middot; Cantidad: {{ number_format((float) ($totales['total_cantidad'] ?? 0), 0, ',', '.') }}
                    &middot; Pendiente: {{ number_format((float) ($totales['total_pendiente'] ?? 0), 0, ',', '.') }}
                    &middot; Tot.pend.: {{ number_format((float) ($totales['total_importe_pendiente'] ?? 0), 2, ',', '.') }}
                    &middot; Tot.OC: {{ number_format((float) ($totales['total_importe_oc'] ?? 0), 2, ',', '.') }}
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
    @include('compras.ordencompra_reporte.partials.tabla_datos', [
        'filas' => $filas ?? [],
        'solo_filas' => true,
        'cabecera_en_filas' => true,
        'para_pdf' => true,
        'para_excel' => true,
        'puede_ver_articulo' => false,
        'puede_ver_requisicion' => false,
        'puede_ver_centrocosto' => false,
        'puede_ver_ordencompra' => false,
        'puede_ver_proveedor' => false,
        'puede_ver_capex' => false,
        'puede_ver_recepcion' => false,
    ])
</table>
