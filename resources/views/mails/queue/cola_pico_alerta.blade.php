<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alerta cola Laravel</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; line-height:1.5;">
    <h2 style="margin:0 0 12px 0; color:#333;">
        Verificación cola — {{ $informe['status'] ?? 'ALERTA' }}
    </h2>

    <p style="margin:0 0 8px 0;">
        <strong>Fecha:</strong> {{ $informe['timestamp'] ?? '' }}<br>
        <strong>QUEUE_CONNECTION:</strong> {{ $informe['queue_connection'] ?? '?' }}<br>
        <strong>Workers activos:</strong> {{ $informe['worker_count'] ?? 0 }}<br>
        <strong>Supervisor:</strong> {{ $informe['supervisor_state'] ?? '?' }}
    </p>

    @php
        $jobs = $informe['jobs'] ?? [];
    @endphp
    <p style="margin:0 0 8px 0;">
        <strong>Cola:</strong>
        pending={{ $jobs['pending'] ?? 0 }},
        reserved={{ $jobs['reserved'] ?? 0 }},
        delayed={{ $jobs['delayed'] ?? 0 }},
        reservado_mas_viejo={{ $jobs['oldest_reserved_sec'] ?? 0 }}s
    </p>

    <p style="margin:0 0 8px 0;">
        <strong>failed_jobs 24h:</strong> {{ $informe['failed_jobs_24h'] ?? 0 }}
        · <strong>total:</strong> {{ $informe['failed_jobs_total'] ?? 0 }}
    </p>

    @php
        $laravel60 = $informe['laravel_60m'] ?? [];
    @endphp
    <p style="margin:0 0 16px 0;">
        <strong>Últimos 60 min (laravel.log):</strong>
        waitry OK={{ $laravel60['waitry_ok'] ?? 0 }},
        waitry fallos={{ $laravel60['waitry_fail'] ?? 0 }},
        anita_bridge fallo={{ $laravel60['anita_bridge_fail'] ?? 0 }}
    </p>

    @if (! empty($informe['issues_critical']))
        <h3 style="color:#c0392b; margin:16px 0 8px 0;">Crítico</h3>
        <ul>
            @foreach ($informe['issues_critical'] as $issue)
                <li>{{ $issue }}</li>
            @endforeach
        </ul>
    @endif

    @if (! empty($informe['issues_warn']))
        <h3 style="color:#d68910; margin:16px 0 8px 0;">Advertencia</h3>
        <ul>
            @foreach ($informe['issues_warn'] as $issue)
                <li>{{ $issue }}</li>
            @endforeach
        </ul>
    @endif

    <p style="color:#888; font-size:11px; margin-top:28px;">
        Correo automático (hora pico gastronomía). Comando: <code>php artisan queue:verificar-pico</code>.
        Log: storage/logs/queue-verificar-pico.log
    </p>
</body>
</html>
