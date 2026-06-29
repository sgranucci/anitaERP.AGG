@php
    $tipo = $movimiento->tipotransaccion_stock;
    $deposito = optional($movimiento->articulos_movimiento->first())->depositos;
    $usuario = $usuario ?? null;
    $colorCabecera = '#52BE80';
    $colorBorde = '#229954';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Movimiento de stock {{ $movimiento->codigo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; }
        .header { width: 100%; border-bottom: 2px solid {{ $colorBorde }}; margin-bottom: 12px; padding-bottom: 8px; }
        .header td { vertical-align: middle; border: none; }
        h1 { margin: 0; font-size: 16px; color: {{ $colorBorde }}; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: {{ $colorCabecera }}; color: #17202A; font-weight: bold; padding: 4px; border: 1px solid #ccc; font-size: 8px; }
        table.data td { padding: 4px; border: 1px solid #ccc; vertical-align: top; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .num { text-align: right; }
        .totales { margin-top: 10px; text-align: right; font-size: 10px; font-weight: bold; }
        .obs { margin-top: 8px; font-size: 8px; border: 1px solid #ccc; padding: 6px; }
    </style>
</head>
<body>
<table class="header">
    <tr>
        <td style="width:35%">
            @foreach ($logos as $logo)
                <img src="{{ $logo['uri'] }}" style="max-height:50px; margin-right:8px;">
            @endforeach
        </td>
        <td style="width:40%; text-align:center">
            <h1>Comprobante de movimiento de stock</h1>
            <div class="meta">N&deg; {{ $movimiento->codigo }} &mdash; {{ optional($tipo)->nombre ?? 'Movimiento' }}</div>
            <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
        </td>
        <td style="width:25%; text-align:right">
            <strong>{{ optional($empresa)->nombre ?? config('app.empresa') }}</strong><br>
            Fecha: {{ $movimiento->fecha ? date('d/m/Y', strtotime($movimiento->fecha)) : '—' }}
        </td>
    </tr>
</table>

<table style="width:100%; margin-bottom:10px; font-size:8px;">
    <tr>
        <td><strong>Tipo:</strong> {{ optional($tipo)->nombre ?? '—' }} ({{ optional($tipo)->abreviatura ?? '—' }})</td>
        <td><strong>Dep&oacute;sito:</strong> {{ optional($deposito)->etiqueta() ?? '—' }}</td>
        <td><strong>Lote:</strong> {{ optional($movimiento->articulos_movimiento->first())->lote ?? '—' }}</td>
    </tr>
    <tr>
        <td><strong>Operaci&oacute;n:</strong>
            @if(($tipo->operacion ?? '') === 'E')
                Entrada
            @elseif(($tipo->operacion ?? '') === 'S')
                Salida
            @elseif(($tipo->operacion ?? '') === 'T')
                Transferencia
            @else
                —
            @endif
        </td>
        <td><strong>Centro costo destino:</strong> {{ optional($movimiento->centrocostoDestino)->nombre ?? '—' }}</td>
        <td><strong>Usuario:</strong> {{ optional($usuario)->nombre ?? '—' }}</td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>#</th>
            <th>SKU</th>
            <th>Descripci&oacute;n</th>
            <th>UM</th>
            <th class="num">Cantidad</th>
            <th class="num">Precio</th>
            <th class="num">Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach($movimiento->articulos_movimiento as $idx => $linea)
        @php
            $cant = abs((float) $linea->cantidad);
            $precio = abs((float) $linea->precio);
            $importe = $cant * $precio;
        @endphp
        <tr>
            <td>{{ $idx + 1 }}</td>
            <td>{{ optional($linea->articulos)->sku ?? $linea->sku }}</td>
            <td>{{ optional($linea->articulos)->descripcion ?? '—' }}</td>
            <td>{{ optional(optional($linea->articulos)->unidadesdemedidas)->abreviatura ?? '—' }}</td>
            <td class="num">{{ number_format($cant, 4, ',', '.') }}</td>
            <td class="num">{{ number_format($precio, 4, ',', '.') }}</td>
            <td class="num">{{ number_format($importe, 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="totales">
    Total cantidad: {{ number_format($totalCantidad, 4, ',', '.') }}
    &nbsp;|&nbsp;
    Total importe: {{ number_format($totalImporte, 2, ',', '.') }}
</div>

@if(trim((string) ($movimiento->leyenda ?? '')) !== '')
<div class="obs"><strong>Observaci&oacute;n:</strong> {{ $movimiento->leyenda }}</div>
@endif
</body>
</html>
