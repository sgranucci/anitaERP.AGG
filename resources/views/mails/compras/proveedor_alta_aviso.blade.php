<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedor dado de alta</title>
</head>
<body>
    <p>
        Se le informa que el proveedor
        <strong>{{ $datos['codigo'] ?? '' }}</strong>
        {{ $datos['nombre'] ?? '' }}
        ha sido dado de alta.
    </p>
    <p>Por favor verifiquen datos de impuestos y habiliten para su uso.</p>
    <ul>
        @if (!empty($datos['cuit']))
            <li>CUIT: {{ $datos['cuit'] }}</li>
        @endif
        @if (!empty($datos['usuario']))
            <li>Usuario: {{ $datos['usuario'] }}</li>
        @endif
    </ul>
    <p>Mensaje automático de {{ config('app.name', 'anitaERP') }}.</p>
</body>
</html>
