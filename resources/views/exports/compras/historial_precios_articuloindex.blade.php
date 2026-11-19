@php
    $modoExport = $modo ?? 'resumen';
    $mesesExport = $meses ?? [];
    $esMensualExport = $modoExport === \App\Support\Compras\HistorialPreciosArticuloFiltros::MODO_MENSUAL;
    $esMensualOcExport = $modoExport === \App\Support\Compras\HistorialPreciosArticuloFiltros::MODO_MENSUAL_OC;
    if ($esMensualOcExport) {
        $colspanExport = 7 + count($mesesExport);
    } elseif ($esMensualExport) {
        $colspanExport = 5 + count($mesesExport) + max(0, count($mesesExport) - 1) + 1;
    } else {
        $colspanExport = 14;
    }
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspanExport }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspanExport }}">
                <strong style="font-size: 16pt;">{{ $titulo }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspanExport }}">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        @if (!empty($subtitulo))
            <tr>
                <td colspan="{{ $colspanExport }}">{{ $subtitulo }}</td>
            </tr>
        @endif
        @if (!empty($totales))
            <tr>
                <td colspan="{{ $colspanExport }}">
                    Artículos: {{ (int) ($totales['total_articulos'] ?? 0) }}
                    · Filas: {{ (int) ($totales['total_compras'] ?? 0) }}
                    · Con variación: {{ (int) ($totales['con_variacion'] ?? 0) }}
                </td>
            </tr>
        @endif
        @if (!empty($total_lineas))
            <tr>
                <td colspan="{{ $colspanExport }}">Líneas: {{ (int) $total_lineas }}</td>
            </tr>
        @endif
    </tbody>
    @include('compras.historial_precios_articulo.partials.tabla_datos', [
        'filas' => $filas ?? [],
        'modo' => $modoExport,
        'meses' => $mesesExport,
        'para_pdf' => true,
        'para_excel' => true,
        'puede_ver_articulo' => false,
        'puede_ver_proveedor' => false,
        'puede_ver_recepcion' => false,
        'puede_ver_ordencompra' => false,
    ])
</table>
