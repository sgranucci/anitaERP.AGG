<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Control de ganancias' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .sub { margin-bottom: 8px; color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 3px 4px; vertical-align: top; }
        th { background: #d6eaf8; }
        .disc { background: #fcf3cf; }
    </style>
</head>
<body>
    <h1>{{ $titulo ?? 'Control de ganancias' }}</h1>
    @if (! empty($subtitulo))
        <div class="sub">{{ $subtitulo }}</div>
    @endif
    <table>
        @include('compras.proveedor_control_ganancias_reporte.partials.tabla_datos', [
            'filas' => $filas ?? [],
            'puede_ver_proveedor' => false,
        ])
    </table>
</body>
</html>
