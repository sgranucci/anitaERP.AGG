<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $formato === 'gaming' ? 'Gaming Revenues' : 'Flash Crown' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#17202A; font-size:14px;">
@if ($formato === 'gaming')
    @include('caja.flash_crown.reporte.partials.tabla_gaming', ['informe' => $informe])
@else
    <p style="margin:0 0 12px 0;">Flash del {{ $fecha->format('d/m/Y') }}.</p>
    @include('caja.flash_crown.reporte.partials.tabla_completo', ['informe' => $informe])
@endif
</body>
</html>
