<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7.5px; color: #17202A; }
        h1 { font-size: 13px; margin: 0 0 2px; }
        .meta { margin-bottom: 6px; color: #333; line-height: 1.35; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #bbbbbb; padding: 2px 3px; text-align: left; }
        table.data td { border: 1px solid #bbbbbb; padding: 2px 3px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .num { text-align: right; }
        .tot { font-weight: bold; background: #eaf2f8 !important; }
    </style>
</head>
<body>
    @php
        $logos = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
            collect([(object) ['nombreempresa' => (string) ($meta['empresa_nombre'] ?? '')]])
        );
        $t = $reporte['totales'] ?? [];
        $m = $reporte['meta'] ?? [];
        $hasta = $meta['fecha_hasta'] ?? ($m['fecha_hasta'] ?? '');
        try {
            $hastaTxt = $hasta !== '' ? \Carbon\Carbon::parse($hasta)->format('d/m/y') : '';
        } catch (\Throwable) {
            $hastaTxt = (string) $hasta;
        }
    @endphp
    @if (!empty($logos))
        <div style="margin-bottom:4px;">
            @foreach ($logos as $logo)
                @if (!empty($logo['uri']))
                    <img src="{{ $logo['uri'] }}" height="32" style="margin-right:8px;">
                @endif
            @endforeach
        </div>
    @endif
    <h1>{{ $meta['titulo'] ?? 'Movimientos de Cuentas sin conciliar' }}</h1>
    <div class="meta">
        Generado {{ date('d/m/Y H:i') }} · {{ $meta['empresa_nombre'] ?? '' }}<br>
        Desde primer mov. hasta {{ $hastaTxt }} ·
        Cuenta: {{ $meta['cuentacaja_label'] ?? ($m['cuentacaja_label'] ?? '') }} ·
        {{ (int) ($t['n'] ?? 0) }} mov.
    </div>
    <table class="data">
        <thead>
            <tr>
                <th>Tip</th>
                <th>Numero</th>
                <th>N.Orig.</th>
                <th>F.Mov.</th>
                <th>F.Dev.</th>
                <th>F.Entr.</th>
                <th>F.Conc.</th>
                <th>Detalle</th>
                <th class="num">Debitos</th>
                <th class="num">Creditos</th>
                <th>N.Con.</th>
                <th>Detalle del concepto</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($reporte['filas'] ?? []) as $fila)
                <tr>
                    <td>{{ $fila['tip'] ?? '' }}</td>
                    <td>{{ $fila['numero'] ?? '' }}</td>
                    <td>{{ $fila['n_orig'] ?? '' }}</td>
                    <td>{{ $fila['f_mov'] ?? '' }}</td>
                    <td>{{ $fila['f_dev'] ?? '' }}</td>
                    <td>{{ $fila['f_entr'] ?? '' }}</td>
                    <td>{{ $fila['f_conc'] ?? '' }}</td>
                    <td>{{ $fila['detalle'] ?? '' }}</td>
                    <td class="num">@if (abs((float) ($fila['debito'] ?? 0)) > 0.005){{ number_format((float) $fila['debito'], 2, ',', '.') }}@endif</td>
                    <td class="num">@if (abs((float) ($fila['credito'] ?? 0)) > 0.005){{ number_format((float) $fila['credito'], 2, ',', '.') }}@endif</td>
                    <td>{{ $fila['n_con'] ?? '' }}</td>
                    <td>{{ $fila['detalle_concepto'] ?? '' }}</td>
                </tr>
            @endforeach
            <tr class="tot">
                <td colspan="8" style="text-align:right;">Totales</td>
                <td class="num">{{ number_format((float) ($t['debitos'] ?? 0), 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) ($t['creditos'] ?? 0), 2, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
