<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cierre turno {{ $turno->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #17202A; }
        h1 { font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #85C1E9; color: #17202A; padding: 6px; border: 1px solid #ccc; }
        td { padding: 6px; border: 1px solid #ccc; }
        .meta { margin: 4px 0; }
    </style>
</head>
<body>
    <h1>Cierre de turno — Facturación Local</h1>
    <p class="meta">Turno #{{ $turno->id }}
        @if ($turno->turnoLocal)
            · {{ $turno->turnoLocal->nombre }}
        @endif
        · Local {{ $turno->localVenta->codigo ?? '' }} {{ $turno->localVenta->nombre ?? '' }}</p>
    <p class="meta">Apertura: {{ optional($turno->apertura_en)->format('d/m/Y H:i') }} · {{ $turno->usuarioApertura->nombre ?? '' }}</p>
    <p class="meta">Cierre: {{ optional($turno->cierre_en)->format('d/m/Y H:i') }} · {{ $turno->usuarioCierre->nombre ?? '' }}</p>
    <p class="meta">Fondo inicial: {{ number_format($turno->fondo_inicial, 2, ',', '.') }}</p>
    <p class="meta">Facturación turno: {{ number_format($turno->monto_facturacion_turno, 2, ',', '.') }}</p>
    <p class="meta">Sobrante/faltante: {{ number_format((float) $turno->sobrante_faltante, 2, ',', '.') }}</p>
    @if ($turno->observacion_cierre)
        <p class="meta">Obs.: {{ $turno->observacion_cierre }}</p>
    @endif
    @php $medios = $turno->medios_contado_cierre_json ?? []; @endphp
    @if (! empty($medios))
    <table>
        <thead>
            <tr>
                <th>Cuenta</th>
                <th>Esperado</th>
                <th>Contado</th>
                <th>Dif.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($medios as $m)
            <tr>
                <td>{{ $m['cuentacaja_id'] ?? '' }}</td>
                <td style="text-align:right;">{{ number_format((float) ($m['esperado'] ?? 0), 2, ',', '.') }}</td>
                <td style="text-align:right;">{{ number_format((float) ($m['contado'] ?? 0), 2, ',', '.') }}</td>
                <td style="text-align:right;">{{ number_format((float) ($m['contado'] ?? 0) - (float) ($m['esperado'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    <p style="margin-top:20px;font-size:9px;color:#666;">Generado {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
