{{-- Qué se está autorizando, en los términos del contrato de suscripción. --}}
<h2>Condiciones de la suscripción</h2>
<table class="cabecera">
    <colgroup>
        <col style="width:18%;"><col style="width:32%;">
        <col style="width:18%;"><col style="width:32%;">
    </colgroup>
    <tbody>
        <tr>
            <td class="lbl">Servicio</td>
            <td>{{ $susc['servicio'] }}</td>
            <td class="lbl">Periodicidad</td>
            <td>{{ $susc['periodicidad'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Importe período</td>
            <td>{{ trim($susc['moneda'].' '.number_format($susc['monto_periodo'], 2, ',', '.')) }}</td>
            <td class="lbl">Tolerancia</td>
            <td>{{ number_format($susc['tolerancia_pct'], 2, ',', '.') }} %</td>
        </tr>
        <tr>
            <td class="lbl">Tope autorizado</td>
            <td><strong>{{ trim($susc['moneda'].' '.number_format($susc['tope_autorizado'], 2, ',', '.')) }}</strong></td>
            <td class="lbl">Renovación automática</td>
            <td>{{ $susc['auto_renovable_texto'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Medio de pago</td>
            <td>
                {{ $susc['condicion_pago'] }}
                @if ($susc['tarjeta_etiqueta'] !== '')
                    ({{ $susc['tarjeta_etiqueta'] }})
                @endif
            </td>
            <td class="lbl">Vigencia</td>
            <td>{{ $susc['vigencia_desde'] }} — {{ $susc['vigencia_hasta'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Área solicitante</td>
            <td>{{ $susc['area'] !== '' ? $susc['area'] : '—' }}</td>
            <td class="lbl">Dueño del servicio</td>
            <td>{{ $susc['owner'] !== '' ? $susc['owner'] : '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Aprobó</td>
            <td colspan="3">
                {{ $susc['aprobo'] }}
                @if ($susc['aprobo_fecha'] !== '')
                    <span class="muted">· {{ $susc['aprobo_fecha'] }}</span>
                @endif
                <span class="muted">· árbol Suscripciones, gerente del sector</span>
            </td>
        </tr>
    </tbody>
</table>

<table style="width:100%; border-collapse:collapse; margin-top:4px;">
    <tr>
        <td style="border:1px solid #1e4d7b; padding:6px 8px; font-size:8.5px; line-height:1.5;">
            @foreach ($susc['condiciones'] as $condicion)
                • {{ $condicion }}<br>
            @endforeach
        </td>
    </tr>
</table>

<table style="width:100%; border-collapse:collapse; margin-top:4px;">
    <tr>
        <td style="text-align:right; padding:5px 8px; background:#eef4fa; border:1px solid #1e4d7b; font-size:10px;">
            TOTAL <strong>TOPE AUTORIZADO</strong> por cargo:
            <strong style="font-size:11px;">{{ trim($susc['moneda'].' '.number_format($susc['tope_autorizado'], 2, ',', '.')) }}</strong>
        </td>
    </tr>
</table>
