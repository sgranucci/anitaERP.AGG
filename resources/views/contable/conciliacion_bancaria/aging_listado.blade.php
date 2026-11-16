<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { margin-bottom: 8px; color: #444; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 3px 4px; text-align: left; }
        table.data td { border: 1px solid #cccccc; padding: 3px 4px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .num { text-align: right; }
        .resumen td { border: 1px solid #cccccc; padding: 4px; }
    </style>
</head>
<body>
    @php
        $logos = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
            collect([(object) ['nombreempresa' => (string) ($meta['empresa_nombre'] ?? '')]])
        );
        $r = $resultado['resumen'] ?? [];
    @endphp
    @if (!empty($logos))
        <div style="margin-bottom:6px;">
            @foreach ($logos as $logo)
                @if (!empty($logo['uri']))
                    <img src="{{ $logo['uri'] }}" height="36" style="margin-right:8px;">
                @endif
            @endforeach
        </div>
    @endif
    <h1>Aging conciliación bancaria</h1>
    <div class="meta">
        Generado {{ date('d/m/Y H:i') }} ·
        {{ $meta['empresa_nombre'] ?? '' }} ·
        {{ $meta['cuentacaja_label'] ?? '' }} ·
        {{ sprintf('%02d/%d', (int) ($meta['mes'] ?? 0), (int) ($meta['anio'] ?? 0)) }}
    </div>
    <table class="resumen" style="margin-bottom:8px;width:100%;">
        <tr>
            <td>0–7: <strong>{{ $r['0-7'] ?? 0 }}</strong></td>
            <td>8–30: <strong>{{ $r['8-30'] ?? 0 }}</strong></td>
            <td>31–60: <strong>{{ $r['31-60'] ?? 0 }}</strong></td>
            <td>61+: <strong>{{ $r['61+'] ?? 0 }}</strong></td>
            <td>Total: <strong>{{ $r['total'] ?? 0 }}</strong></td>
            <td>|Importe|: <strong>{{ number_format((float) ($r['importe_abs'] ?? 0), 2, ',', '.') }}</strong></td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th>Lado</th>
                <th>Fecha</th>
                <th class="num">Días</th>
                <th>Bucket</th>
                <th>Detalle</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($resultado['filas'] ?? []) as $fila)
                <tr>
                    <td>{{ $fila['lado'] }}</td>
                    <td>{{ $fila['fecha'] }}</td>
                    <td class="num">{{ $fila['dias'] }}</td>
                    <td>{{ $fila['bucket'] }}</td>
                    <td>{{ $fila['detalle'] }}</td>
                    <td class="num">{{ number_format((float) $fila['importe'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
