@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas ?? []);
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $modo = $filtros['tipo'] ?? 'todos';
    $colspan = match ($modo) {
        'rem', 'ap', 'com' => 5,
        default => 6,
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Kilos por artículo' }}</title>
    <style>
        @include('includes.reportes.estilos_pdf_pagina', [
            'pdf_size' => 'legal landscape',
            'pdf_margin' => '14mm 16mm',
        ])
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px 4px;
            vertical-align: top;
        }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas > th {
            background-color: #85C1E9;
            font-size: 8px;
            font-weight: bold;
            color: #17202A;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
<table class="data">
    <thead>
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => $titulo ?? 'Kilos por artículo',
            'subtitulo' => $subtitulo ?? '',
            'colspan' => $colspan,
            'logosCabecera' => $logosCabecera,
            'totalFilas' => $totalFilas,
        ])
        @include('stock.kilos_articulo_surmar.partials.tabla_datos', [
            'filas' => $filas,
            'totales' => $totales,
            'filtros' => $filtros,
            'presentacion' => 'pdf',
            'puede_ver_articulo' => false,
            'parte' => 'columnas',
        ])
    </thead>
    @include('stock.kilos_articulo_surmar.partials.tabla_datos', [
        'filas' => $filas,
        'totales' => $totales,
        'filtros' => $filtros,
        'presentacion' => 'pdf',
        'puede_ver_articulo' => false,
        'parte' => 'cuerpo',
    ])
</table>
</body>
</html>
