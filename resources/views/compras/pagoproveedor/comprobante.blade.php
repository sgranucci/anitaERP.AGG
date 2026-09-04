<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $nroOp }}</title>
    <style>
        @page { margin: 14mm 12mm 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #17202A; margin: 0; }
        h1 { font-size: 15px; margin: 0 0 2px; color: #0d3b66; }
        h2 {
            font-size: 10px; margin: 10px 0 4px; padding: 3px 6px;
            background: #85C1E9; color: #17202A; border: 1px solid #5dade2;
        }
        h3 {
            font-size: 9px; margin: 8px 0 3px; padding: 2px 5px;
            background: #d6eaf8; color: #1B4F72; border: 1px solid #85C1E9;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        th, td { border: 1px solid #b0b0b0; padding: 2px 4px; vertical-align: top; }
        th { background: #85C1E9; color: #17202A; font-weight: bold; text-align: left; font-size: 8px; }
        .no-border td { border: none !important; padding: 1px 3px; }
        .lbl { background: #f4f6f7; font-weight: bold; white-space: nowrap; width: 14%; }
        .muted { color: #555; font-size: 8px; }
        .num { text-align: right; white-space: nowrap; }
        .cen { text-align: center; }
        .logo { max-height: 48px; max-width: 150px; }
        .total-box {
            margin-top: 6px; padding: 4px 8px; border: 1.5px solid #0d3b66;
            font-size: 11px; font-weight: bold; text-align: right;
        }
        .firma-box { margin-top: 28px; }
        .firma-box td { border: none; text-align: center; padding-top: 6px; vertical-align: top; }
        .firma-linea { border-top: 1px solid #333; width: 75%; margin: 32px auto 4px auto; }
        .page-break { page-break-before: always; }
        .badge-op {
            display: inline-block; border: 1px solid #0d3b66; padding: 2px 8px;
            font-weight: bold; font-size: 11px; letter-spacing: 0.3px;
        }
        .legal { font-size: 8px; margin-top: 14px; color: #333; line-height: 1.35; }
        .ret-titulo { font-size: 12px; font-weight: bold; color: #0d3b66; text-align: center; margin: 8px 0 6px; }
        .ret-subtitulo { font-size: 9px; text-align: center; margin-bottom: 10px; color: #555; }
        .montos-ret td { padding: 3px 6px; }
        .montos-ret .lbl { width: 55%; }
    </style>
</head>
<body>

@if (empty($soloRetencion))
{{-- ===================== HOJA PRINCIPAL OP ===================== --}}
<table class="no-border" style="margin-bottom:6px;">
    <tr>
        <td style="width:38%;">
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
        <td style="width:62%; text-align:right; vertical-align:top;">
            <h1>Orden de pago</h1>
            <div class="badge-op" style="margin-top:4px;">{{ $nroOp }}</div>
            <div style="margin-top:6px;"><strong>Lugar y fecha:</strong> {{ $lugarFecha }}</div>
            <div class="muted" style="margin-top:2px;">Generado {{ $generadoEn }}
                @if ($usuarioLogin !== '')
                    &nbsp;|&nbsp; Usuario: {{ $usuarioLogin }}
                @endif
            </div>
            <div style="margin-top:4px;">
                <span class="badge-op" style="font-size:9px;border-color:#555;">ESTADO: {{ $pago->estado }}</span>
            </div>
        </td>
    </tr>
</table>

<h2>Proveedor</h2>
<table>
    <tr>
        <td class="lbl">C&oacute;digo</td>
        <td>{{ $proveedor ? str_pad((string) $proveedor->codigo, 6, '0', STR_PAD_LEFT) : '—' }}</td>
        <td class="lbl">Raz&oacute;n social</td>
        <td colspan="3">{{ $proveedor->nombre ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Domicilio</td>
        <td colspan="5">
            {{ $proveedor->domicilio ?? '—' }}
            @if ($proveedor && $proveedor->codigopostal)
                &nbsp;(CP {{ $proveedor->codigopostal }})
            @endif
            @if ($proveedor && optional($proveedor->localidades)->nombre)
                — {{ $proveedor->localidades->nombre }}
            @endif
            @if ($proveedor && optional($proveedor->provincias)->nombre)
                , {{ $proveedor->provincias->nombre }}
            @endif
        </td>
    </tr>
    <tr>
        <td class="lbl">CUIT</td>
        <td>{{ $proveedor->nroinscripcion ?? '—' }}</td>
        <td class="lbl">Cond. IVA</td>
        <td>{{ optional(optional($proveedor)->condicionivas)->nombre ?? '—' }}</td>
        <td class="lbl">Ing. Brutos</td>
        <td>{{ $proveedor->nroIIBB ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Tel&eacute;fono</td>
        <td>{{ $proveedor->telefono ?? '—' }}</td>
        <td class="lbl">Contacto</td>
        <td colspan="3">{{ $proveedor->contacto ?? '—' }}</td>
    </tr>
</table>

@if (trim((string) ($pago->detalle ?? '')) !== '')
    <table style="margin-top:4px;">
        <tr>
            <td class="lbl">Concepto</td>
            <td>{{ $pago->detalle }}</td>
        </tr>
    </table>
@endif

<h2>Comprobantes aplicados</h2>
<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Tipo / N&uacute;mero</th>
            <th>Nro. int.</th>
            <th class="num">Monto</th>
            <th>Mda.</th>
            <th class="num">Cotizaci&oacute;n</th>
            <th class="num">Monto apl.</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($aplicaciones as $apl)
            <tr>
                <td>{{ $apl['fecha'] }}</td>
                <td>{{ $apl['numero'] }}</td>
                <td>{{ $apl['nro_int'] }}</td>
                <td class="num">{{ number_format($apl['monto'], 2, ',', '.') }}</td>
                <td>{{ $apl['moneda'] }}</td>
                <td class="num">{{ number_format($apl['cotizacion'], 4, ',', '.') }}</td>
                <td class="num">{{ number_format($apl['monto_aplicado'], 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="cen muted">Sin aplicaciones a cuenta corriente (anticipo / OPA).</td></tr>
        @endforelse
    </tbody>
</table>

@if ($cheques->count() > 0)
    <h2>Cheques entregados</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Nro. cheque</th>
                <th>Banco</th>
                <th>Car&aacute;cter</th>
                <th class="num">Monto</th>
                <th>Mda.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cheques as $cheque)
                <tr>
                    <td>{{ optional($cheque->fechavencimiento)->format('d/m/Y') ?: optional($cheque->fecha)->format('d/m/Y') }}</td>
                    <td>{{ $cheque->numerocheque }}</td>
                    <td>{{ optional($cheque->bancos)->nombre }}</td>
                    <td>{{ $cheque->caracter ?? $cheque->origen ?? '' }}</td>
                    <td class="num">{{ number_format((float) $cheque->importe, 2, ',', '.') }}</td>
                    <td>{{ optional($cheque->monedas)->abreviatura }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($mediosCaja->count() > 0)
    <h2>Valores / cuentas de caja</h2>
    <table>
        <thead>
            <tr>
                <th>Cuenta</th>
                <th class="num">Monto</th>
                <th>Mda.</th>
                <th class="num">Cotiz.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($mediosCaja as $med)
                <tr>
                    <td>{{ $med['cuenta'] }}</td>
                    <td class="num">{{ number_format($med['monto_abs'], 2, ',', '.') }}</td>
                    <td>{{ $med['moneda'] }}</td>
                    <td class="num">{{ number_format($med['cotizacion'], 4, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($retenciones->count() > 0)
    <h2>Retenciones</h2>
    <table>
        <thead>
            <tr>
                <th>Concepto</th>
                <th>Certificado</th>
                <th>R&eacute;gimen</th>
                <th class="num">Base</th>
                <th class="num">Al&iacute;cuota %</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($retenciones as $ret)
                <tr>
                    <td>{{ $ret->etiquetaTipo() }}</td>
                    <td>{{ $ret->nro_certificado ?? '—' }}</td>
                    <td>{{ $ret->codigo_regimen ?: ($ret->codigo_retencion ?: '—') }}</td>
                    <td class="num">{{ number_format((float) $ret->base_calculo, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $ret->alicuota, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $ret->importe, 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="5" class="num" style="font-weight:bold;">Total retenciones</td>
                <td class="num" style="font-weight:bold;">{{ number_format($totalRetenciones, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($asientoLineas->count() > 0)
    <h2>Asiento contable{{ optional($pago->asientos)->numeroasiento ? ' N° '.optional($pago->asientos)->numeroasiento : '' }}</h2>
    <table>
        <thead>
            <tr>
                <th>Cuenta</th>
                <th class="num">Debe</th>
                <th class="num">Haber</th>
                <th>C. costo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($asientoLineas as $lin)
                <tr>
                    <td>{{ $lin['cuenta'] }}</td>
                    <td class="num">{{ $lin['debe'] !== null ? number_format($lin['debe'], 2, ',', '.') : '' }}</td>
                    <td class="num">{{ $lin['haber'] !== null ? number_format($lin['haber'], 2, ',', '.') : '' }}</td>
                    <td>{{ $lin['centrocosto'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<table class="no-border" style="margin-top:8px;">
    <tr>
        <td style="width:65%;">
            <strong>Importe en letras:</strong><br>
            Son {{ $importeLetras }}
            @if ($monedaAbr !== '')
                ({{ $monedaAbr }})
            @endif
            @if ($cotizacion > 1.0001)
                <div class="muted" style="margin-top:4px;">Cotizaci&oacute;n: {{ number_format($cotizacion, 4, ',', '.') }}</div>
            @endif
        </td>
        <td style="width:35%;">
            <div class="total-box">
                TOTAL OP
                @if ($monedaAbr !== '')
                    {{ $monedaAbr }}
                @endif
                {{ number_format($totalOp, 2, ',', '.') }}
            </div>
        </td>
    </tr>
</table>

<table class="firma-box no-border">
    <tr>
        <td style="width:50%;">
            <div class="firma-linea"></div>
            Recib&iacute; conforme<br>
            <span class="muted">(firma del proveedor)</span>
        </td>
        <td style="width:50%;">
            <div class="firma-linea"></div>
            Firma / autorizaci&oacute;n
        </td>
    </tr>
</table>
@endif

{{-- ===================== PÁGINAS DE RETENCIÓN ===================== --}}
@foreach ($retencionesPaginas as $retencion)
    @include('compras.pagoproveedor.partials.comprobante_retencion', [
        'retencion' => $retencion,
        'pago' => $pago,
        'empresa' => $empresa,
        'proveedor' => $proveedor,
        'logo' => $logo,
        'direccionEmpresa' => $direccionEmpresa,
        'nroOp' => $nroOp,
        'lugarFecha' => $lugarFecha,
        'aplicaciones' => $aplicaciones,
        'totalOp' => $totalOp,
        'fechaDdjjGanancias' => $fechaDdjjGanancias,
        'periodoSuss' => $periodoSuss,
        'leyendasLegales' => $leyendasLegales,
        'pageBreak' => empty($soloRetencion) || ! $loop->first,
    ])
@endforeach

</body>
</html>
