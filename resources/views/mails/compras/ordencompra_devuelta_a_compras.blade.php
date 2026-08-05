<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Legajo devuelto a COMPRAS</title>
</head>
<body style="font-family: DejaVu Sans, Arial, sans-serif; font-size: 14px; color: #222;">
    <p>El legajo de la orden de compra fue devuelto al sector <strong>COMPRAS</strong>.</p>
    <ul>
        <li><strong>OC:</strong> {{ $datos['numero_oc'] ?? '' }}</li>
        <li><strong>Empresa:</strong> {{ $datos['empresa'] ?? '' }}</li>
        <li><strong>Proveedor:</strong> {{ $datos['proveedor'] ?? '' }}</li>
        <li><strong>Motivo:</strong> {{ $datos['motivo'] ?? '' }}</li>
    </ul>
    @if (! empty($datos['detalle']))
        <p>{{ $datos['detalle'] }}</p>
    @endif
    @if (! empty($datos['url']))
        <p><a href="{{ $datos['url'] }}">Abrir orden de compra</a></p>
    @endif
</body>
</html>
