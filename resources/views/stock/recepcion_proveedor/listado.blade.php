@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccion);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recepciones proveedor</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; padding: 4px; border: 1px solid #ccc; }
        table.data td { padding: 3px; border: 1px solid #ccc; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
    </style>
</head>
<body>
<table style="width:100%; margin-bottom:8px;">
    <tr>
        <td>
            @foreach($logos as $logo)
                <img src="{{ $logo['uri'] }}" style="max-height:48px; margin-right:8px;">
            @endforeach
        </td>
        <td style="text-align:center"><h2 style="margin:0">Recepciones de proveedores</h2>
            <div>Generado {{ date('d/m/Y H:i') }} · {{ $coleccion->count() }} registros</div>
        </td>
    </tr>
</table>
<table class="data">
    <thead>
        <tr>
            <th>Nº</th><th>Fecha</th><th>Tipo</th><th>OC</th><th>Proveedor</th><th>Empresa</th><th>Estado</th><th>Diff.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($coleccion as $row)
        <tr>
            <td>{{ $row->numerorecepcion }}</td>
            <td>{{ $row->fecha ? date('d/m/Y', strtotime($row->fecha)) : '' }}</td>
            <td>{{ $row->tipo }}</td>
            <td>{{ $row->numeroordencompra }}</td>
            <td>{{ $row->nombreproveedor }}</td>
            <td>{{ $row->nombreempresa }}</td>
            <td>{{ $row->estado }}</td>
            <td>
                @if($row->fl_precio_diferencia) P @endif
                @if($row->fl_diferencia_cantidad) C @endif
                @if($row->fl_articulo_extra) A @endif
                @if($row->fl_faltante_oc) F @endif
                @if($row->fl_laboratorio) L @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
