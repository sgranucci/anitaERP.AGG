{{-- Franja de cabecera: lo esencial de la suscripción antes de los ítems. --}}
<table style="width:100%; border-collapse:collapse; margin:0 0 8px 0;">
    <tr>
        <td style="background:#1e4d7b; color:#ffffff; padding:6px 8px; font-size:9px; line-height:1.45;">
            <strong style="font-size:10px; letter-spacing:0.04em;">SUSCRIPCIÓN · {{ mb_strtoupper($susc['servicio']) }}</strong>
            <span style="float:right; font-size:9px;">
                Tope autorizado por cargo:
                <strong>{{ trim($susc['moneda'].' '.number_format($susc['tope_autorizado'], 2, ',', '.')) }}</strong>
            </span>
            <br>
            {{ $susc['periodicidad'] }}
            · {{ trim($susc['moneda'].' '.number_format($susc['monto_periodo'], 2, ',', '.')) }} por período
            · tolerancia {{ number_format($susc['tolerancia_pct'], 2, ',', '.') }}%
            · {{ $susc['condicion_pago'] }}
            @if ($susc['vigencia_hasta'] !== '—')
                · vigencia hasta {{ $susc['vigencia_hasta'] }}
            @endif
        </td>
    </tr>
</table>
