@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
    $tituloReporte = $titulo ?? 'Reporte de costos del local';
    $subtituloReporte = $subtitulo ?? '';
    $totalFilas = count($resultado['filas'] ?? []);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        @include('includes.reportes.estilos_pdf_pagina', [
            'pdf_size' => 'legal landscape',
            'pdf_margin' => '14mm 16mm',
        ])
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px 4px;
            vertical-align: top;
            font-size: 7px;
        }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas > th {
            background-color: #85C1E9;
            font-weight: bold;
            color: #17202A;
        }
        table.data td.text-right, table.data th.text-right { text-align: right; white-space: nowrap; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
    </style>
</head>
<body>
<table class="data">
    <thead>
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => $tituloReporte,
            'subtitulo' => $subtituloReporte,
            'colspan' => 8,
            'logosCabecera' => $logosCabecera,
            'totalFilas' => $totalFilas,
        ])
        <tr class="columnas">
            <th>SKU</th>
            <th>Descripción</th>
            <th>Marca</th>
            <th>Canal</th>
            <th>Estado</th>
            <th class="text-right">Precio fábrica</th>
            <th class="text-right">Costo local</th>
            <th>Aviso</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($resultado['filas'] ?? [] as $fila)
            <tr style="{{ ! empty($fila['sin_precio']) ? 'background:#FDEBD0;' : '' }}">
                <td>{{ $fila['sku'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>{{ $fila['marca'] ?? '' }}</td>
                <td>{{ $fila['canal'] ?? '' }}</td>
                <td>{{ $fila['estado'] ?? '' }}</td>
                <td class="text-right">{{ number_format((float) ($fila['precio_fabrica'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) ($fila['costo'] ?? 0), 2, ',', '.') }}</td>
                <td>{{ ! empty($fila['sin_precio']) ? 'Precio en 0' : '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
