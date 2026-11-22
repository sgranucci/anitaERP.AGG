<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Problemas con cliente</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<p style="margin:0 0 12px 0;">
    Se le informa que la cuenta del cliente Nro.
    <strong>{{ $fila['codigo'] ?? '' }}</strong>
    {{ $fila['nombre'] ?? '' }}
    presenta problemas de AFIP / padrón ARCA.
</p>
<p style="margin:0 0 12px 0;">
    Motivo: <strong>{{ $fila['motivo'] ?? '' }}</strong>
</p>
@if (! empty($fila['detalles']) && is_array($fila['detalles']))
    <ul>
        @foreach ($fila['detalles'] as $detalle)
            <li>{{ $detalle }}</li>
        @endforeach
    </ul>
@endif
<p style="margin:0 0 12px 0;">
    Por favor póngase en contacto con la administración.
</p>
<p style="margin-top:20px; font-size:12px; color:#666;">
    Control {{ $fechaControl !== '' ? \Carbon\Carbon::parse($fechaControl)->format('d/m/Y') : '' }}
    · {{ config('app.empresa') }} · reemplazo envia_mail_vendedor (l-pedrem)
</p>
</body>
</html>
