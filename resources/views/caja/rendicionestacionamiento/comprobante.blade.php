@php
    $d = $datos;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $d['titulo'] ?? 'Rendición estacionamiento' }}</title>
    @include('caja.rendicionestacionamiento.partials.estilos_comprobante_pdf')
</head>
<body>
@include('caja.rendicionestacionamiento.partials.comprobante_contenido')
</body>
</html>
