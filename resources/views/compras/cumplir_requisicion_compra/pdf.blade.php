<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 9px; color: #17202A; margin: 12px; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .meta { font-size: 9px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data th { background-color: #85C1E9; color: #17202A; border: 1px solid #ccc; padding: 3px 4px; text-align: left; }
        table.data td { border: 1px solid #ccc; padding: 3px 4px; }
        table.data tr:nth-child(even) td { background-color: #f5f5f5; }
        .right { text-align: right; }
        .subtitulo { font-size: 10px; font-weight: bold; margin: 8px 0 3px; }
    </style>
</head>
<body>
    <h1>Cumplimiento de requisici&oacute;n de compra N&ordm; {{ $data['cumplimiento_numero'] ?? '' }}</h1>
    <div class="meta">
        Generado: {{ $data['generado_en'] ?? now()->format('d/m/Y H:i') }} &nbsp;|&nbsp;
        Usuario: {{ $data['usuario'] ?? '' }}
    </div>

    @if (!empty($data['cabeceras']))
        <div class="subtitulo">Requisiciones</div>
        <table class="data">
            <thead>
                <tr>
                    <th>N&ordm; Req.</th>
                    <th>Fecha</th>
                    <th>Empresa</th>
                    <th>Centro costo</th>
                    <th>Dep. origen</th>
                    <th>Dep. destino</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['cabeceras'] as $cab)
                    <tr>
                        <td>#{{ $cab['numerorequisicion'] ?? '' }}</td>
                        <td>{{ $cab['fecha'] ?? '' }}</td>
                        <td>{{ $cab['empresa'] ?? '' }}</td>
                        <td>{{ $cab['centrocosto'] ?? '' }}</td>
                        <td>{{ trim(($cab['deposito_origen_codigo'] ?? '').' '.($cab['deposito_origen'] ?? '')) }}</td>
                        <td>{{ trim(($cab['deposito_destino_codigo'] ?? '').' '.($cab['deposito_destino'] ?? '')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="subtitulo">L&iacute;neas cumplidas</div>
    <table class="data">
        <thead>
            <tr>
                <th>Req.</th>
                <th>SKU</th>
                <th>Descripci&oacute;n</th>
                <th class="right">Entrega</th>
                <th class="right">Pend. restante</th>
                <th>Dep. origen</th>
                <th>Dep. destino</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['filas'] ?? [] as $fila)
                <tr>
                    <td>#{{ $fila['requisicion_nro'] ?? '' }}</td>
                    <td>{{ $fila['sku'] ?? '' }}</td>
                    <td>{{ $fila['descripcion'] ?? '' }}</td>
                    <td class="right">{{ number_format((float) ($fila['entrega'] ?? 0), 2, '.', '') }}</td>
                    <td class="right">{{ number_format((float) ($fila['pendiente_restante'] ?? 0), 2, '.', '') }}</td>
                    <td>{{ $fila['deposito_origen_codigo'] ?? '' }}</td>
                    <td>{{ $fila['deposito_destino_codigo'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Sin l&iacute;neas.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if (!empty($data['transferencias']))
        <div class="subtitulo">Transferencias generadas</div>
        <table class="data">
            <thead>
                <tr>
                    <th>Transferencia</th>
                    <th>Origen</th>
                    <th>Destino</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['transferencias'] as $tm)
                    <tr>
                        <td>{{ $tm['codigo'] !== '' ? $tm['codigo'] : ('#'.($tm['id'] ?? '')) }}</td>
                        <td>{{ trim(($tm['origen_codigo'] ?? '').' '.($tm['origen'] ?? '')) }}</td>
                        <td>{{ trim(($tm['destino_codigo'] ?? '').' '.($tm['destino'] ?? '')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (!empty($data['leyenda']))
        <div class="subtitulo">Leyenda</div>
        <div>{{ $data['leyenda'] }}</div>
    @endif
</body>
</html>
