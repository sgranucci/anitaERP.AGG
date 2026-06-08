<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tituloEvento }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; line-height:1.5;">
    <h2 style="margin:0 0 12px 0; color:#333;">{{ $tituloEvento }}</h2>

    @if ($textoCuerpo !== '')
        <p style="white-space: pre-wrap;">{{ $textoCuerpo }}</p>
    @endif

    @if (! empty($linkConsulta))
        <p style="margin: 20px 0;">
            <a href="{{ $linkConsulta }}" style="background:#007bff; color:#fff; padding:10px 18px; text-decoration:none; border-radius:4px;">
                Consultar en Anita ERP
            </a>
        </p>
        <p style="font-size:12px; color:#666;">
            Si el botón no funciona, copiá este enlace en el navegador:<br>
            <a href="{{ $linkConsulta }}">{{ $linkConsulta }}</a>
        </p>
    @endif

    <p style="color:#888; font-size:11px; margin-top:28px;">
        Correo generado automáticamente por Anita ERP. Si no esperabas recibirlo podés ignorarlo.
    </p>
</body>
</html>
