<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de caja</title>
</head>
<body>
    <p>Adjuntamos el informe de <strong>cierre de caja / tesorería</strong>
        @if (!empty($resumen['fecha']))
            del día <strong>{{ $resumen['fecha'] }}</strong>
        @endif
        .
    </p>
    @if (!empty($resumen['subtitulo']))
        <p>{{ $resumen['subtitulo'] }}</p>
    @endif
    <p>Registros en el adjunto: {{ (int) ($resumen['total_registros'] ?? 0) }}.</p>
    <p>Mensaje automático de {{ config('app.name', 'anitaERP') }} (equivalente Anita l-ciecaja).</p>
</body>
</html>
