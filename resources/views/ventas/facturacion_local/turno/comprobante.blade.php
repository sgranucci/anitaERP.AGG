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
    @php
        $cantFacPdf = (int) ($resumen['cantidad_facturas'] ?? 0);
        $cantNcPdf = (int) ($resumen['cantidad_nc'] ?? 0);
        $detalleCantPdf = $cantFacPdf.' facturas';
        if ($cantNcPdf > 0) {
            $detalleCantPdf .= ' · '.$cantNcPdf.' NC';
        }
    @endphp
    <p class="meta">Facturación turno: {{ number_format((float) ($resumen['total_facturado'] ?? $turno->monto_facturacion_turno), 2, ',', '.') }}
        ({{ $detalleCantPdf }})</p>
    @if ($cantNcPdf > 0)
        <p class="meta">Notas de crédito: {{ $cantNcPdf }} · {{ number_format((float) $resumen['total_nc'], 2, ',', '.') }}</p>
    @endif
    <p class="meta">Neto por medios: {{ number_format((float) ($resumen['neto_medios'] ?? 0), 2, ',', '.') }}</p>
    <p class="meta">Sobrante/faltante: {{ number_format((float) $turno->sobrante_faltante, 2, ',', '.') }}</p>
    @if ($turno->observacion_cierre)
        <p class="meta">Obs.: {{ $turno->observacion_cierre }}</p>
    @endif
    @php
        $medios = $resumen['por_medio'] ?? [];
        $arqueo = is_array($turno->medios_contado_cierre_json) ? $turno->medios_contado_cierre_json : [];
        $arqueoPorCuenta = [];
        foreach ($arqueo as $fila) {
            $ccId = (int) ($fila['cuentacaja_id'] ?? 0);
            if ($ccId > 0) {
                $arqueoPorCuenta[$ccId] = $fila;
            }
        }
    @endphp
    @if (! empty($medios))
    <table>
        <thead>
            <tr>
                <th>Medio de pago</th>
                <th>Comprobantes</th>
                <th>Cobrado</th>
                <th>Devuelto NC</th>
                <th>Neto</th>
                <th>Contado</th>
                <th>Dif.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($medios as $m)
            @php
                $ccId = (int) ($m['cuentacaja_id'] ?? 0);
                $contado = $arqueoPorCuenta[$ccId]['contado'] ?? null;
                $neto = (float) ($m['neto'] ?? 0);
                $dif = $contado === null ? null : ((float) $contado - $neto);
            @endphp
            <tr>
                <td>{{ trim(($m['codigo'] ?? '').' '.($m['nombre'] ?? '')) }}</td>
                <td style="text-align:right;">{{ (int) ($m['cantidad'] ?? 0) }}</td>
                <td style="text-align:right;">{{ number_format((float) ($m['cobrado'] ?? 0), 2, ',', '.') }}</td>
                <td style="text-align:right;">{{ number_format((float) ($m['devuelto'] ?? 0), 2, ',', '.') }}</td>
                <td style="text-align:right;">{{ number_format($neto, 2, ',', '.') }}</td>
                <td style="text-align:right;">{{ $contado === null ? '—' : number_format((float) $contado, 2, ',', '.') }}</td>
                <td style="text-align:right;">{{ $dif === null ? '—' : number_format($dif, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    <p style="margin-top:20px;font-size:9px;color:#666;">Generado {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
