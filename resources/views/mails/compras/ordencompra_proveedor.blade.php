<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de compra</title>
</head>
<body>
    <p>Estimado proveedor{{ $ordencompra->proveedores ? ' '.$ordencompra->proveedores->nombre : '' }}:</p>
    <p>Adjuntamos la orden de compra Nº <strong>{{ $ordencompra->numeroordencompra }}</strong>
        @if ($ordencompra->empresas)
            de <strong>{{ $ordencompra->empresas->nombre }}</strong>
        @endif
        .
    </p>
    @if (!empty($mensajeAdicional))
        <p>{!! nl2br(e($mensajeAdicional)) !!}</p>
    @endif
    <p>Saludos cordiales.</p>
</body>
</html>
