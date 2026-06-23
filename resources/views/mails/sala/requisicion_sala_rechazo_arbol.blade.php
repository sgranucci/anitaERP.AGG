<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requisición de sala rechazada</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222;">
    <p>Hola {{ $solicitante->nombre }},</p>

    <p>Su requisición de sala
        <strong>#{{ $requisicion->numerorequisicion ?? $requisicion->id }}</strong>
        fue <span style="color:#dc3545;"><strong>RECHAZADA</strong></span>
        @if ($rechazador)
            por {{ $rechazador->nombre }}.
        @else
            en el árbol de aprobación.
        @endif
    </p>

    @if ($motivoRechazo !== '')
        <p><strong>Motivo:</strong> {{ $motivoRechazo }}</p>
    @endif

    <p>Puede corregirla y volver a enviarla al circuito de aprobación desde el ERP:</p>
    <p><a href="{{ $linkEditar }}">{{ $linkEditar }}</a></p>

    <ul style="line-height:1.5;">
        <li><strong>Fecha:</strong> {{ $requisicion->fecha ? date('d/m/Y', strtotime($requisicion->fecha)) : '—' }}</li>
        <li><strong>Estado:</strong> RECHAZADA</li>
        <li><strong>Depósito:</strong> {{ optional($requisicion->depositos)->nombre ?? '—' }}</li>
    </ul>
</body>
</html>
