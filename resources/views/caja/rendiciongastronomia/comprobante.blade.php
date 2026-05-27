@php
    $d = $datos;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $d['titulo'] ?? 'Rendición gastronomía' }}</title>
    @include('caja.rendiciongastronomia.partials.estilos_comprobante_pdf')
</head>
<body>
@include('caja.rendiciongastronomia.partials.comprobante_contenido')
</body>
</html>
