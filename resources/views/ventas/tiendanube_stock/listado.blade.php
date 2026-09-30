@php
    $totalFilas = is_countable($lineas) ? count($lineas) : 0;
    $filasPdf = [];
    foreach ($lineas as $linea) {
        $filasPdf[] = ['type' => 'row', 'row' => $linea];
    }
    $lotesPdf = \App\Support\Listado\ListadoPdfRapidoSupport::lotes($filasPdf);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo }}</title>
    <style>
        @page { size: legal landscape; margin: 14mm 10mm 16mm 10mm; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; margin: 0; padding: 0; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
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
        .text-right { text-align: right; }
    </style>
</head>
<body>
@foreach ($lotesPdf as $indiceLote => $lote)
<table class="data">
    <thead>
        @if ($indiceLote === 0)
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'colspan' => 9,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
        @endif
        <tr class="columnas">
            <th style="width: 10%;">SKU</th>
            <th style="width: 14%;">Variante</th>
            <th style="width: 8%;">Combinaci&oacute;n</th>
            <th style="width: 6%;">Talle</th>
            <th style="width: 7%;">Stock</th>
            <th style="width: 9%;">Precio</th>
            <th style="width: 10%;">Promocional</th>
            <th style="width: 8%;">Estado</th>
            <th style="width: 28%;">Detalle</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lote as $item)
            @php $linea = $item['row']; @endphp
            <tr>
                <td>{{ $linea->sku }}</td>
                <td>{{ $linea->variante_sku }}</td>
                <td>{{ $linea->combinacion_codigo }}</td>
                <td>{{ $linea->talle }}</td>
                <td class="text-right">{{ $linea->stock !== null ? (int) $linea->stock : '' }}</td>
                <td class="text-right">{{ $linea->precio !== null ? number_format((float) $linea->precio, 2, ',', '.') : '' }}</td>
                <td class="text-right">{{ $linea->precio_promocional !== null ? number_format((float) $linea->precio_promocional, 2, ',', '.') : '' }}</td>
                <td>{{ \App\Models\Ventas\TiendanubeStockSubidaLinea::etiquetaEstado($linea->estado) }}</td>
                <td>{{ $linea->mensaje }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endforeach
</body>
</html>
