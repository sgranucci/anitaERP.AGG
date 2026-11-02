<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auditor costos de compra {{ $informe['fecha_calendario'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Auditor de costos de compra y recetas</h2>
<p style="margin:0 0 16px 0;">
    Período: <strong>{{ $informe['fecha_calendario'] ?? '—' }}</strong>
    · Umbral ×{{ number_format((float) ($informe['umbral_ratio'] ?? 0), 2, ',', '.') }}
</p>
<p style="margin:0 0 16px 0; color:#555; font-size:13px;">
    Busca recepciones con precio fuera de rango respecto de la OC o de las compras anteriores,
    unidades de medida sin conversión, cambios de unidad o coeficiente en el maestro,
    transferencias a fórmulas con costo destino fuera de rango y recetas con costo mayor o igual al precio de venta.
</p>

<h3 style="margin:18px 0 6px 0;">Resumen</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#f0f0f0;"><th align="left">Concepto</th><th align="right">Cantidad</th></tr>
    <tr><td>Líneas de recepción auditadas</td><td align="right">{{ (int) ($informe['lineas_auditadas'] ?? 0) }}</td></tr>
    <tr><td>Líneas de transferencia auditadas</td><td align="right">{{ (int) ($informe['transferencias_auditadas'] ?? 0) }}</td></tr>
    <tr><td>Recetas auditadas</td><td align="right">{{ (int) ($informe['recetas_auditadas'] ?? 0) }}</td></tr>
    <tr style="background:{{ count($informe['hallazgos'] ?? []) > 0 ? '#fadbd8' : '#d5f5e3' }};">
        <td><strong>Desfasajes</strong></td>
        <td align="right"><strong>{{ count($informe['hallazgos'] ?? []) }}</strong></td>
    </tr>
    <tr style="background:{{ count($informe['recetas'] ?? []) > 0 ? '#fadbd8' : '#d5f5e3' }};">
        <td><strong>Recetas con costo ≥ precio</strong></td>
        <td align="right"><strong>{{ count($informe['recetas'] ?? []) }}</strong></td>
    </tr>
    <tr><td>Errores</td><td align="right">{{ count($informe['errores'] ?? []) }}</td></tr>
</table>

@if (! empty($informe['recetas']))
    <h3 style="margin:18px 0 6px 0;">Recetas con costo mayor o igual al precio de venta</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#fadbd8;">
            <th align="left">Receta</th>
            <th align="right">Costo</th>
            <th align="right">Precio</th>
            <th align="left">Insumos de mayor costo</th>
        </tr>
        @foreach ($informe['recetas'] as $receta)
            <tr>
                <td>{{ $receta['codigo'] }} · {{ $receta['nombre'] }}</td>
                <td align="right">{{ number_format((float) $receta['costo'], 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) $receta['precio'], 2, ',', '.') }}</td>
                <td>
                    @foreach ($receta['insumos'] as $insumo)
                        <div style="{{ $insumo['con_desvio'] ? 'color:#a00; font-weight:bold;' : '' }}">
                            {{ $insumo['sku'] }} {{ $insumo['descripcion'] }}: $ {{ number_format((float) $insumo['costo_unitario'], 2, ',', '.') }}
                            @if (! empty($insumo['skus_compra'])) <span style="color:#555;">(compra: {{ implode(', ', $insumo['skus_compra']) }})</span>@endif
                            @if ($insumo['con_desvio']) (con desfasaje abajo) @endif
                        </div>
                    @endforeach
                </td>
            </tr>
        @endforeach
    </table>
@endif

@if (! empty($informe['hallazgos']))
    <h3 style="margin:18px 0 6px 0;">Desfasajes</h3>
    @if (! empty($informe['hallazgos_truncados']))
        <p style="color:#a00; font-size:12px;">Listado truncado en el correo.</p>
    @endif
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#fadbd8;">
            <th align="left">Tipo</th>
            <th align="left">Fecha</th>
            <th align="left">Documento</th>
            <th align="left">Artículo</th>
            <th align="left">Detalle</th>
            <th align="left">Pista</th>
        </tr>
        @foreach ($informe['hallazgos'] as $fila)
            <tr>
                <td>{{ $fila['tipo'] }}@if (($fila['gravedad'] ?? '') === 'Alta') <strong style="color:#a00;">(Alta)</strong>@endif</td>
                <td>{{ $fila['fecha'] ?: '—' }}</td>
                <td>
                    @if (! empty($fila['url']))
                        <a href="{{ $fila['url'] }}">{{ $fila['documento'] }}</a>
                    @else
                        {{ $fila['documento'] }}
                    @endif
                    @if (! empty($fila['proveedor']))<br><span style="color:#555;">{{ $fila['proveedor'] }}</span>@endif
                </td>
                <td>{{ $fila['sku'] }} · {{ $fila['descripcion'] }}</td>
                <td>{{ $fila['detalle'] }}</td>
                <td>{{ $fila['pista'] }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if (! empty($informe['errores']))
    <h3 style="margin:18px 0 6px 0;">Errores</h3>
    <ul>
        @foreach ($informe['errores'] as $err)
            <li>{{ $err['paso'] ?? '?' }} — {{ $err['mensaje'] ?? '' }}</li>
        @endforeach
    </ul>
@endif
</body>
</html>
