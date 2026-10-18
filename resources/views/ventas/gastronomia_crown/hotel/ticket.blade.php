<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cargo hotel #{{ $cuenta->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #17202A; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #85C1E9; color: #17202A; text-align: left; padding: 4px; }
        td { border-bottom: 1px solid #ccc; padding: 4px; }
        .tot { font-weight: bold; font-size: 13px; margin-top: 10px; }
    </style>
</head>
<body>
    <h1>Cargo a habitación</h1>
    <div>Cuenta #{{ $cuenta->id }} · {{ now()->format('d/m/Y H:i') }}</div>
    <div>Habitación: <strong>{{ $cuenta->habitacion }}</strong>
        @if ($cuenta->reserva_hotel) · Reserva: {{ $cuenta->reserva_hotel }} @endif
    </div>
    @if ($cuenta->mesa)
        <div>Mesa: {{ $cuenta->mesa->codigo }} {{ $cuenta->mesa->nombre }}</div>
    @endif
    <table>
        <thead>
            <tr>
                <th>Cant</th>
                <th>Artículo</th>
                <th style="text-align:right;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @php $sub = 0; @endphp
            @foreach ($cuenta->lineas as $l)
                @continue($l->es_separador)
                @php $imp = (float)$l->cantidad * (float)$l->precio - (float)$l->descuento_importe; $sub += $imp; @endphp
                <tr>
                    <td>{{ $l->cantidad }}</td>
                    <td>{{ $l->descripcion }}</td>
                    <td style="text-align:right;">{{ number_format($imp, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @php
        $pct = (float) ($cuenta->porcentaje_descuento ?? 0);
        $dto = round($sub * ($pct / 100), 2);
        $total = max(0, $sub - $dto);
    @endphp
    @if ($pct > 0)
        <div>Subtotal: $ {{ number_format($sub, 2, ',', '.') }} · Dto {{ $pct }}%: $ {{ number_format($dto, 2, ',', '.') }}</div>
    @endif
    <div class="tot">TOTAL: $ {{ number_format($total, 2, ',', '.') }}</div>
</body>
</html>
