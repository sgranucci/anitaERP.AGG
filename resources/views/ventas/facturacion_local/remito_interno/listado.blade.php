<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado remitos internos</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h1 { font-size: 14px; margin: 0 0 4px 0; }
        .meta { margin-bottom: 8px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 3px 4px; }
        table.data thead { background: #85C1E9; color: #17202A; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
    </style>
</head>
<body>
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Ventas\FacturacionLocal\RemitoInternoEstadosSupport;
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
@endphp
@if (! empty($logos))
    <div>
        @foreach ($logos as $logo)
            @if (! empty($logo['uri']))
                <img src="{{ $logo['uri'] }}" style="max-height:40px; margin-right:8px;" alt="">
            @endif
        @endforeach
    </div>
@endif
<h1>Listado de remitos internos</h1>
<div class="meta">Generado {{ date('d/m/Y H:i') }} — {{ $datas->count() }} registro(s)</div>
<table class="data">
    <thead>
        <tr>
            <th>Nº</th>
            <th>Fecha</th>
            <th>Estado</th>
            <th>Local</th>
            <th>Destinatario</th>
            <th>Depósito</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
            <tr>
                <td>{{ $data->numero }}</td>
                <td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
                <td>{{ RemitoInternoEstadosSupport::etiqueta($data->estado) }}</td>
                <td>{{ trim(($data->localVenta->codigo ?? '').' '.($data->localVenta->nombre ?? '')) }}</td>
                <td>{{ $data->destinatario }}</td>
                <td>{{ trim(($data->deposito->codigo ?? '').' '.($data->deposito->nombre ?? '')) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
