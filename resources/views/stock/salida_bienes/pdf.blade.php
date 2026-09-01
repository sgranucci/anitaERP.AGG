<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Remito salida {{ $prestamo->codigo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #17202A; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #ccc; padding: 4px; text-align: left; }
        table.data td { border: 1px solid #ccc; padding: 4px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .meta { margin-top: 8px; }
        .meta td { padding: 2px 8px 2px 0; }
        .firma { margin-top: 40px; width: 100%; }
        .firma td { width: 50%; text-align: center; padding-top: 40px; border-top: 1px solid #999; }
    </style>
</head>
<body>
    <h1>Remito de salida de bienes — {{ $prestamo->codigo }}</h1>
    <div>Generado {{ now()->format('d/m/Y H:i') }} · Tipo: {{ $prestamo->etiquetaTipo() }} · Estado: {{ $prestamo->estado }}</div>

    <table class="meta">
        <tr>
            <td><strong>Fecha salida:</strong> {{ optional($prestamo->fecha_prestamo)->format('d/m/Y') }}</td>
            <td><strong>Dev. prometida:</strong> {{ optional($prestamo->fecha_devolucion_prometida)->format('d/m/Y') ?: '—' }}</td>
        </tr>
        <tr>
            <td><strong>Origen:</strong> {{ optional($prestamo->depositoOrigen)->nombre }}</td>
            <td><strong>Destinatario:</strong> {{ $prestamo->etiquetaDestinatario() }}</td>
        </tr>
        <tr>
            <td><strong>Solicitante:</strong> {{ optional($prestamo->solicitante)->nombre }}</td>
            <td><strong>Prioridad:</strong> {{ $prestamo->prioridad ?? 'NORMAL' }}</td>
        </tr>
    </table>

    @if ($prestamo->esDestinoExterno())
        <p>
            Externo: {{ $prestamo->externo_nombre }}
            @if ($prestamo->externo_documento) · {{ $prestamo->externo_documento }} @endif
            @if ($prestamo->externo_empresa) · {{ $prestamo->externo_empresa }} @endif
            @if ($prestamo->externo_telefono) · {{ $prestamo->externo_telefono }} @endif
        </p>
    @endif

    @if (! empty($prestamo->observaciones))
        <p><strong>Observaciones:</strong> {{ $prestamo->observaciones }}</p>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Descripción</th>
                <th>Serie / ID</th>
                <th>Condición</th>
                <th>Cant.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prestamo->items as $item)
                <tr>
                    <td>{{ optional($item->articulos)->sku ?: '—' }}</td>
                    <td>{{ $item->descripcionMostrada() }}</td>
                    <td>{{ $item->nro_serie ?: '—' }}</td>
                    <td>{{ $item->condicion_salida ?: '—' }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->cantidad, 6, '.', ''), '0'), '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="firma">
        <tr>
            <td>Entrega / despacho</td>
            <td>Recepción</td>
        </tr>
    </table>
</body>
</html>
