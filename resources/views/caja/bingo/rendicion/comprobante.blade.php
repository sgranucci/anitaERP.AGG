<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h2 { margin: 0 0 8px; font-size: 14px; }
        .meta { margin-bottom: 10px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 4px 6px; }
        table.data th { background: #85C1E9; color: #17202A; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h2>Rendición bingo — presentación en caja</h2>
    <div class="meta">
        Código operación: <strong>{{ $rendicion->codigo }}</strong>
        · Empresa: {{ $rendicion->empresa?->nombre }}
        · Jornada: {{ optional($rendicion->fecha_jornada)->format('d/m/Y') }}
        · Turno: {{ $rendicion->turnoOperativo?->turno?->nombre }}
        · Generado: {{ now()->format('d/m/Y H:i') }}
    </div>

    <table class="data">
        <tr><th>Total cartones</th><td class="text-right">${{ number_format((float) $rendicion->total_cartones, 2, ',', '.') }}</td></tr>
        <tr><th>Cantidad cartones</th><td class="text-right">{{ (int) $rendicion->cant_cartones }}</td></tr>
        <tr><th>Saldo rendición</th><td class="text-right">${{ number_format((float) $rendicion->saldo_final, 2, ',', '.') }}</td></tr>
        <tr><th>Redondeo</th><td class="text-right">${{ number_format((float) $rendicion->redondeo, 2, ',', '.') }}</td></tr>
        <tr><th>Sobrante / faltante</th><td class="text-right">${{ number_format((float) $rendicion->sobrante_faltante, 2, ',', '.') }}</td></tr>
        <tr><th>Vales</th><td class="text-right">${{ number_format((float) $rendicion->vales, 2, ',', '.') }}</td></tr>
        <tr><th>Depósito</th><td class="text-right">${{ number_format((float) $rendicion->deposito, 2, ',', '.') }}</td></tr>
    </table>

    @if (is_array($rendicion->cartones_json) && $rendicion->cartones_json !== [])
        <h3 style="font-size:12px;margin-top:12px;">Detalle cartones</h3>
        <table class="data">
            <thead><tr><th>Código</th><th>Nombre</th><th class="text-right">Cant.</th><th class="text-right">Precio</th></tr></thead>
            <tbody>
                @foreach ($rendicion->cartones_json as $linea)
                    <tr>
                        <td>{{ $linea['codigo'] ?? '' }}</td>
                        <td>{{ $linea['nombre'] ?? '' }}</td>
                        <td class="text-right">{{ (int) ($linea['cantidad'] ?? 0) }}</td>
                        <td class="text-right">${{ number_format((float) ($linea['precio_unitario'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($rendicion->observacion)
        <p style="margin-top:10px;"><strong>Observación:</strong> {{ $rendicion->observacion }}</p>
    @endif
</body>
</html>
