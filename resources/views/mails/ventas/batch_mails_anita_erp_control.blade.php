<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Anita↔ERP batch mails</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Control Anita ↔ ERP (batch mails El Bierzo)</h2>
<p style="margin:0 0 12px 0;">
    Fecha: <strong>{{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</strong><br>
    Solo sistemas: los mails a socios/operativos ya salen sin paneles de diferencias.
</p>

@foreach ($bloques as $b)
    @php $problema = ! empty($b['problema']); @endphp
    <div style="margin:0 0 14px 0; padding:10px 12px; border:1px solid {{ $problema ? '#c0392b' : '#1e8449' }}; background:{{ $problema ? '#fadbd8' : '#d5f5e3' }};">
        <strong>{{ $b['nombre'] ?? '' }}</strong><br>
        @if ($problema)
            DIFF {{ (int) ($b['diffs'] ?? 0) }}
            @if (empty($b['anita_ok']))
                · Anita: {{ $b['anita_error'] ?? 'error' }}
            @endif
        @else
            OK
        @endif
        @if (! empty($b['detalle']))
            <ul style="margin:8px 0 0 18px; padding:0; font-size:12px;">
                @foreach ($b['detalle'] as $d)
                    <li>
                        {{ $d['sku'] ?? $d['clave'] ?? '' }}
                        @if (isset($d['tipo'])) · {{ $d['tipo'] }} @endif
                        @if (isset($d['saldo_erp']) || isset($d['monto_erp']))
                            · ERP {{ $d['saldo_erp'] ?? $d['monto_erp'] ?? '' }}
                            / Anita {{ $d['saldo_anita'] ?? $d['monto_anita'] ?? '' }}
                        @elseif (isset($d['erp_kilo']))
                            · kg ERP {{ $d['erp_kilo'] }} / Anita {{ $d['anita_kilo'] ?? '' }}
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endforeach

<p style="margin-top:20px; font-size:12px; color:#666;">
    Generado {{ now()->format('d/m/Y H:i') }} · {{ config('app.empresa') }} · ventas:control-batch-mails-anita-erp
</p>
</body>
</html>
