<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diferencia de cambio pendiente</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222;">
    <p>Hay una factura de proveedor en moneda extranjera cuyo tipo de cambio difiere del de una recepción de un período ya cerrado, por encima de la tolerancia.</p>
    <p>Quedó en <strong>borrador</strong>. Al aprobarla en Mis aprobaciones se contabiliza sola, con la diferencia de cambio en el mes abierto. La recepción no se modifica.</p>

    <p style="margin-top:16px;">
        <a href="{{ $links['bandeja'] }}" style="display:inline-block;padding:10px 16px;background:#0f3d56;color:#fff;text-decoration:none;font-weight:700;border-radius:3px;">Ir a Mis aprobaciones</a>
        <a href="{{ $links['visualizar'] }}" style="display:inline-block;padding:10px 16px;background:#007bff;color:#fff;text-decoration:none;font-weight:700;border-radius:3px;margin-left:8px;">Ver comprobantes</a>
    </p>

    <h3 style="margin:18px 0 6px 0;">Factura</h3>
    <ul style="line-height:1.5;">
        <li><strong>Número:</strong> {{ $comprobante->letra }} {{ $comprobante->sucursal }}-{{ $comprobante->numerocomprobante }}</li>
        <li><strong>Proveedor:</strong> {{ optional($comprobante->proveedores)->nombre ?? '—' }}</li>
        <li><strong>Empresa:</strong> {{ optional($comprobante->empresas)->nombre ?? '—' }}</li>
        <li><strong>OC:</strong> {{ optional($comprobante->ordencompras)->numeroordencompra ?? '—' }}</li>
        <li><strong>TC factura:</strong> {{ number_format((float) $comprobante->cotizacion, 4, ',', '.') }}</li>
        <li><strong>Desvío:</strong> {{ number_format((float) ($analisis['porcentaje_max'] ?? 0), 2, ',', '.') }}% (tolerancia {{ number_format((float) ($analisis['tolerancia'] ?? 0), 2, ',', '.') }}%)</li>
        <li><a href="{{ $links['factura'] }}">Abrir la factura</a></li>
        @if (! empty($links['ordencompra']))
            <li><a href="{{ $links['ordencompra'] }}">Abrir la orden de compra</a></li>
        @endif
    </ul>

    <h3 style="margin:18px 0 6px 0;">Recepciones implicadas</h3>
    <ul>
        @foreach ($links['recepciones'] as $recepcion)
            <li>
                Recepción #{{ $recepcion['id'] }}
                @if ($recepcion['fecha'] !== '')
                    del {{ $recepcion['fecha'] }}
                @endif
                · TC {{ $recepcion['tc'] }}
                · <a href="{{ $recepcion['url'] }}">Abrir recepción</a>
            </li>
        @endforeach
    </ul>

    <p style="color:#888; font-size:11px; margin-top:24px;">
        Autorizar o rechazar se hace desde Mis aprobaciones, con la sesión iniciada.
        Este correo fue generado automáticamente por el sistema.
    </p>
</body>
</html>
