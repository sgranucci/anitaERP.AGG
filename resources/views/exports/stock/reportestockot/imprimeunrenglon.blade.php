@php
    $rojoExcel = \App\Support\Stock\ReporteStockOtSituacionSupport::colorearRojoEnExcel(
        (string) ($lote['situacion'] ?? ''),
        ! empty($lote['en_produccion'])
    );
@endphp
<tr @if($rojoExcel) style="color:#FF0000;" @endif>
    @include('stock.repstockot.partials.fila_celdas', [
        'lote' => $lote,
        'imprimefoto' => $imprimefoto ?? '',
        'medidas_columnas' => $medidas_columnas ?? [],
        'es_movimientos' => $es_movimientos ?? false,
    ])
</tr>
