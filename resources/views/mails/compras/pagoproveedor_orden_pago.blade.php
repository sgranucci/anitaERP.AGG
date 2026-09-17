<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de pago</title>
</head>
<body>
    <p>Estimado proveedor{{ $pagoproveedor->proveedores ? ' '.$pagoproveedor->proveedores->nombre : '' }}:</p>
    <p>Adjuntamos la orden de pago <strong>{{ $pagoproveedor->etiquetaComprobante() }}</strong>
        @if ($pagoproveedor->empresas)
            de <strong>{{ $pagoproveedor->empresas->nombre }}</strong>
        @endif
        .
    </p>
    @if (!empty($mensajeAdicional))
        <p>{!! nl2br(e($mensajeAdicional)) !!}</p>
    @endif
    <p>Saludos cordiales.</p>
</body>
</html>
