@php
    $titulo = 'Análisis de entregas y pendientes';
    $totalFilas = is_countable($filas) ? count($filas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #17202A; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data thead { display: table-header-group; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 2px 3px; vertical-align: top; }
        table.data thead tr.columnas > th { background-color: #85C1E9; color: #17202A; font-size: 7px; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
<table class="data">
    <thead>
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => $titulo,
            'subtitulo' => trim(($subtitulo ?? '').' '.(\App\Support\Ventas\AnalisisEntregasReporte::resumenTexto($resumen ?? []))),
            'colspan' => 24,
            'logosCabecera' => $logosCabecera ?? [],
            'totalFilas' => $totalFilas,
        ])
        <tr class="columnas">
            @include('ventas.analisis_entregas.partials.columnas')
        </tr>
    </thead>
    <tbody>
        @include('ventas.analisis_entregas.partials.filas', [
            'filas' => $filas,
            'puede_ver_pedido' => false,
            'puede_ver_cliente' => false,
            'puede_ver_articulo' => false,
        ])
    </tbody>
</table>
</body>
</html>
