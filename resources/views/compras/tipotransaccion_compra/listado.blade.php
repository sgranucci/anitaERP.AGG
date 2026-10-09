@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = $datas->count();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tipos de comprobante de compras</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 3px; vertical-align: top; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas > th { background-color: #85C1E9; color: #17202A; font-size: 7px; font-weight: bold; }
    </style>
</head>
<body>
    <table class="data">
        <thead>
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => 'Tipos de comprobante de compras',
                'subtitulo' => $subtitulo ?? '',
                'colspan' => 12,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
            <tr class="columnas">
                <th>ID</th>
                <th>Nombre</th>
                <th>Operación</th>
                <th>Abrev.</th>
                <th>Tipo ARCA</th>
                <th>Signo</th>
                <th>Subdiario</th>
                <th>Asiento</th>
                <th>Estado</th>
                <th>IVA</th>
                <th>Ganancias</th>
                <th>IIBB</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($datas as $data)
                <tr>
                    <td>{{ $data->id }}</td>
                    <td>{{ $data->nombre }}</td>
                    <td>{{ $data->desc_operacion }}</td>
                    <td>{{ $data->abreviatura }}</td>
                    <td>@include('compras.tipotransaccion_compra.partials.celda_codigo_arca')</td>
                    <td>{{ $data->desc_signo }}</td>
                    <td>{{ $data->desc_subdiario }}</td>
                    <td>{{ $data->desc_asientocontable }}</td>
                    <td>{{ $data->desc_estado }}</td>
                    <td>{{ $data->desc_retieneiva }}</td>
                    <td>{{ $data->desc_retieneganancia }}</td>
                    <td>{{ $data->desc_retieneiibb }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
