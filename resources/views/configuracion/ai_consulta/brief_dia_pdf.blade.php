<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Brief del día</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #17202A; }
        h1 { font-size: 16px; margin: 0 0 4px 0; }
        .meta { color: #555; margin-bottom: 12px; font-size: 9px; }
        .bloque { margin-bottom: 10px; page-break-inside: avoid; }
        .bloque h2 { font-size: 11px; margin: 0 0 4px 0; background: #85C1E9; color: #17202A; padding: 4px 6px; }
        ul { margin: 0; padding-left: 16px; }
        li { margin-bottom: 2px; }
    </style>
</head>
<body>
    <h1>Brief del día — copiloto IA</h1>
    <div class="meta">
        Generado {{ $generadoAt ?? now()->format('d/m/Y H:i') }}
        @if (!empty($asistenteRol))
            · Asistente: {{ $asistenteRol }}
        @endif
    </div>
    @forelse ($items as $i => $item)
        <div class="bloque">
            <h2>{{ ($i + 1) }}. {{ $item['titulo'] ?? 'Bloque' }}</h2>
            <ul>
                @foreach (array_slice($item['lineas'] ?? [], 0, 6) as $linea)
                    @if (trim((string) $linea) !== '')
                        <li>{{ $linea }}</li>
                    @endif
                @endforeach
            </ul>
        </div>
    @empty
        <p>El brief está vacío. Armalo desde el FAB con «armar brief del día».</p>
    @endforelse
</body>
</html>
