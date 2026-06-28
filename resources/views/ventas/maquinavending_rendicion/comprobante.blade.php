@php $d = $d ?? []; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $d['titulo'] ?? 'Rendición vending' }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; color: #222; margin: 12px 16px; }
        h1 { font-size: 16px; margin: 0 0 4px 0; }
        h2 { font-size: 11px; margin: 12px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #666; padding: 4px 6px; vertical-align: top; }
        th { background: #85C1E9; font-weight: bold; text-align: left; color: #17202A; }
        .cabecera-doc td { border: none !important; vertical-align: middle; }
        .cabecera-doc { width: 100%; margin-bottom: 12px; border-bottom: 2px solid #333; padding-bottom: 8px; }
        .logo { max-height: 56px; max-width: 200px; }
        .subtitulo { font-size: 10px; color: #444; margin-bottom: 8px; }
        .lbl { background: #f0f0f0; font-weight: bold; width: 22%; }
        .num { text-align: right; white-space: nowrap; }
        .total-grande { font-size: 12px; font-weight: bold; background: #e8f4fc; }
    </style>
</head>
<body>
    <table class="cabecera-doc">
        <tr>
            <td style="width: 35%;">
                @if (!empty($d['logo']['uri']))
                    <img src="{{ $d['logo']['uri'] }}" alt="Logo" class="logo">
                @endif
            </td>
            <td style="width: 65%; text-align: right;">
                <h1>{{ $d['titulo'] ?? 'Rendición vending' }}</h1>
                <div class="subtitulo">{{ $d['subtitulo'] ?? '' }}</div>
                <div style="font-size:8px;color:#555;">Emitido: {{ $d['fecha_emision_comprobante'] ?? '' }}</div>
            </td>
        </tr>
    </table>

    <h2>Datos de la rendici&oacute;n</h2>
    <table>
        <tr>
            <td class="lbl">Empresa</td>
            <td>{{ $d['empresa_nombre'] ?? '' }}</td>
            <td class="lbl">N&ordm; cierre</td>
            <td>
                <strong>#{{ (int) ($d['numero_cierre'] ?? 0) }}</strong>
                <span style="font-size:8px;color:#555;">(correlativo empresa; registro #{{ (int) ($d['rendicion_id'] ?? 0) }})</span>
            </td>
        </tr>
        <tr>
            <td class="lbl">M&aacute;quina</td>
            <td>{{ $d['maquina_nombre'] ?? '' }}</td>
            <td class="lbl">Punto de venta</td>
            <td>{{ trim(($d['puntoventa_codigo'] ?? '').' — '.($d['puntoventa_nombre'] ?? ''), ' —') }}</td>
        </tr>
        <tr>
            <td class="lbl">Fecha rendici&oacute;n</td>
            <td>{{ $d['fecha_rendicion'] ?? '' }}</td>
            <td class="lbl">Fecha jornada</td>
            <td>{{ $d['fecha_jornada'] ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Registrado por</td>
            <td colspan="3">{{ $d['usuario_nombre'] ?? '' }}</td>
        </tr>
    </table>

    <h2>Art&iacute;culos vendidos</h2>
    <table>
        <thead>
            <tr>
                <th>Rulo</th>
                <th>SKU</th>
                <th>Descripci&oacute;n</th>
                <th class="num">Cant.</th>
                <th class="num">P. lista</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($d['articulos'] ?? [] as $art)
            <tr>
                <td class="num">{{ (int) ($art['numero_rulo'] ?? 0) }}</td>
                <td>{{ $art['sku'] ?? '' }}</td>
                <td>{{ $art['descripcion'] ?? '' }}</td>
                <td class="num">{{ number_format((float) ($art['cantidad'] ?? 0), 3, ',', '.') }}</td>
                <td class="num">${{ number_format((float) ($art['precio_lista'] ?? 0), 2, ',', '.') }}</td>
                <td class="num">${{ number_format((float) ($art['importe_total'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="total-grande">
                <td colspan="5" class="num">Total a rendir</td>
                <td class="num">${{ number_format((float) ($d['total_ventas'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Medios de pago</h2>
    <table>
        <thead>
            <tr>
                <th>C&oacute;digo</th>
                <th>Medio</th>
                <th class="num">Monto</th>
                <th class="num">Cotiz.</th>
                <th class="num">En $</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($d['medios_pago'] ?? [] as $mp)
            <tr>
                <td>{{ $mp['codigo'] ?? '' }}</td>
                <td>{{ $mp['nombre'] ?? '' }}</td>
                <td class="num">${{ number_format((float) ($mp['monto'] ?? 0), 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) ($mp['cotizacion'] ?? 1), 4, ',', '.') }}</td>
                <td class="num">${{ number_format((float) ($mp['monto_pesos'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="total-grande">
                <td colspan="4" class="num">Total cobrado</td>
                <td class="num">${{ number_format((float) ($d['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @if (!empty($d['observacion']))
    <h2>Observaciones</h2>
    <p style="white-space:pre-wrap;font-size:8px;">{{ $d['observacion'] }}</p>
    @endif
</body>
</html>
