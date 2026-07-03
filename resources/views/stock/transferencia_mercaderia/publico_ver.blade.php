<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transferencia {{ $transferencia->codigo }}</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f5f5; padding:24px; color:#333; }
        .card { max-width:820px; margin:0 auto; background:#fff; border-radius:8px; padding:24px; box-shadow:0 2px 12px rgba(0,0,0,.08); }
        .estado { display:inline-block; padding:4px 10px; border-radius:4px; font-size:13px; font-weight:700; margin-bottom:12px; }
        .estado-pendiente { background:#fff3cd; color:#856404; }
        .estado-confirmada { background:#d4edda; color:#155724; }
        .estado-rechazada { background:#f8d7da; color:#721c24; }
        table { width:100%; border-collapse:collapse; font-size:14px; margin-top:16px; }
        th, td { border:1px solid #ddd; padding:6px 8px; text-align:left; }
        th { background:#85C1E9; color:#17202A; }
        .actions { margin-top:20px; }
        .btn { display:inline-block; padding:10px 16px; margin-right:8px; border-radius:4px; text-decoration:none; color:#fff; border:0; cursor:pointer; font-size:14px; }
        .btn-ok { background:#28a745; }
        .btn-danger { background:#dc3545; }
        .meta { font-size:14px; line-height:1.6; margin-bottom:8px; }
        .aviso { background:#e8f4fd; border:1px solid #bee5eb; color:#0c5460; padding:12px 14px; border-radius:6px; margin-bottom:16px; font-size:14px; }
    </style>
</head>
<body>
    <div class="card">
        <h1 style="margin-top:0;">Transferencia {{ $transferencia->codigo }}</h1>

        @php
            $estado = (string) ($transferencia->estado ?? '');
            $claseEstado = match ($estado) {
                \App\Support\Stock\TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION => 'estado-pendiente',
                \App\Support\Stock\TransferenciaMercaderiaEstados::CONFIRMADA => 'estado-confirmada',
                \App\Support\Stock\TransferenciaMercaderiaEstados::RECHAZADA => 'estado-rechazada',
                default => 'estado-pendiente',
            };
        @endphp

        <span class="estado {{ $claseEstado }}">
            {{ \App\Support\Stock\TransferenciaMercaderiaEstados::etiqueta($estado) }}
        </span>

        @if ($estado === \App\Support\Stock\TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION)
            <div class="aviso">
                Esta transferencia est&aacute; pendiente de aprobaci&oacute;n en el dep&oacute;sito destino.
                Pod&eacute;s aprobar o rechazar desde esta p&aacute;gina sin iniciar sesi&oacute;n en el ERP.
            </div>
        @else
            <div class="aviso">
                Consulta p&uacute;blica de la transferencia. No requiere iniciar sesi&oacute;n en el ERP.
            </div>
        @endif

        <div class="meta">
            <strong>Origen:</strong>
            @if ($transferencia->bien_uso_origen_id)
                {{ \App\Support\Stock\TransferenciaBienUsoSupport::etiquetaBien($transferencia->bienUsoOrigen) }}
            @else
                {{ optional($transferencia->depositoOrigen)->nombre }}
            @endif
            <br>
            <strong>Destino:</strong>
            @if ($transferencia->bien_uso_destino_id)
                {{ \App\Support\Stock\TransferenciaBienUsoSupport::etiquetaBien($transferencia->bienUsoDestino) }}
            @else
                {{ optional($transferencia->depositoDestino)->nombre }}
            @endif
            <br>
            <strong>Fecha:</strong> {{ $transferencia->fecha?->format('d/m/Y') }}
            <br>
            <strong>Enviada por:</strong> {{ optional($transferencia->usuarioOrigen)->nombre ?? '—' }}
            @if ($transferencia->usuarioDestino)
                <br>
                <strong>Destinatario:</strong> {{ $transferencia->usuarioDestino->nombre }}
            @endif
            @if ($transferencia->motivo_rechazo)
                <br>
                <strong>Motivo rechazo:</strong> {{ $transferencia->motivo_rechazo }}
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th>SKU origen</th>
                    <th>Descripci&oacute;n</th>
                    <th>Cant. origen</th>
                    <th>SKU destino</th>
                    <th>Cant. destino</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transferencia->articulos as $linea)
                    <tr>
                        <td>{{ optional($linea->articuloOrigen)->sku }}</td>
                        <td>{{ optional($linea->articuloOrigen)->descripcion }}</td>
                        <td>{{ number_format((float) $linea->cantidad_origen, 4, ',', '.') }}</td>
                        <td>
                            @if ($linea->fl_conversion_formula)
                                {{ optional($linea->articuloDestino)->sku }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($linea->fl_conversion_formula)
                                {{ number_format((float) $linea->cantidad_destino, 4, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($estado === \App\Support\Stock\TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION)
            <div class="actions">
                @if (! empty($tokenAprobar))
                <form method="get" action="{{ urlAppAbsoluta('stock/transferencia-mercaderia/publico/'.$tokenAprobar.'/aprobar') }}" style="display:inline">
                    <button type="submit" class="btn btn-ok">Aprobar recepci&oacute;n</button>
                </form>
                @endif
                @if (! empty($tokenRechazar))
                <form method="post" action="{{ urlAppAbsoluta('stock/transferencia-mercaderia/publico/'.$tokenRechazar.'/rechazar') }}" style="display:inline">
                    @csrf
                    <input type="hidden" name="motivo" value="Rechazado desde enlace p&uacute;blico">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('¿Rechazar esta transferencia?');">Rechazar</button>
                </form>
                @endif
            </div>
        @endif
    </div>
</body>
</html>
