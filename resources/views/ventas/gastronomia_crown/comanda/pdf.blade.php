<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comanda {{ $comanda->numero }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        .meta { color: #444; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px; text-align: left; }
        th { background: #85C1E9; color: #17202A; }
    </style>
</head>
<body>
    <h1>Comanda {{ $comanda->numero }} — {{ strtoupper($comanda->tipo) }}</h1>
    <div class="meta">
        @if ($comanda->mesa)
            Mesa: {{ $comanda->mesa->codigo }} {{ $comanda->mesa->nombre }} ·
        @endif
        @if ($comanda->mozo)
            Mozo: {{ $comanda->mozo->nombre }} ·
        @endif
        {{ optional($comanda->fired_at)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}
        @if ($comanda->areaComanda)
            · Área: {{ $comanda->areaComanda->nombre }}
        @endif
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:15%;">Cant.</th>
                <th>Artículo</th>
                <th style="width:30%;">Obs.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($comanda->lineas as $linea)
                <tr>
                    <td>{{ rtrim(rtrim(number_format((float) $linea->cantidad, 3, ',', ''), '0'), ',') }}</td>
                    <td>{{ $linea->descripcion }}</td>
                    <td>{{ $linea->observacion }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
