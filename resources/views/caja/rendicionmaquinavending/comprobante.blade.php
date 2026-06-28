@php
    $d = $datos;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $d['titulo'] ?? 'Rendición vending caja' }}</title>
    @include('caja.rendicionmaquinavending.partials.estilos_comprobante_pdf')
</head>
<body>
@include('caja.rendicionmaquinavending.partials.comprobante_contenido')
</body>
</html>
