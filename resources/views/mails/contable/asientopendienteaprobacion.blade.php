<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Asiento {{ $asiento->numeroasiento }} — Pendiente de aprobación</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222;">
    <p>Se registró un asiento contable con cuentas fuera de la lista autorizada del usuario que lo cargó.</p>

    @if (! empty($config->mail_texto_aprobacion))
        <p>{{ $config->mail_texto_aprobacion }}</p>
    @else
        <p>Por favor revisá el asiento y, según corresponda, <strong>aprobalo</strong> para sincronizarlo con contabilidad o <strong>rechazalo</strong> si no corresponde.</p>
    @endif

    <h3 style="margin:18px 0 6px 0;">Datos del asiento</h3>
    <ul style="line-height:1.5;">
        <li><strong>Número:</strong> {{ $asiento->numeroasiento }}</li>
        <li><strong>Fecha:</strong> {{ optional($asiento->fecha)->format('d/m/Y') ?? $asiento->fecha }}</li>
        <li><strong>Empresa:</strong> {{ optional($asiento->empresas)->nombre ?? '—' }}</li>
        <li><strong>Tipo:</strong> {{ optional($asiento->tipoasientos)->nombre ?? '—' }}</li>
        <li><strong>Usuario:</strong> {{ optional($asiento->usuarios)->nombre ?? '—' }}</li>
        @if (! empty($asiento->observacion))
            <li><strong>Observación:</strong> {{ $asiento->observacion }}</li>
        @endif
    </ul>

    @php
        $cuentasPendientes = \App\Support\Contable\AsientoCuentaUsuarioSupport::detalleCuentas($asiento->cuentasNoAutorizadasIds());
    @endphp
    @if ($cuentasPendientes !== [])
        <h3 style="margin:18px 0 6px 0;">Cuentas no autorizadas</h3>
        <ul>
            @foreach ($cuentasPendientes as $cuenta)
                <li>{{ $cuenta['codigo'] }} — {{ $cuenta['nombre'] }}</li>
            @endforeach
        </ul>
    @endif

    <h3 style="margin:18px 0 6px 0;">Movimientos</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
        <thead style="background:#f0f0f0;">
            <tr>
                <th align="left">Cuenta</th>
                <th align="right">Debe</th>
                <th align="right">Haber</th>
                <th align="left">Obs.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($asiento->asiento_movimientos as $mov)
                @php $monto = (float) ($mov->monto ?? 0); @endphp
                <tr>
                    <td>{{ optional($mov->cuentacontables)->codigo }} {{ optional($mov->cuentacontables)->nombre }}</td>
                    <td align="right">{{ $monto > 0 ? number_format($monto, 2, ',', '.') : '' }}</td>
                    <td align="right">{{ $monto < 0 ? number_format(abs($monto), 2, ',', '.') : '' }}</td>
                    <td>{{ $mov->observacion }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top:18px;">
        <a href="{{ $links['aprobar'] }}" style="background:#28a745; color:#fff; padding:10px 16px; text-decoration:none; border-radius:4px; margin-right:8px;">Aprobar asiento</a>
        <a href="{{ $links['rechazar'] }}" style="background:#dc3545; color:#fff; padding:10px 16px; text-decoration:none; border-radius:4px;">Rechazar</a>
    </p>

    <p style="margin-top:8px;">
        <a href="{{ $links['visualizar'] }}">Ver detalle completo del asiento</a>
    </p>

    <p style="color:#888; font-size:11px; margin-top:24px;">
        Este correo fue generado automáticamente por el sistema.
    </p>
</body>
</html>
