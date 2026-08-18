<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $reporte->titulo }}</title>
</head>
<body style="font-family:Arial,sans-serif;color:#222;font-size:14px;">
    <h2 style="margin:0 0 4px;">{{ $reporte->codigo }} — {{ $reporte->titulo }}</h2>
    <p style="margin:0 0 14px;color:#555;">
        Ejecuci&oacute;n auditable #{{ $ejecucion->id }}
        @if($segmento !== '') &middot; Segmento: <strong>{{ $segmento }}</strong> @endif
        &middot; {{ $ejecucion->finalizada_at?->format('d/m/Y H:i') }}
    </p>

    @if(trim((string) ($suscripcion->mensaje ?? '')) !== '')
        <p style="padding:10px;background:#f4f6f7;border-left:4px solid #85C1E9;">
            {{ $suscripcion->mensaje }}
        </p>
    @endif

    <p>
        El resultado completo se adjunta a este correo. Contiene
        <strong>{{ $ejecucion->cantidad_filas }}</strong> fila(s) y
        <strong>{{ $ejecucion->cantidad_columnas }}</strong> columna(s).
    </p>

    @if($ejecucion->advertencias_count > 0)
        <div style="padding:10px;background:#fdf6e3;border:1px solid #B7950B;">
            <strong>Controles de calidad</strong>
            <ul>
                @foreach((array) $ejecucion->advertencias as $advertencia)
                    <li>{{ $advertencia }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p style="margin-top:18px;color:#777;font-size:12px;">
        Generado autom&aacute;ticamente por {{ config('app.name', 'anitaERP') }}.
        El hash del resultado es {{ $ejecucion->resultado_hash }}.
    </p>
</body>
</html>
