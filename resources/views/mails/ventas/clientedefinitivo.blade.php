<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Cliente</title>
</head>
<body>
    <p>Hola! Se ha creado un nuevo cliente para su validaci&oacute;n.</p>
    <p>Estos son los datos:</p>
    <ul>
        <li>Nombre: {{ $datosCliente->nombre }}</li>
        <li>Domicilio: {{ $datosCliente->domicilio }}</li>
        <li>Teléfono: {{ $datosCliente->telefono }}</li>
        <li>CUIT: {{ $datosCliente->nroinscripcion }}</li>
    </ul>
</body>
</html>