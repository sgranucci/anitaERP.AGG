<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Alta del artículo SKU {{$datosArticulo['sku']}}</title>
</head>
<body>
    <p>Hola! Se ha dado de alta el articulo {{$datosArticulo['sku']}}</p>
    <p>Estos son los datos:</p>
    <ul>
        <li>Descripción: {{ $datosArticulo['descripcion'] }}</li>
        <li>Marca: {{ $datosArticulo['marca'] }}</li>
        <li>Línea: {{ $datosArticulo['linea'] }}</li>
    </ul>
</body>
</html>