@php
    $colspan = (int) ($total_columnas ?? 6);
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
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Existencias por depósito' }}</strong>
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
        @if (($total_articulos ?? 0) > 0)
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    Art&iacute;culos: {{ (int) $total_articulos }}
                    @if (! empty($totales['total_general']))
                        &middot; Total general: {{ \App\Support\Stock\ArticuloSaldosDepositoSupport::formatSaldo((float) $totales['total_general']) }}
                    @endif
                </td>
            </tr>
        @endif
    </tbody>
    @include('stock.existencias_deposito_reporte.partials.tabla_datos', [
        'depositos' => $depositos ?? collect(),
        'filas' => $filas ?? [],
        'totales' => $totales ?? [],
        'puede_ver_articulo' => false,
        'solo_thead_tbody' => true,
        'exportar_numeros_excel' => true,
    ])
</table>
