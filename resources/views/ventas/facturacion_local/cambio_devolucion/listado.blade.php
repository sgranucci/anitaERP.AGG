<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cambios / devoluciones marketplace</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { font-size: 14px; margin: 0 0 6px; }
        .meta { margin-bottom: 10px; color: #333; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 4px; }
        table.data td { border: 1px solid #cccccc; padding: 3px; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
    </style>
</head>
<body>
@php
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceEstadosSupport;
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceCatalogoSupport;
@endphp
<h1>Cambios / devoluciones marketplace</h1>
<div class="meta">
    Generado {{ now()->format('d/m/Y H:i') }} —
    {{ $datas->count() }} registro(s)
</div>
<table class="data">
    <thead>
        <tr>
            <th>Nº</th>
            <th>Estado</th>
            <th>Canal</th>
            <th>Local</th>
            <th>FAC orig.</th>
            <th>FAC reemp.</th>
            <th>Receptor</th>
            <th>Diferencia</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
        <tr>
            <td>{{ $data->numero }}</td>
            <td>{{ CambioDevolucionMarketplaceEstadosSupport::etiqueta($data->estado) }}</td>
            <td>{{ CambioDevolucionMarketplaceCatalogoSupport::CANALES[$data->canal] ?? $data->canal }}</td>
            <td>{{ $data->localVenta->nombre ?? '' }}</td>
            <td>{{ $data->ventaOriginal->codigo ?? $data->venta_original_id }}</td>
            <td>{{ $data->ventaReemplazo->codigo ?? '' }}</td>
            <td>{{ $data->receptor_nombre }}</td>
            <td>{{ number_format((float) $data->diferencia_importe, 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
