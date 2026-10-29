<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('includes.reportes.estilos_pdf_pagina', ['pdf_size' => 'legal landscape'])
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #17202A; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 2px 3px; }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas > th { background-color: #85C1E9; color: #17202A; font-weight: bold; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        .rojo { color: #c0392b; }
    </style>
</head>
<body>
@php
    $medidasCols = $medidas_columnas ?? [];
    $esMovimientos = ! empty($es_movimientos);
    $colspan = 10 + count($medidasCols) + ($esMovimientos ? 2 : 0);
@endphp
<table class="data">
    <thead>
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => $titulo ?? 'Stock por OT',
            'subtitulo' => $subtitulo ?? '',
            'colspan' => $colspan,
            'totalFilas' => $total_filas ?? count($filas ?? []),
        ])
        <tr class="columnas">
            <th>Línea</th>
            <th>Art.</th>
            <th>Descripción</th>
            @foreach ($medidasCols as $medida)
                <th>{{ $medida }}</th>
            @endforeach
            <th>PS</th>
            <th>QM</th>
            <th>N</th>
            <th>TT</th>
            <th>Precio</th>
            <th>Situación</th>
            <th>OT / lote</th>
            <th>Depósito</th>
            @if ($esMovimientos)
                <th>Módulo</th>
                <th>Pedido</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $lote)
            @php
                $rojo = \App\Support\Stock\ReporteStockOtSituacionSupport::colorearRojoEnExcel(
                    (string) ($lote['situacion'] ?? ''),
                    ! empty($lote['en_produccion'])
                );
            @endphp
            <tr @if($rojo) class="rojo" @endif>
                @include('stock.repstockot.partials.fila_celdas', [
                    'lote' => $lote,
                    'imprimefoto' => 'SIN_FOTO',
                    'medidas_columnas' => $medidasCols,
                    'es_movimientos' => $esMovimientos,
                ])
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
