<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>
        @if ($formato === 'gaming')
            Gaming Revenues
        @elseif ($formato === 'daily')
            Daily Flash Report
        @else
            Flash Crown
        @endif
    </title>
</head>
<body style="font-family: Arial, sans-serif; color:#17202A; font-size:14px;">
@if ($formato === 'gaming')
    @include('caja.flash_crown.reporte.partials.tabla_gaming', ['informe' => $informe])
@elseif ($formato === 'daily')
    <p style="margin:0 0 12px 0;">Daily Flash Report {{ $fecha->format('d/m/Y') }}.</p>
    @include('caja.flash_crown.reporte.partials.tabla_daily', ['informe' => $informe])
@else
    <p style="margin:0 0 12px 0;">Flash del {{ $fecha->format('d/m/Y') }}.</p>
    @include('caja.flash_crown.reporte.partials.tabla_completo', ['informe' => $informe])
@endif
</body>
</html>
