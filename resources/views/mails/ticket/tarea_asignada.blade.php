<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Nueva tarea en ticket #{{ $ticket->id }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222;">
    <p>Hola{{ optional($ticket->usuarios)->nombre ? ' '.optional($ticket->usuarios)->nombre : '' }},</p>

    <p>
        Se registró una nueva tarea en el ticket <strong>#{{ $ticket->id }}</strong>
        @if (! empty($ticket->titulo))
            — <em>{{ $ticket->titulo }}</em>
        @endif
        desde la administración de tickets.
    </p>

    <p>
        <strong>Asignada por:</strong> {{ $asignadoPor->nombre ?? $asignadoPor->usuario }}
    </p>

    <h3 style="margin:18px 0 6px 0;">Tarea(s) agregada(s)</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
        <thead style="background:#85C1E9; color:#17202A;">
            <tr>
                <th align="left">Tarea</th>
                <th align="left">Fecha carga</th>
                <th align="left">Fecha programación</th>
                <th align="left">Técnico</th>
                <th align="left">Turno</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tareasNuevas as $tarea)
                <tr>
                    <td>{{ $tarea['nombre_tarea'] ?? '' }}</td>
                    <td>{{ $tarea['fechacarga_legible'] ?? '' }}</td>
                    <td>{{ $tarea['fechaprogramacion_legible'] ?? '' }}</td>
                    <td>{{ $tarea['tecnico_nombre'] ?? '' }}</td>
                    <td>{{ $tarea['turno_nombre'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (! empty($ticket->comentario))
        <h3 style="margin:18px 0 6px 0;">Comentario del ticket</h3>
        <p>{{ $ticket->comentario }}</p>
    @endif

    @if (! empty($urlTicket))
        <p style="margin-top:18px;">
            <a href="{{ $urlTicket }}">Ver ticket en Anita ERP</a>
        </p>
    @endif
</body>
</html>
