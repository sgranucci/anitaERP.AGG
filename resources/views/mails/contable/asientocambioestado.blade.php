<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asiento {{ $asiento->numeroasiento }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222;">
    <p>Hola <strong>{{ optional($asiento->usuarios)->nombre ?? 'usuario' }}</strong>,</p>

    @if ($tipoCambio === 'rechazado')
        <p>Tu asiento contable <strong>{{ $asiento->numeroasiento }}</strong> fue <strong>rechazado</strong> por contaduría.</p>
        @if (! empty($mensaje))
            <p><strong>Motivo:</strong> {{ $mensaje }}</p>
        @endif
    @else
        <p>Tu asiento contable <strong>{{ $asiento->numeroasiento }}</strong> fue <strong>aprobado</strong> y ya está sincronizado con contabilidad.</p>
        @if (! empty($mensaje))
            <p><strong>Observación:</strong> {{ $mensaje }}</p>
        @endif
    @endif

    <ul style="line-height:1.5;">
        <li><strong>Fecha:</strong> {{ optional($asiento->fecha)->format('d/m/Y') ?? $asiento->fecha }}</li>
        <li><strong>Empresa:</strong> {{ optional($asiento->empresas)->nombre ?? '—' }}</li>
    </ul>
</body>
</html>
