<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $numero }}</title>
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #17202A; margin: 0; }
        h1 { font-size: 16px; margin: 0 0 2px; color: #0d3b66; }
        h2 {
            font-size: 10px; margin: 12px 0 4px; padding: 3px 6px;
            background: #85C1E9; color: #17202A; border: 1px solid #5dade2;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        th, td { border: 1px solid #b0b0b0; padding: 3px 5px; vertical-align: top; }
        th { background: #85C1E9; color: #17202A; font-weight: bold; text-align: left; font-size: 8px; }
        .no-border td { border: none !important; padding: 1px 3px; }
        .lbl { background: #f4f6f7; font-weight: bold; white-space: nowrap; width: 14%; }
        .muted { color: #555; font-size: 8px; }
        .num { text-align: right; white-space: nowrap; }
        .cen { text-align: center; }
        .logo { max-height: 52px; max-width: 160px; }
        .badge {
            display: inline-block; border: 1.5px solid #0d3b66; padding: 3px 10px;
            font-weight: bold; font-size: 12px; letter-spacing: 0.4px;
        }
        .badge-soft {
            display: inline-block; border: 1px solid #555; padding: 2px 8px;
            font-weight: bold; font-size: 9px;
        }
        .marca-interno {
            margin-top: 6px; padding: 4px 8px; background: #eaf2f8; border: 1px solid #85C1E9;
            font-size: 8px; color: #1B4F72; text-align: center; letter-spacing: 0.3px;
        }
        .total-box {
            margin-top: 8px; padding: 6px 10px; border: 1.5px solid #0d3b66;
            font-size: 12px; font-weight: bold;
        }
        .letras { font-size: 9px; font-weight: normal; margin-top: 3px; color: #333; }
        .firma-box { margin-top: 36px; }
        .firma-box td { border: none; text-align: center; padding-top: 6px; vertical-align: top; }
        .firma-linea { border-top: 1px solid #333; width: 78%; margin: 36px auto 4px auto; }
        .legal { font-size: 7.5px; margin-top: 18px; color: #555; line-height: 1.4; border-top: 1px solid #ccc; padding-top: 6px; }
        .pie { font-size: 7px; color: #888; margin-top: 8px; text-align: center; }
    </style>
</head>
<body>

<table class="no-border" style="margin-bottom:8px;">
    <tr>
        <td style="width:40%;">
            @if (! empty($logo['uri']))
                <img class="logo" src="{{ $logo['uri'] }}" alt="">
            @endif
            <div style="font-size:12px;font-weight:bold;margin-top:4px;">{{ $empresa->nombre ?? '' }}</div>
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
        <td style="width:60%; text-align:right; vertical-align:top;">
            <h1>{{ $titulo }}</h1>
            <div class="badge" style="margin-top:4px;">{{ $numero }}</div>
            <div style="margin-top:8px;"><strong>Lugar y fecha:</strong> {{ $lugarFecha }}</div>
            <div class="muted" style="margin-top:2px;">
                Generado {{ $generadoEn }}
                @if ($usuarioLogin !== '')
                    &nbsp;|&nbsp; Usuario: {{ $usuarioLogin }}
                @endif
            </div>
            @if ($estado !== '')
                <div style="margin-top:5px;">
                    <span class="badge-soft">ESTADO: {{ $estado }}</span>
                </div>
            @endif
            <div class="marca-interno">
                DOCUMENTO INTERNO GENERADO POR EL ERP &mdash; NO ES COMPROBANTE FISCAL
            </div>
        </td>
    </tr>
</table>

<h2>Proveedor / beneficiario</h2>
<table>
    <tr>
        <td class="lbl">C&oacute;digo</td>
        <td>{{ $proveedor ? str_pad((string) $proveedor->codigo, 6, '0', STR_PAD_LEFT) : '—' }}</td>
        <td class="lbl">Raz&oacute;n social</td>
        <td colspan="3">{{ $proveedor->nombre ?? ($comprobante->proveedor_nombre_eventual ?? '—') }}</td>
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
        <td>{{ $proveedor->nroinscripcion ?? ($comprobante->proveedor_documento_eventual ?? '—') }}</td>
        <td class="lbl">Cond. IVA</td>
        <td>{{ optional(optional($proveedor)->condicionivas)->nombre ?? '—' }}</td>
        <td class="lbl">Ing. Brutos</td>
        <td>{{ $proveedor->nroIIBB ?? '—' }}</td>
    </tr>
</table>

<h2>Datos del comprobante</h2>
<table>
    <tr>
        <td class="lbl">Tipo</td>
        <td>{{ $tipo->nombre ?? $titulo }}</td>
        <td class="lbl">Abreviatura</td>
        <td>{{ $tipo->abreviatura ?? '—' }}</td>
        <td class="lbl">N&uacute;mero</td>
        <td>{{ $numero }}</td>
    </tr>
    <tr>
        <td class="lbl">F. comprobante</td>
        <td>{{ $fechaComprobante ?: '—' }}</td>
        <td class="lbl">F. vencimiento</td>
        <td>{{ $fechaVencimiento ?: '—' }}</td>
        <td class="lbl">F. IVA</td>
        <td>{{ $fechaIva ?: '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Concepto de gasto</td>
        <td colspan="3">{{ $conceptoGasto !== '' ? $conceptoGasto : '—' }}</td>
        <td class="lbl">Cond. de pago</td>
        <td>{{ $condicionPago !== '' ? $condicionPago : '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Moneda</td>
        <td>{{ $nombreMoneda }}</td>
        <td class="lbl">Cotizaci&oacute;n</td>
        <td class="num">{{ number_format($cotizacion, 4, ',', '.') }}</td>
        <td class="lbl">Asiento</td>
        <td>
            @if ($asientoNumero > 0)
                {{ $asientoNumero }}
                @if ($asientoFecha !== '')
                    <span class="muted">({{ $asientoFecha }})</span>
                @endif
            @else
                —
            @endif
        </td>
    </tr>
    @if ($leyenda !== '')
        <tr>
            <td class="lbl">Leyenda</td>
            <td colspan="5">{{ $leyenda }}</td>
        </tr>
    @endif
</table>

@if ($conceptos->isNotEmpty())
    <h2>Conceptos / IVA</h2>
    <table>
        <thead>
            <tr>
                <th style="width:8%;">#</th>
                <th style="width:42%;">Concepto</th>
                <th style="width:30%;">Cuenta contable</th>
                <th style="width:20%;" class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($conceptos as $i => $linea)
                <tr>
                    <td class="cen">{{ $linea->orden ?: ($i + 1) }}</td>
                    <td>{{ $linea->concepto_ivacompras->nombre ?? '—' }}</td>
                    <td>
                        @php $cta = $linea->cuentacontablesdebe; @endphp
                        @if ($cta)
                            {{ $cta->codigo ?? '' }}
                            @if (! empty($cta->nombre))
                                — {{ $cta->nombre }}
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="num">{{ $simboloMoneda }} {{ number_format((float) $linea->monto, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($cuotas->isNotEmpty())
    <h2>Cuotas / vencimientos</h2>
    <table>
        <thead>
            <tr>
                <th style="width:10%;">Cuota</th>
                <th style="width:18%;">Vencimiento</th>
                <th style="width:22%;">Importe</th>
                <th style="width:22%;">Pagado</th>
                <th style="width:28%;">Detalle</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cuotas as $cuota)
                <tr>
                    <td class="cen">{{ $cuota->numero_cuota }}</td>
                    <td class="cen">
                        {{ $cuota->fechavencimiento ? \Carbon\Carbon::parse($cuota->fechavencimiento)->format('d/m/Y') : '—' }}
                    </td>
                    <td class="num">{{ $simboloMoneda }} {{ number_format((float) $cuota->monto, 2, ',', '.') }}</td>
                    <td class="num">{{ $simboloMoneda }} {{ number_format((float) ($cuota->total_pagado ?? 0), 2, ',', '.') }}</td>
                    <td>{{ $cuota->detalle ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="total-box">
    <table class="no-border" style="margin:0;">
        <tr>
            <td style="text-align:left; width:55%;">
                <div class="muted">Subtotal: {{ $simboloMoneda }} {{ number_format($subtotal, 2, ',', '.') }}</div>
                <div class="letras">Son {{ $importeLetras }} {{ mb_strtoupper($nombreMoneda, 'UTF-8') }}</div>
            </td>
            <td style="text-align:right; width:45%; vertical-align:middle;">
                TOTAL&nbsp;&nbsp;{{ $simboloMoneda }} {{ number_format($total, 2, ',', '.') }}
            </td>
        </tr>
    </table>
</div>

<table class="firma-box no-border">
    <tr>
        <td style="width:33%;">
            <div class="firma-linea"></div>
            <div>Preparado por</div>
            <div class="muted">Administraci&oacute;n / Cuentas a pagar</div>
        </td>
        <td style="width:34%;">
            <div class="firma-linea"></div>
            <div>Revisado por</div>
            <div class="muted">Contabilidad</div>
        </td>
        <td style="width:33%;">
            <div class="firma-linea"></div>
            <div>Autorizado por</div>
            <div class="muted">Gerencia / Firmante</div>
        </td>
    </tr>
</table>

<div class="legal">
    Este documento es un comprobante interno emitido por el sistema {{ config('app.name', 'anitaERP') }}
    para respaldar movimientos de tipo {{ $tipo->abreviatura ?? 'FIN' }}
    ({{ $tipo->nombre ?? 'comprobante interno' }}).
    No reemplaza facturas, notas de cr&eacute;dito ni recibos fiscales del proveedor.
    Conservar junto a la documentaci&oacute;n contable del asiento asociado.
</div>

<div class="pie">
    {{ $empresa->nombre ?? '' }} &mdash; Comprobante interno #{{ $comprobante->id }}
    &mdash; Generado el {{ $generadoEn }}
</div>

</body>
</html>
