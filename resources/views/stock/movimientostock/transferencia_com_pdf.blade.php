@php
    use App\Support\Stock\TransferenciaMercaderiaEstados;

    $tipo = $transferencia->tipotransaccion_stock;
    $colorCabecera = '#85C1E9';
    $colorBorde = '#2E86C1';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Transferencia {{ $transferencia->codigo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }
        .header { width: 100%; border-bottom: 2px solid {{ $colorBorde }}; margin-bottom: 12px; padding-bottom: 8px; }
        .header td { vertical-align: middle; border: none; font-size: 10px; }
        h1 { margin: 0; font-size: 17px; color: {{ $colorBorde }}; }
        .meta { font-size: 9px; color: #444; margin-top: 4px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: {{ $colorCabecera }}; color: #17202A; font-weight: bold; padding: 5px 3px; border: 1px solid #ccc; font-size: 9px; }
        table.data td { padding: 5px 3px; border: 1px solid #ccc; vertical-align: top; font-size: 9px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .num { text-align: right; }
        .totales { margin-top: 10px; text-align: right; font-size: 11px; font-weight: bold; }
        .obs { margin-top: 8px; font-size: 9px; border: 1px solid #ccc; padding: 6px; }
        .info td { padding: 3px 8px 3px 0; font-size: 9px; vertical-align: top; }
    </style>
</head>
<body>
<table class="header">
    <tr>
        <td style="width:30%">
            @foreach ($logos as $logo)
                <img src="{{ $logo['uri'] }}" style="max-height:50px; margin-right:8px;">
            @endforeach
        </td>
        <td style="width:40%; text-align:center">
            <h1>Comprobante de transferencia de mercader&iacute;a</h1>
            <div class="meta">N&deg; {{ $transferencia->codigo }}</div>
            <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
        </td>
        <td style="width:30%; text-align:right">
            <strong>{{ optional($empresa)->nombre ?? config('app.empresa') }}</strong><br>
            Fecha: {{ $transferencia->fecha ? $transferencia->fecha->format('d/m/Y') : '—' }}
        </td>
    </tr>
</table>

<table class="info" style="width:100%; margin-bottom:10px;">
    <tr>
        <td><strong>Tipo:</strong> {{ optional($tipo)->nombre ?? '—' }}</td>
        <td><strong>Estado:</strong> {{ TransferenciaMercaderiaEstados::etiqueta($transferencia->estado) }}</td>
        <td><strong>Lote:</strong> {{ $transferencia->lote ?? '—' }}</td>
    </tr>
    <tr>
        <td><strong>Origen:</strong> {{ $origen ?: '—' }}</td>
        <td><strong>Destino:</strong> {{ $destino ?: '—' }}</td>
        <td><strong>Usuario origen:</strong> {{ optional($transferencia->usuarioOrigen)->nombre ?? '—' }}</td>
    </tr>
    <tr>
        <td><strong>Usuario destino:</strong> {{ optional($transferencia->usuarioDestino)->nombre ?? '—' }}</td>
        <td><strong>Aprobador:</strong> {{ optional($transferencia->usuarioAprobador)->nombre ?? '—' }}</td>
        <td>
            <strong>Mov. stock:</strong>
            @if($transferencia->movimientostock_salida_id)
                Salida #{{ $transferencia->movimientostock_salida_id }}
            @endif
            @if($transferencia->movimientostock_entrada_id)
                @if($transferencia->movimientostock_salida_id) / @endif
                Entrada #{{ $transferencia->movimientostock_entrada_id }}
            @endif
        </td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>#</th>
            <th>SKU origen</th>
            <th>Art&iacute;culo origen</th>
            <th class="num">Cant. origen</th>
            <th class="num">Costo orig.</th>
            <th>SKU destino</th>
            <th>Art&iacute;culo destino</th>
            <th class="num">Cant. destino</th>
            <th class="num">Costo dest.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($transferencia->articulos as $linea)
        <tr>
            <td>{{ $linea->item }}</td>
            <td>{{ optional($linea->articuloOrigen)->sku ?? '—' }}</td>
            <td>{{ optional($linea->articuloOrigen)->descripcion ?? '—' }}</td>
            <td class="num">{{ number_format(abs((float) $linea->cantidad_origen), 4, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $linea->precio_costo_origen, 4, ',', '.') }}</td>
            <td>{{ optional($linea->articuloDestino)->sku ?? '—' }}</td>
            <td>{{ optional($linea->articuloDestino)->descripcion ?? '—' }}</td>
            <td class="num">{{ number_format(abs((float) $linea->cantidad_destino), 4, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $linea->precio_costo_destino, 4, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="totales">
    Total cantidad origen: {{ number_format($totalOrigen, 4, ',', '.') }}
    &nbsp;|&nbsp;
    Total cantidad destino: {{ number_format($totalDestino, 4, ',', '.') }}
</div>

@if(trim((string) ($transferencia->observacion ?? '')) !== '')
<div class="obs"><strong>Observaci&oacute;n:</strong> {{ $transferencia->observacion }}</div>
@endif
@if(trim((string) ($transferencia->motivo_rechazo ?? '')) !== '')
<div class="obs"><strong>Motivo rechazo:</strong> {{ $transferencia->motivo_rechazo }}</div>
@endif
</body>
</html>
