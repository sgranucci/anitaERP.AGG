<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Comentario en ticket #{{ $ticket->id }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222;">
    <p>Hola{{ optional($ticketTarea->tecnicos)->nombre ? ' '.optional($ticketTarea->tecnicos)->nombre : '' }},</p>

    <p>
        El usuario <strong>{{ $autor->nombre ?? $autor->usuario }}</strong>
        agregó un comentario en el ticket <strong>#{{ $ticket->id }}</strong>
        @if (! empty($ticket->titulo))
            — <em>{{ $ticket->titulo }}</em>
        @endif
        .
    </p>

    <h3 style="margin:18px 0 6px 0;">Tarea</h3>
    <p>{{ $ticketTarea->tareas->nombre ?? $ticketTarea->detalle ?? '' }}</p>

    <h3 style="margin:18px 0 6px 0;">Comentario</h3>
    <p style="white-space:pre-wrap;">{{ $comentario->comentario }}</p>

    <p style="font-size:12px; color:#666;">
        Enviado el {{ $comentario->created_at ? $comentario->created_at->format('d/m/Y H:i') : '' }}
    </p>

    @if (! empty($urlTicket))
        <p style="margin-top:18px;">
            <a href="{{ $urlTicket }}">Ver ticket en administración</a>
        </p>
    @endif
</body>
</html>
