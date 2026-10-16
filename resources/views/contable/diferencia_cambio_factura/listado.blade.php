@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $filasLogo = collect($filas)->each(function ($fila) {
        $fila->nombreempresa = optional(optional($fila->comprobantes)->empresas)->nombre ?? '';
    });
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filasLogo);
    $totalFilas = $filasLogo->count();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 3px 4px; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas > th { background-color: #85C1E9; color: #17202A; font-weight: bold; }
    </style>
</head>
<body>
<table class="data">
    <thead>
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => $titulo,
            'subtitulo' => '',
            'colspan' => 9,
            'logosCabecera' => $logosCabecera,
            'totalFilas' => $totalFilas,
        ])
        <tr class="columnas">
            <th>Factura</th>
            <th>Proveedor</th>
            <th>OC</th>
            <th>Recepción</th>
            <th>TC recepción</th>
            <th>TC factura</th>
            <th>Nominal</th>
            <th>Pesos</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filasLogo as $fila)
            @php $cp = $fila->comprobantes; @endphp
            <tr>
                <td>{{ $cp ? $cp->letra.' '.$cp->sucursal.'-'.$cp->numerocomprobante : '' }}</td>
                <td>{{ optional(optional($cp)->proveedores)->nombre }}</td>
                <td>{{ optional(optional($cp)->ordencompras)->numeroordencompra }}</td>
                <td>#{{ $fila->recepcion_proveedor_id }}</td>
                <td>{{ number_format((float) $fila->tc_recepcion, 4, ',', '.') }}</td>
                <td>{{ number_format((float) $fila->tc_factura, 4, ',', '.') }}</td>
                <td>{{ number_format((float) $fila->nominal_me, 2, ',', '.') }}</td>
                <td>{{ number_format((float) $fila->importe_pesos, 2, ',', '.') }}</td>
                <td>{{ $fila->estado }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
