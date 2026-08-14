<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 20px 24px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #17202A; }
        .header { width: 100%; margin-bottom: 10px; }
        .header td { border: 0; vertical-align: middle; }
        .logos img { max-height: 42px; max-width: 150px; margin-right: 10px; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .meta { color: #555; line-height: 1.45; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 3px; }
        table.data thead th { background: #85C1E9; color: #17202A; font-weight: bold; }
        table.data tbody tr:nth-child(even) { background: #f5f5f5; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .summary { margin: 8px 0; font-size: 8px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="logos" style="width:32%;">
                @foreach ($logos as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}">
                @endforeach
            </td>
            <td>
                <h1>Papel de trabajo — Ajuste por inflación RT 6</h1>
                <div class="meta">
                    Empresa: {{ $corrida->empresa?->nombre }}<br>
                    Período: {{ $corrida->periodo_desde->format('d/m/Y') }}
                    al {{ $corrida->fecha_cierre->format('d/m/Y') }}<br>
                    Generado: {{ now()->format('d/m/Y H:i') }} · Corrida #{{ $corrida->id }}
                    · Estado: {{ ucfirst($corrida->estado) }}
                </div>
            </td>
        </tr>
    </table>

    <div class="summary">
        Índice de cierre:
        <strong>{{ number_format((float) $corrida->indiceCierre?->valor, 8, ',', '.') }}</strong>
        · Registros: <strong>{{ $corrida->detalles->count() }}</strong>
        · Ajuste neto: <strong>{{ number_format((float) $corrida->total_ajuste, 2, ',', '.') }}</strong>
        · Firma: {{ $corrida->firma }}
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>Origen</th>
                <th>Cuenta</th>
                <th>Descripción</th>
                <th>Centro costo</th>
                <th class="num">Saldo origen</th>
                <th class="num">Índice origen</th>
                <th class="num">Coeficiente</th>
                <th class="num">Reexpresado</th>
                <th class="num">Ajuste</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($corrida->detalles as $detalle)
                <tr>
                    <td class="center">{{ $detalle->periodo_origen->format('m/Y') }}</td>
                    <td>{{ $detalle->cuentacontable?->codigo }}</td>
                    <td>{{ $detalle->cuentacontable?->nombre }}</td>
                    <td>{{ $detalle->centrocosto?->codigo ?? '' }}</td>
                    <td class="num">{{ number_format((float) $detalle->saldo_origen, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $detalle->indiceOrigen?->valor, 8, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $detalle->coeficiente, 10, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $detalle->importe_reexpresado, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $detalle->ajuste, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
