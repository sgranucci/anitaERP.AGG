<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px 3px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas > th { background-color: #85C1E9; font-size: 7px; font-weight: bold; color: #17202A; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data tbody tr.mcc-fila-header-empresa { background-color: #E5E7E9; }
        table.data tbody tr.mcc-fila-total-nivel { background-color: #EAF2F8; }
        table.data tbody tr.mcc-fila-total-general { background-color: #AED6F1; }
        .text-right { text-align: right !important; white-space: nowrap; }
        .text-center { text-align: center; }
        .text-muted { color: #666666; }
        .badge { font-size: 6px; border: 1px solid #B7950B; padding: 0 2px; }
    </style>
</head>
<body>
    <table class="data">
        @include('contable.movccosto.conciliacion.partials.tabla', [
            'filas' => $filas,
            'solo_resumen' => $solo_resumen,
            'multiempresa' => $multiempresa,
            'enlaces' => false,
            'pdf_cabecera' => [
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ],
        ])
    </table>
</body>
</html>
