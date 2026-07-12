<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recepci&oacute;n {{ $recepcion->numerorecepcion ?? $recepcion->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f5f5; padding:24px; color:#333; }
        .card { max-width:860px; margin:0 auto; background:#fff; border-radius:8px; padding:24px; box-shadow:0 2px 12px rgba(0,0,0,.08); }
        .estado { display:inline-block; padding:4px 10px; border-radius:4px; font-size:13px; font-weight:700; margin-bottom:12px; }
        .estado-borrador { background:#fff3cd; color:#856404; }
        .estado-confirmada { background:#d4edda; color:#155724; }
        .estado-anulada { background:#f8d7da; color:#721c24; }
        table { width:100%; border-collapse:collapse; font-size:14px; margin-top:16px; }
        th, td { border:1px solid #ddd; padding:6px 8px; text-align:left; }
        th { background:#85C1E9; color:#17202A; }
        .num { text-align:right; }
        .meta { font-size:14px; line-height:1.6; margin-bottom:8px; }
        .aviso { background:#e8f4fd; border:1px solid #bee5eb; color:#0c5460; padding:12px 14px; border-radius:6px; margin-bottom:16px; font-size:14px; }
        .actions { margin-top:20px; }
        .btn { display:inline-block; padding:10px 16px; border-radius:4px; text-decoration:none; color:#fff; background:#007bff; font-size:14px; }
    </style>
</head>
<body>
    <div class="card">
        <h1 style="margin-top:0;">Recepci&oacute;n de proveedor #{{ $recepcion->numerorecepcion ?? $recepcion->id }}</h1>

        @php
            $estado = (string) ($recepcion->estado ?? '');
            $claseEstado = match ($estado) {
                \App\Models\Stock\Recepcion_Proveedor::ESTADO_BORRADOR => 'estado-borrador',
                \App\Models\Stock\Recepcion_Proveedor::ESTADO_CONFIRMADA => 'estado-confirmada',
                \App\Models\Stock\Recepcion_Proveedor::ESTADO_ANULADA => 'estado-anulada',
                default => 'estado-borrador',
            };
        @endphp

        <span class="estado {{ $claseEstado }}">{{ $estado ?: '—' }}</span>

        @if ($recepcion->fl_precio_pendiente_aprobacion)
            <div class="aviso" style="background:#fff3cd;border-color:#ffeeba;color:#856404;">
                <strong>Precio pendiente de aprobaci&oacute;n en compras.</strong>
                Los precios solicitados deben actualizarse en la OC antes de confirmar la recepci&oacute;n.
            </div>
        @endif

        <div class="aviso">
            Consulta p&uacute;blica de la recepci&oacute;n. No requiere iniciar sesi&oacute;n en el ERP.
        </div>

        <div class="meta">
            <strong>Proveedor:</strong> {{ optional($recepcion->proveedores)->nombre ?? '—' }}<br>
            <strong>OC:</strong> {{ optional($recepcion->ordencompras)->numeroordencompra ?? '—' }}<br>
            <strong>Fecha:</strong> {{ $recepcion->fecha?->format('d/m/Y') ?? '—' }}<br>
            <strong>Dep&oacute;sito:</strong> {{ optional($recepcion->depositos)->nombre ?? '—' }}<br>
            <strong>Registrada por:</strong> {{ optional($recepcion->creousuarios)->nombre ?? '—' }}<br>
            @if ($recepcion->numerofactura)
                <strong>Factura:</strong> {{ $recepcion->numerofactura }}<br>
            @endif
            @if ($recepcion->observacion)
                <strong>Observaciones:</strong> {{ $recepcion->observacion }}<br>
            @endif
        </div>

        <h3 style="margin-bottom:8px;">&Iacute;tems</h3>
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Art&iacute;culo</th>
                    <th class="num">Cantidad</th>
                    <th class="num">Precio</th>
                    <th class="num">Precio solicitado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recepcion->recepcion_proveedor_articulos as $linea)
                    <tr>
                        <td>{{ optional($linea->articulos)->sku }}</td>
                        <td>{{ optional($linea->articulos)->descripcion }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $linea->cantidad, 6, '.', ''), '0'), '.') }}</td>
                        <td class="num">{{ number_format((float) ($linea->precio ?? 0), 4, ',', '.') }}</td>
                        <td class="num">
                            @if($linea->precio_solicitado !== null && abs((float)$linea->precio_solicitado - (float)($linea->precio_ordencompra ?? $linea->precio)) >= 0.0001)
                                {{ number_format((float) $linea->precio_solicitado, 4, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Sin &iacute;tems registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($estado === \App\Models\Stock\Recepcion_Proveedor::ESTADO_CONFIRMADA && ! empty($token))
            <div class="actions">
                <a class="btn" href="{{ \App\Support\Stock\RecepcionProveedorEnlacePublicoSupport::urlComPdfPublico($token) }}" target="_blank" rel="noopener">
                    Ver comprobante PDF
                </a>
            </div>
        @endif
    </div>
</body>
</html>
