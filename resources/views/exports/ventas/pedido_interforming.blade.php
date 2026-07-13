<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedido {{ $pedido->codigo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #17202A; }
        h1 { font-size: 14px; margin: 0 0 8px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #85C1E9; color: #17202A; padding: 4px; border: 1px solid #cccccc; }
        td { padding: 3px 4px; border: 1px solid #cccccc; }
        tr:nth-child(even) td { background: #f5f5f5; }
        .meta td { border: none; padding: 2px 4px; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Pedido Interforming {{ $pedido->codigo }}</h1>
    <table class="meta">
        <tr>
            <td><strong>Fecha:</strong> {{ optional($pedido->fecha)->format('d/m/Y') ?? substr((string) $pedido->fecha, 0, 10) }}</td>
            <td><strong>Entrega:</strong> {{ optional($pedido->fechaentrega)->format('d/m/Y') ?? substr((string) $pedido->fechaentrega, 0, 10) }}</td>
            <td><strong>Estado:</strong> {{ $estadosCabecera[$pedido->estadopedido ?? '0'] ?? $pedido->estadopedido }}</td>
        </tr>
        <tr>
            <td colspan="2"><strong>Cliente:</strong> {{ $pedido->clientes->codigo ?? '' }} — {{ $pedido->clientes->nombre ?? '' }}</td>
            <td><strong>O. Compra:</strong> {{ $pedido->orden_compra }}</td>
        </tr>
        <tr>
            <td><strong>Vendedor:</strong> {{ $pedido->vendedores->nombre ?? '' }}</td>
            <td><strong>Cond. venta:</strong> {{ $pedido->condicionesdeventa->nombre ?? '' }}</td>
            <td><strong>Moneda / cotiz.:</strong> {{ $pedido->moneda->abreviatura ?? '' }} / {{ $pedido->cotizacion }}</td>
        </tr>
        <tr>
            <td colspan="3"><strong>Lugar entrega:</strong> {{ $pedido->lugarentrega }}</td>
        </tr>
        @if ($pedido->leyenda)
            <tr><td colspan="3"><strong>Leyenda:</strong> {{ $pedido->leyenda }}</td></tr>
        @endif
    </table>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>SKU</th>
                <th>Descripci&oacute;n</th>
                <th>Art. cliente</th>
                <th class="right">Cant.</th>
                <th class="right">% fason</th>
                <th class="right">Precio</th>
                <th class="right">Precio fason</th>
                <th class="right">Dto</th>
                <th>Estado</th>
                <th>F. entrega</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pedido->pedido_articulos as $item)
                <tr>
                    <td>{{ $item->numeroitem }}</td>
                    <td>{{ $item->articulos->sku ?? '' }}</td>
                    <td>{{ $item->articulos->descripcion ?? $item->descripcion_aux }}</td>
                    <td>{{ $item->articulo_cliente }}</td>
                    <td class="right">{{ number_format((float) $item->cantidad, 3, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $item->porc_fason, 2, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $item->precio, 2, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $item->precio_fason, 2, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $item->descuento, 2, ',', '.') }}</td>
                    <td>{{ $estadosItem[$item->estado] ?? $item->estado }}</td>
                    <td>{{ optional($item->fechaentrega)->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p style="margin-top:10px;">Generado {{ date('d/m/Y H:i') }}</p>
</body>
</html>
