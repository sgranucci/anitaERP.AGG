<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #17202A;">
    <h2 style="color: #C0392B;">Ingesta de facturas por mail — errores</h2>

    <p>
        No se pudo crear la precarga para {{ count($errores) }} adjunto(s) de un correo recibido
        en la casilla de facturas.
        @if ($exitos > 0)
            Otros {{ $exitos }} adjunto(s) del mismo correo se procesaron correctamente.
        @endif
    </p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse; margin-bottom: 16px;">
        <tr>
            <td style="border: 1px solid #cccccc; background: #85C1E9; font-weight: bold;">Remitente</td>
            <td style="border: 1px solid #cccccc;">{{ $mensaje->remitente ?: '—' }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #cccccc; background: #85C1E9; font-weight: bold;">Asunto</td>
            <td style="border: 1px solid #cccccc;">{{ $mensaje->asunto ?: '—' }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #cccccc; background: #85C1E9; font-weight: bold;">Fecha del mensaje</td>
            <td style="border: 1px solid #cccccc;">{{ $mensaje->fechaMensaje?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
    </table>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%;">
        <thead>
            <tr style="background: #85C1E9;">
                <th style="border: 1px solid #cccccc; text-align: left;">Adjunto</th>
                <th style="border: 1px solid #cccccc; text-align: left;">OC detectada</th>
                <th style="border: 1px solid #cccccc; text-align: left;">Motivo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($errores as $error)
                <tr>
                    <td style="border: 1px solid #cccccc;">{{ $error['adjunto'] }}</td>
                    <td style="border: 1px solid #cccccc;">{{ $error['numero_oc'] ?? 'sin detectar' }}</td>
                    <td style="border: 1px solid #cccccc; color: #C0392B;">{{ $error['error'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 16px; color: #555555;">
        El correo quedó en la carpeta «{{ config('precarga_comprobante_mail.carpeta_errores') }}» de la casilla
        y el detalle está en «Errores de recepción» del módulo de precarga de comprobantes.
        Los PDF con error quedan en cuarentena en <code>storage/app/compras/factura_pdf_ia/mail_pendiente/</code>.
    </p>

    <p style="color: #999999; font-size: 12px;">
        Mensaje automático de {{ config('app.name', 'anitaERP') }} — ingesta de facturas por correo.
    </p>
</body>
</html>
