<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #17202A; }
        h1 { font-size: 16px; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cccccc; padding: 6px 8px; text-align: left; }
        th { background: #85C1E9; color: #17202A; }
        .meta { margin-top: 8px; }
        .logos img { max-height: 48px; margin-right: 8px; }
        .muted { color: #555; font-size: 9px; }
    </style>
</head>
<body>
    <div class="logos">
        @foreach(($logos ?? []) as $logo)
            @if(!empty($logo))
                <img src="{{ $logo }}" alt="logo">
            @endif
        @endforeach
    </div>
    <h1>Acta de certificación de paridad Anita</h1>
    <p class="muted">Generado {{ now()->format('d/m/Y H:i') }}</p>
    <p class="meta">
        Informe <strong>{{ $data->codigo }}</strong> — {{ $data->titulo }}<br>
        Certificación <strong>#{{ $certificacion->id }}</strong>
        ({{ $certificacion->estado }}) · Nómina <strong>{{ $certificacion->nomina }}</strong>
    </p>
    <table>
        <tr><th>Liquidación ERP</th><td>#{{ $certificacion->liquidacion_id }}{{ $certificacion->liquidacion?->numero ? ' · nro '.$certificacion->liquidacion->numero : '' }}</td></tr>
        <tr><th>Ejecución</th><td>#{{ $certificacion->ejecucion_id }}</td></tr>
        <tr><th>Columnas OK</th><td>{{ $certificacion->columnas_ok }}</td></tr>
        <tr><th>Columnas con diferencia</th><td>{{ $certificacion->columnas_dif }}</td></tr>
        <tr><th>Máxima diferencia</th><td>{{ number_format($certificacion->max_diferencia, 4, ',', '.') }}</td></tr>
        <tr><th>Certificado por</th><td>{{ $certificacion->usuario?->nombre ?? '—' }}{{ $certificacion->usuario?->email ? ' · '.$certificacion->usuario->email : '' }}</td></tr>
        <tr><th>Fecha</th><td>{{ optional($certificacion->certificada_at)->format('d/m/Y H:i:s') }}</td></tr>
        <tr><th>Comentario</th><td>{{ $certificacion->comentario ?: '—' }}</td></tr>
    </table>
    <p style="margin-top:16px;">
        Se certifica que, para la liquidación y nómina indicadas, la matriz de paridad del listado
        no presenta diferencias fuera de tolerancia respecto de Anita (auxhist/auxconfh según corresponda).
    </p>
</body>
</html>
