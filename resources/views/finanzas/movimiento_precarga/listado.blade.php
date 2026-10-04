@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    foreach ($datas as $row) {
        $row->nombreempresa = $row->empresa->nombre ?? '';
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Precargas de cash flow</title>
    <style>
        @include('includes.reportes.estilos_pdf_pagina', [
            'pdf_size' => 'legal landscape',
            'pdf_margin' => '14mm 16mm',
        ])
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data thead { display: table-header-group; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr.columnas > th {
            background-color: #85C1E9;
            font-size: 7px;
            font-weight: bold;
            color: #17202A;
        }
        td.num { text-align: right; }
    </style>
</head>
<body>
<table class="data">
    <thead>
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => 'Precargas de cash flow',
            'subtitulo' => $subtitulo ?? '',
            'colspan' => 12,
            'logosCabecera' => $logosCabecera,
            'totalFilas' => $totalFilas,
        ])
        <tr class="columnas">
            <th>ID</th>
            <th>Fecha</th>
            <th>Empresa</th>
            <th>Tipo</th>
            <th>Rubro</th>
            <th>Detalle</th>
            <th>Cuenta</th>
            <th>Monto</th>
            <th>Cotiz.</th>
            <th>Moneda</th>
            <th>Estado</th>
            <th>Comprobante</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ $row->fecha?->format('d/m/Y') }}</td>
                <td>{{ $row->empresa->nombre ?? '' }}</td>
                <td>{{ $row->etiquetaTipo() }}</td>
                <td>{{ $row->etiquetaRubro() }}</td>
                <td>{{ $row->detalle }}</td>
                <td>{{ $row->etiquetaCuenta() }}</td>
                <td class="num">{{ number_format((float) $row->monto, 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $row->cotizacion, 4, ',', '.') }}</td>
                <td>{{ $row->moneda->abreviatura ?? '' }}</td>
                <td>{{ $row->etiquetaEstado() }}</td>
                <td>{{ $row->cajaMovimiento->numerotransaccion ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
