@php
    use App\Models\Compras\Pagoproveedor_Retencion;

    $tipo = (string) $retencion->tiporetencion;
    $detalle = is_array($retencion->detalle_calculo) ? $retencion->detalle_calculo : [];
    $titulo = match ($tipo) {
        Pagoproveedor_Retencion::TIPO_GANANCIAS => 'COMPROBANTE DE RETENCIÓN DE IMPUESTO A LAS GANANCIAS',
        Pagoproveedor_Retencion::TIPO_IVA => 'COMPROBANTE DE RETENCIÓN DE IVA',
        Pagoproveedor_Retencion::TIPO_IIBB => 'CERTIFICADO / COMPROBANTE DE RETENCIÓN DE INGRESOS BRUTOS',
        Pagoproveedor_Retencion::TIPO_SUSS => 'COMPROBANTE DE RETENCIÓN DE SEGURIDAD SOCIAL (SUSS)',
        default => 'COMPROBANTE DE RETENCIÓN — '.$retencion->etiquetaTipo(),
    };
    $subtitulo = match ($tipo) {
        Pagoproveedor_Retencion::TIPO_GANANCIAS => 'Resolución General AFIP 830',
        Pagoproveedor_Retencion::TIPO_IIBB => 'Jurisdicción: '.(optional($retencion->provincias)->nombre
            ?: ($retencion->codigo_regimen ?: '—')),
        default => '',
    };
    $conceptoRegimen = trim(
        (string) ($retencion->codigo_regimen ?: '')
        .' '
        .(string) ($retencion->codigo_retencion ?: '')
        .' '
        .(string) (optional($retencion->retencionganancias)->nombre
            ?? optional($retencion->retencionivas)->nombre
            ?? optional($retencion->retencionsusss)->nombre
            ?? '')
    );
    $legal = $leyendasLegales[$tipo] ?? '';
    $netoGravado = (float) ($detalle['neto_periodo'] ?? $detalle['neto_pago'] ?? $detalle['neto_base'] ?? $detalle['neto'] ?? $retencion->base_calculo);
    $minimo = (float) ($detalle['minimo_retencion'] ?? $detalle['minimo_imponible'] ?? $detalle['monto_excedente'] ?? 0);
    $sujeto = (float) ($detalle['base_retenible'] ?? $retencion->base_calculo);
    $yaRetenido = (float) ($detalle['retenido_previo'] ?? $detalle['retencion_periodo'] ?? 0);
    if (isset($detalle['retenido_previo'])) {
        $yaRetenido = (float) $detalle['retenido_previo'];
    } elseif (isset($detalle['retencion_periodo']) && isset($detalle['retenido_previo']) === false) {
        $periodoRet = (float) ($detalle['retencion_periodo'] ?? 0);
        $yaRetenido = max(0, $periodoRet - (float) $retencion->importe);
    }
    $pagosAnteriores = (float) ($detalle['pagos_anteriores'] ?? 0);
@endphp

@if (! empty($pageBreak))
    <div class="page-break"></div>
@endif

<table class="no-border" style="margin-bottom:6px;">
    <tr>
        <td style="width:48%;">
            @if (! empty($logo['uri']))
                <img class="logo" src="{{ $logo['uri'] }}" alt="">
            @endif
            <div style="font-size:11px;font-weight:bold;margin-top:3px;">{{ $empresa->nombre ?? '' }}</div>
            @if ($direccionEmpresa !== '')
                <div class="muted">{{ $direccionEmpresa }}</div>
            @endif
            @if (! empty($empresa->nroinscripcion))
                <div class="muted">CUIT: {{ $empresa->nroinscripcion }}</div>
            @endif
            @if (! empty($empresa->numeroiibb))
                <div class="muted">Ing. Brutos: {{ $empresa->numeroiibb }}</div>
            @endif
        </td>
        <td style="width:52%; text-align:right; vertical-align:top;">
            <div style="font-size:11px;font-weight:bold;">CERTIFICADO N&deg; {{ $retencion->nro_certificado ?? '—' }}</div>
            <div style="margin-top:4px;"><strong>Lugar y fecha:</strong> {{ $lugarFecha }}</div>
            <div class="muted" style="margin-top:2px;">Origen: {{ $nroOp }}</div>
        </td>
    </tr>
</table>

<div class="ret-titulo">{{ $titulo }}</div>
@if ($subtitulo !== '')
    <div class="ret-subtitulo">{{ $subtitulo }}</div>
@endif

<h3>Importe retenido a</h3>
<table>
    <tr>
        <td class="lbl">Proveedor</td>
        <td colspan="3">
            @if ($proveedor)
                {{ str_pad((string) $proveedor->codigo, 6, '0', STR_PAD_LEFT) }} {{ $proveedor->nombre }}
            @else
                —
            @endif
        </td>
    </tr>
    <tr>
        <td class="lbl">Domicilio</td>
        <td colspan="3">
            {{ $proveedor->domicilio ?? '—' }}
            @if ($proveedor && optional($proveedor->localidades)->nombre)
                — {{ $proveedor->localidades->nombre }}
            @endif
        </td>
    </tr>
    <tr>
        <td class="lbl">CUIT</td>
        <td>{{ $proveedor->nroinscripcion ?? '—' }}</td>
        <td class="lbl">Cond. IVA</td>
        <td>{{ optional(optional($proveedor)->condicionivas)->nombre ?? '—' }}</td>
    </tr>
    @if ($tipo === Pagoproveedor_Retencion::TIPO_IIBB)
        <tr>
            <td class="lbl">Nro. IBR</td>
            <td>{{ $proveedor->nroIIBB ?? '—' }}</td>
            <td class="lbl">Nro. reemp.</td>
            <td>—</td>
        </tr>
    @endif
</table>

@if ($tipo === Pagoproveedor_Retencion::TIPO_GANANCIAS)
    <h3>Concepto y comprobante que origina la retenci&oacute;n</h3>
    <table>
        <tr>
            <td class="lbl">Concepto / r&eacute;gimen</td>
            <td>{{ $conceptoRegimen !== '' ? $conceptoRegimen : ($retencion->motivo ?: '—') }}</td>
            <td class="lbl">Comprobante</td>
            <td>{{ $nroOp }}</td>
        </tr>
        <tr>
            <td class="lbl">DDJJ en la que informar&aacute;</td>
            <td colspan="3">{{ $fechaDdjjGanancias }}</td>
        </tr>
    </table>

    <h3>Liquidaci&oacute;n</h3>
    <table class="montos-ret">
        <tr><td class="lbl">Monto a pagar</td><td class="num">{{ number_format($totalOp, 2, ',', '.') }}</td></tr>
        <tr><td class="lbl">Pagos anteriores</td><td class="num">{{ number_format($pagosAnteriores, 2, ',', '.') }}</td></tr>
        <tr><td class="lbl">Total</td><td class="num">{{ number_format($totalOp + $pagosAnteriores, 2, ',', '.') }}</td></tr>
        <tr><td class="lbl">Neto gravado</td><td class="num">{{ number_format($netoGravado, 2, ',', '.') }}</td></tr>
        <tr><td class="lbl">M&iacute;nimo / no sujeto</td><td class="num">{{ $minimo > 0 ? number_format($minimo, 2, ',', '.') : '' }}</td></tr>
        <tr><td class="lbl">Importe sujeto a retenci&oacute;n</td><td class="num">{{ number_format($sujeto, 2, ',', '.') }}</td></tr>
        <tr><td class="lbl">Retenci&oacute;n del mes</td><td class="num">{{ number_format((float) $retencion->importe + $yaRetenido, 2, ',', '.') }}</td></tr>
        <tr><td class="lbl">Importes ya retenidos</td><td class="num">{{ number_format($yaRetenido, 2, ',', '.') }}</td></tr>
        <tr>
            <td class="lbl">Retenci&oacute;n efectuada {{ number_format((float) $retencion->alicuota, 2, ',', '.') }} %</td>
            <td class="num" style="font-weight:bold;">{{ number_format((float) $retencion->importe, 2, ',', '.') }}</td>
        </tr>
    </table>

@elseif ($tipo === Pagoproveedor_Retencion::TIPO_SUSS)
    <h3>Per&iacute;odo y liquidaci&oacute;n</h3>
    <table>
        <tr>
            <td class="lbl">Per&iacute;odo</td>
            <td>{{ $periodoSuss }}</td>
            <td class="lbl">R&eacute;gimen</td>
            <td>{{ $conceptoRegimen !== '' ? $conceptoRegimen : '—' }}</td>
        </tr>
    </table>
    <table class="montos-ret" style="margin-top:4px;">
        <tr><td class="lbl">Monto a pagar</td><td class="num">{{ number_format($totalOp, 2, ',', '.') }}</td></tr>
        <tr><td class="lbl">Base imponible</td><td class="num">{{ number_format((float) $retencion->base_calculo, 2, ',', '.') }}</td></tr>
        <tr><td class="lbl">Al&iacute;cuota %</td><td class="num">{{ number_format((float) $retencion->alicuota, 2, ',', '.') }}</td></tr>
        <tr>
            <td class="lbl">Retenci&oacute;n efectuada</td>
            <td class="num" style="font-weight:bold;">{{ number_format((float) $retencion->importe, 2, ',', '.') }}</td>
        </tr>
    </table>
    @if ($aplicaciones->count() > 0)
        <h3>Comprobantes</h3>
        <table>
            <thead>
                <tr>
                    <th>Comprobante</th>
                    <th>Fecha</th>
                    <th class="num">Gravado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($aplicaciones as $apl)
                    <tr>
                        <td>{{ $apl['numero'] }}</td>
                        <td>{{ $apl['fecha'] }}</td>
                        <td class="num">{{ number_format($apl['neto_gravado'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

@else
    {{-- IVA / IIBB: detalle por comprobante aplicado --}}
    <h3>Detalle de operaciones</h3>
    <table>
        <thead>
            <tr>
                <th>Comprobante</th>
                <th>Fecha</th>
                <th class="num">Gravado</th>
                <th class="num">Base imp.</th>
                <th class="num">% Ret.</th>
                <th class="num">Retenci&oacute;n</th>
            </tr>
        </thead>
        <tbody>
            @if ($aplicaciones->count() > 0)
                @php
                    $cant = max(1, $aplicaciones->count());
                    $importePorLinea = (float) $retencion->importe / $cant;
                    $basePorLinea = (float) $retencion->base_calculo / $cant;
                @endphp
                @foreach ($aplicaciones as $apl)
                    <tr>
                        <td>{{ $apl['numero'] }}</td>
                        <td>{{ $apl['fecha'] }}</td>
                        <td class="num">{{ number_format($apl['neto_gravado'], 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($basePorLinea, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $retencion->alicuota, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($importePorLinea, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td>{{ $nroOp }}</td>
                    <td>{{ optional($pago->fecha)->format('d/m/Y') }}</td>
                    <td class="num">{{ number_format((float) $retencion->base_calculo, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $retencion->base_calculo, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $retencion->alicuota, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $retencion->importe, 2, ',', '.') }}</td>
                </tr>
            @endif
            <tr>
                <td colspan="5" class="num" style="font-weight:bold;">TOTAL RETENIDO</td>
                <td class="num" style="font-weight:bold;">{{ number_format((float) $retencion->importe, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
    @if ($conceptoRegimen !== '')
        <div class="muted" style="margin-top:4px;">R&eacute;gimen: {{ $conceptoRegimen }}</div>
    @endif
@endif

@if ($legal !== '')
    <div class="legal">{{ $legal }}</div>
@endif

<table class="firma-box no-border">
    <tr>
        <td style="width:50%;">
            <div class="firma-linea"></div>
            Recib&iacute; conforme
        </td>
        <td style="width:50%;">
            <div class="firma-linea"></div>
            Firma agente de retenci&oacute;n
        </td>
    </tr>
</table>
