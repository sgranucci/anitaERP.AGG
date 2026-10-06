@php
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $lotesPdf = \App\Support\Listado\ListadoPdfRapidoSupport::lotes($filas);
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
    </style>
</head>
<body>
@foreach ($lotesPdf as $indiceLote => $lote)
<table class="data">
    <thead>
        @if ($indiceLote === 0)
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => $titulo,
                'subtitulo' => $subtitulo ?? '',
                'colspan' => 8,
                'logosCabecera' => $logosCabecera ?? [],
                'totalFilas' => $totalFilas,
            ])
        @endif
        <tr class="columnas">
            <th style="width: 12%;">Sala</th>
            <th style="width: 10%;">Origen</th>
            <th style="width: 10%;">Cuenta Wigos</th>
            <th style="width: 22%;">Nombre y apellido</th>
            <th style="width: 18%;">Alias</th>
            <th style="width: 12%;">Nivel</th>
            <th style="width: 6%;">VIP</th>
            <th style="width: 10%;">Última visita</th>
        </tr>
    </thead>
    <tbody>
        @include('ventas.gastronomia.canjes.cliente_vip_emita.partials.tabla_filas', ['filas' => $lote])
    </tbody>
</table>
@endforeach
</body>
</html>
