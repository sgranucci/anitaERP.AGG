<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Cruce visitas por OC' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #333; }
        h1 { font-size: 13px; margin: 0 0 4px; }
        .meta { font-size: 8px; color: #666; margin-bottom: 6px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 2px 3px; }
        table.data th { background: #85C1E9; color: #17202A; }
    </style>
</head>
<body>
    <h1>{{ $titulo ?? 'Cruce visitas por OC' }}</h1>
    @if (! empty($subtitulo))
        <div class="meta">{{ $subtitulo }}</div>
    @endif
    @include('seguridad.visitas_oc_cruce_reporte.partials.tabla_datos', [
        'filas' => $filas,
        'en_pantalla' => false,
        'solo_thead_tbody' => false,
    ])
</body>
</html>
