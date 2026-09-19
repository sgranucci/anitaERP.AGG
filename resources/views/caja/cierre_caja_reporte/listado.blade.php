@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $filas = $filas ?? [];
    $titulo = $titulo ?? 'Cierre de caja';
    $subtitulo = $subtitulo ?? '';
    $resultado = $resultado ?? [];
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
        collect($filas)->map(fn ($f) => (object) $f)
    );
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @include('includes.reportes.estilos_pdf_pagina', [
            'pdf_size' => 'legal landscape',
            'pdf_margin' => '14mm 16mm',
        ])
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 7px;
            color: #222;
        }
        .bloque-cierre { margin: 0 0 10px 0; width: 100%; page-break-inside: auto; }
        table.titulo-bloque { width: 100%; border-collapse: collapse; margin: 0 0 3px 0; table-layout: fixed; }
        table.titulo-bloque td {
            background: #D6EAF8;
            color: #17202A;
            font-weight: bold;
            font-size: 10px;
            padding: 4px 6px;
            border: 1px solid #85C1E9;
        }
        table.data {
            border-collapse: collapse;
            width: 100%;
            margin: 0;
            table-layout: fixed;
        }
        table.data th, table.data td {
            border: 1px solid #cccccc;
            padding: 2px 3px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            overflow: hidden;
        }
        table.data thead th { background: #85C1E9; color: #17202A; font-size: 6.5px; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        table.data tr.total td { background: #D5D8DC !important; font-weight: bold; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
<table class="marco-pdf"><tr>
    <td class="marco-lat"></td>
    <td class="marco-centro">
    <table style="width:100%; margin-bottom: 8px;">
        <tr>
            <td style="width:20%;">
                @foreach ($logos as $logo)
                    @if (!empty($logo['uri']))
                        <img src="{{ $logo['uri'] }}" style="max-height:40px;">
                    @endif
                @endforeach
            </td>
            <td style="text-align:center;">
                <h2 style="margin:0;font-size:16px;">{{ $titulo }}</h2>
                <div>Generado {{ date('d/m/Y H:i') }}</div>
                <div>{{ $subtitulo }}</div>
            </td>
            <td style="width:20%;"></td>
        </tr>
    </table>

    @include('caja.cierre_caja_reporte.partials.secciones_export', [
        'resultado' => $resultado,
        'esPdf' => true,
    ])
    </td>
    <td class="marco-lat"></td>
</tr></table>
</body>
</html>
