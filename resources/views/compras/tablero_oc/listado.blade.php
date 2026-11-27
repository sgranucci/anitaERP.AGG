<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Compras' }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; line-height: 1.25; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; margin-bottom: 0; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 1px 2px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 6px;
        }
        table.data tbody tr.par { background-color: #f5f5f5; }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas > th { background-color: #85C1E9; font-size: 6px; font-weight: bold; color: #17202A; }
        .text-right { text-align: right; white-space: nowrap; }
        .text-nowrap { white-space: nowrap; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
@php
    // Anchos (%) por columna del PDF; con una sola empresa se quita esa columna y se reparte.
    $anchos = ($ocultar_empresa ?? false)
        ? [5, 4.5, 8, 15, 5.5, 20, 5, 3, 6.5, 7.5, 5.5, 4.5, 5, 5]
        : [5, 4.5, 6.5, 7.5, 12, 5.5, 17, 5, 3, 6.5, 7.5, 5.5, 4.5, 5, 5];
    $lotes = array_chunk($filas ?? [], \App\Support\Listado\ListadoPdfRapidoSupport::FILAS_POR_TABLA);
    if ($lotes === []) {
        $lotes = [[]];
    }
@endphp
@foreach ($lotes as $i => $lote)
    <table class="data">
        <colgroup>
            @foreach ($anchos as $ancho)
                <col style="width:{{ $ancho }}%">
            @endforeach
        </colgroup>
        @include('compras.tablero_oc.partials.tabla_detalle', [
            'filas' => $lote,
            'pantalla' => false,
            'para_pdf' => true,
            'mostrar_cabecera' => ($mostrar_cabecera ?? true) && $i === 0,
            'ocultar_empresa' => $ocultar_empresa ?? false,
            'titulo' => $titulo ?? '',
            'subtitulo' => $subtitulo ?? '',
            'logosCabecera' => $logosCabecera ?? [],
            'totalFilas' => $totalFilas ?? 0,
        ])
    </table>
@endforeach
</body>
</html>
