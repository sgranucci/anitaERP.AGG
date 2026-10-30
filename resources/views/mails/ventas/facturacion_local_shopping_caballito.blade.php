<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ventas Caballito</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
    <p>Adjunto las ventas del local de Caballito del {{ $fechaTexto }}.</p>
    <p>
        Archivo: <strong>{{ $nombreArchivo }}</strong><br>
        Comprobantes: {{ $comprobantes }}<br>
        Líneas: {{ $lineas }}
    </p>
    <p style="color:#666; font-size:12px;">
        Este mensaje se envía desde una casilla automática que no recibe respuestas.
    </p>
</body>
</html>
