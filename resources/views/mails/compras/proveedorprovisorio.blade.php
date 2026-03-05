<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Proveedor Provisorio</title>
</head>
<body>
    <p>Hola! Se ha creado un nuevo proveedor provisorio para su validaci&oacute;n.</p>
    <p>Estos son los datos:</p>
    <ul>
        <li>Nombre: {{ $datosProveedor->nombre }}</li>
        <li>Domicilio: {{ $datosProveedor->domicilio }}</li>
        <li>Teléfono: {{ $datosProveedor->telefono }}</li>
        <li>CUIT: {{ $datosProveedor->nroinscripcion }}</li>
    </ul>
</body>
</html>