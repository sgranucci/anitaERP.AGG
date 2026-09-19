<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @include('includes.reportes.estilos_pdf_pagina', [
            'pdf_size' => 'a4 portrait',
            'pdf_margin' => '14mm 16mm',
        ])
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #17202A; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        h3 { font-size: 12px; margin: 14px 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background: #85C1E9; color: #17202A; padding: 4px; border: 1px solid #ccc; text-align: left; }
        td { padding: 4px; border: 1px solid #ccc; }
        .meta td { border: none; padding: 2px 4px; }
        .right { text-align: right; }
        .logo { max-height: 48px; max-width: 160px; }
        .total-grande {
            margin-top: 12px;
            padding: 4px 8px;
            border: 1px solid #17202A;
            font-size: 13px;
            font-weight: bold;
            text-align: right;
        }
        .importe-letras { margin-top: 8px; font-size: 11px; }
        .firma-box { margin-top: 48px; }
        .firma-box td { border: none; text-align: center; padding-top: 8px; vertical-align: top; }
        .firma-linea { border-top: 1px solid #333; width: 80%; margin: 40px auto 6px auto; }
        .muted { color: #555; font-size: 9px; }
    </style>
</head>
<body>
<table class="marco-pdf"><tr>
    <td class="marco-lat"></td>
    <td class="marco-centro">
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Sueldos\NumeroALetrasEs;

    $logo = EmpresaLogoArchivo::dataUriDesdeNombre($cobranza->empresas->nombre ?? ($datosEmpresa['nombre'] ?? null));
    $tipoAbr = $cobranza->tipotransaccioncajas->abreviatura
        ?? $cobranza->tipotransaccioncajas->nombre
        ?? 'COB';
    $tipoNombre = $cobranza->tipotransaccioncajas->nombre ?? $tipoAbr;
    $empresa = $cobranza->empresas;
    $usuarioLogin = optional($cobranza->usuarios)->usuario
        ?: optional($cobranza->usuarios)->nombre
        ?: '';

    $totalAbs = abs((float) ($totalCobranza['monto'] ?? $cobranza->monto ?? 0));
    $monedaAbrTotal = (string) ($totalCobranza['abreviatura'] ?? optional($cobranza->monedas)->abreviatura ?? '');
    $monedaNombre = (string) ($totalCobranza['moneda'] ?? optional($cobranza->monedas)->nombre ?? '');
    $cotizacionMostrada = (float) ($totalCobranza['cotizacion'] ?? $cobranza->cotizacion ?? 0);
    if ($cotizacionMostrada <= 1.0001) {
        $cotizacionMostrada = null;
        foreach ($tblCuenta as $cuenta) {
            $c = (float) ($cuenta['cotizacion'] ?? 0);
            if ($c > 1.0001) {
                $cotizacionMostrada = $c;
                break;
            }
        }
    }

    $importeLetras = mb_strtoupper(NumeroALetrasEs::monto($totalAbs), 'UTF-8');

    $direccionEmpresa = trim((string) ($datosEmpresa['domicilio'] ?? $empresa->domicilio ?? ''));
    $localidadEmpresa = trim((string) (optional(optional($empresa)->localidad)->nombre ?? ''));
    if ($direccionEmpresa !== '' && $localidadEmpresa !== '' && stripos($direccionEmpresa, $localidadEmpresa) === false) {
        $direccionEmpresa .= ' - '.$localidadEmpresa;
    }

    $asiento = $cobranza->asientos;
    $lineasAsiento = $asiento && $asiento->asiento_movimientos ? $asiento->asiento_movimientos : collect();
@endphp

<table class="meta" style="margin-bottom:10px;">
    <tr>
        <td style="width:30%;">
            @if (! empty($logo['uri']))
                <img class="logo" src="{{ $logo['uri'] }}" alt="logo">
            @endif
        </td>
        <td style="width:70%; vertical-align:middle; text-align:right;">
            <h1 style="text-align:right;">Recibo de cobranza {{ $tipoAbr }} {{ $cobranza->numerotransaccion }}</h1>
            <div style="text-align:right;">Generado {{ now()->format('d/m/Y H:i') }}</div>
            <div style="text-align:right;">Fecha {{ $cobranza->fecha ? date('d/m/Y', strtotime($cobranza->fecha)) : '' }}</div>
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td colspan="2"><strong>Empresa:</strong> {{ $datosEmpresa['nombre'] ?? ($empresa->nombre ?? '') }}</td>
    </tr>
    @if ($direccionEmpresa !== '')
        <tr>
            <td colspan="2"><strong>Direcci&oacute;n:</strong> {{ $direccionEmpresa }}</td>
        </tr>
    @endif
    @if (! empty($datosEmpresa['numeroinscripcion'] ?? $empresa->nroinscripcion ?? null))
        <tr>
            <td colspan="2"><strong>CUIT empresa:</strong> {{ $datosEmpresa['numeroinscripcion'] ?? $empresa->nroinscripcion }}</td>
        </tr>
    @endif
    <tr>
        <td><strong>Cliente:</strong> {{ $datosCliente['nombre'] ?? '' }}</td>
        <td><strong>Tipo:</strong> {{ $tipoNombre }}</td>
    </tr>
    <tr>
        <td colspan="2">
            <strong>Datos del cliente:</strong>
            C&oacute;d. {{ $datosCliente['codigo'] ?? '—' }}
            &nbsp;|&nbsp; {{ $datosCliente['tipodocumento'] ?? 'Doc.' }} {{ $datosCliente['numerodocumento'] ?? '—' }}
            &nbsp;|&nbsp; Cond. IVA {{ $datosCliente['condicioniva'] ?? '—' }}
            &nbsp;|&nbsp; Tel. {{ $datosCliente['telefono'] ?: '—' }}
        </td>
    </tr>
    @php
        $domCliente = trim(implode(' ', array_filter([
            $datosCliente['domicilio'] ?? '',
            $datosCliente['localidad'] ?? '',
            isset($datosCliente['codigopostal']) && $datosCliente['codigopostal'] !== '' ? '('.$datosCliente['codigopostal'].')' : '',
            $datosCliente['provincia'] ?? '',
        ])));
    @endphp
    @if ($domCliente !== '')
        <tr>
            <td colspan="2"><strong>Domicilio cliente:</strong> {{ $domCliente }}</td>
        </tr>
    @endif
    @if ($usuarioLogin !== '')
        <tr>
            <td colspan="2"><strong>Usuario:</strong> {{ $usuarioLogin }}</td>
        </tr>
    @endif
    @if (trim((string) ($cobranza->detalle ?? '')) !== '')
        <tr>
            <td colspan="2"><strong>Detalle:</strong> {{ $cobranza->detalle }}</td>
        </tr>
    @endif
    @if ($cotizacionMostrada !== null)
        <tr>
            <td colspan="2"><strong>Cotizaci&oacute;n:</strong> {{ number_format((float) $cotizacionMostrada, 4, ',', '.') }}</td>
        </tr>
    @endif
</table>

<div class="importe-letras">
    <strong>Importe en letras:</strong> {{ $importeLetras }}
    @if ($monedaAbrTotal !== '')
        ({{ $monedaAbrTotal }})
    @elseif ($monedaNombre !== '')
        ({{ $monedaNombre }})
    @endif
</div>

@if (count($tblComprobante) > 0)
    <h3>Comprobantes aplicados</h3>
    <table>
        <thead>
            <tr>
                <th>Comprobante</th>
                <th>Fecha</th>
                <th>Vto.</th>
                <th>Mon</th>
                <th class="right">Cotiz.</th>
                <th class="right">Monto</th>
                <th class="right">Aplicado</th>
                <th class="right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @php $totalAplicado = 0; @endphp
            @foreach ($tblComprobante as $comprobante)
                @php $totalAplicado += (float) $comprobante['aplicado']; @endphp
                <tr>
                    <td>{{ $comprobante['comprobante'] }}</td>
                    <td>{{ date('d/m/Y', strtotime($comprobante['fecha'] ?? '')) }}</td>
                    <td>{{ date('d/m/Y', strtotime($comprobante['fechavencimiento'] ?? '')) }}</td>
                    <td>{{ $comprobante['moneda'] }}</td>
                    <td class="right">{{ number_format((float) $comprobante['cotizacion'], 4, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $comprobante['monto'], 2, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $comprobante['aplicado'], 2, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $comprobante['saldo'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="6"><strong>TOTAL APLICADO</strong></td>
                <td class="right"><strong>{{ number_format($totalAplicado, 2, ',', '.') }}</strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>
@endif

@if (count($tblCuenta) > 0)
    <h3>Cuentas de caja</h3>
    <table>
        <thead>
            <tr>
                <th>Cuenta</th>
                <th class="right">Monto</th>
                <th>Moneda</th>
                <th class="right">Cotiz.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tblCuenta as $cuenta)
                <tr>
                    <td>{{ $cuenta['nombre'] }}</td>
                    <td class="right">{{ number_format((float) $cuenta['monto'], 2, ',', '.') }}</td>
                    <td>{{ $cuenta['moneda'] }}</td>
                    <td class="right">{{ number_format((float) ($cuenta['cotizacion'] ?: 1), 4, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (count($tblCheques) > 0)
    <h3>Cheques de terceros</h3>
    <table>
        <thead>
            <tr>
                <th>Fecha pago</th>
                <th>N&deg; int.</th>
                <th>Tipo</th>
                <th>Nro cheque</th>
                <th>Banco</th>
                <th>Mon</th>
                <th class="right">Cotiz.</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tblCheques as $cheque)
                <tr>
                    <td>{{ date('d/m/Y', strtotime($cheque['fechapago'] ?? '')) }}</td>
                    <td>{{ $cheque['nro_interno_anita'] ?? '' }}</td>
                    <td>{{ $cheque['tipo_instrumento'] ?? '' }}</td>
                    <td>{{ $cheque['numerocheque'] }}</td>
                    <td>{{ $cheque['banco'] }}</td>
                    <td>{{ $cheque['moneda'] }}</td>
                    <td class="right">{{ number_format((float) ($cheque['cotizacion'] ?: 1), 4, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $cheque['monto'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="muted">N&deg; int. = n&uacute;mero interno secuencial (b&uacute;squeda / endoso en cartera).</p>
@endif

@if (count($tblRetenciones) > 0)
    <h3>Retenciones</h3>
    <table>
        <thead>
            <tr>
                <th>Retenci&oacute;n</th>
                <th>Comprobante</th>
                <th class="right">Tasa</th>
                <th>Mon</th>
                <th class="right">Cotiz.</th>
                <th class="right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tblRetenciones as $retencion)
                <tr>
                    <td>{{ $retencion['retencion'] }}</td>
                    <td>{{ $retencion['comprobante'] }}</td>
                    <td class="right">{{ $retencion['tasa'] }}</td>
                    <td>{{ $retencion['moneda'] }}</td>
                    <td class="right">{{ number_format((float) ($retencion['cotizacion'] ?: 1), 4, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $retencion['monto'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($lineasAsiento->count() > 0)
    <h3>Asiento contable{{ $asiento && $asiento->numeroasiento ? ' N&ordm; '.$asiento->numeroasiento : '' }}</h3>
    <table>
        <thead>
            <tr>
                <th>Cuenta</th>
                <th class="right">Debe</th>
                <th class="right">Haber</th>
                <th>Obs.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lineasAsiento as $am)
                @php
                    $montoAm = (float) ($am->monto ?? 0);
                    $debeTxt = $montoAm > 0 ? number_format($montoAm, 2, ',', '.') : '';
                    $haberTxt = $montoAm < 0 ? number_format(abs($montoAm), 2, ',', '.') : '';
                @endphp
                <tr>
                    <td>{{ $am->cuentacontables->codigo ?? $am->cuentacontable_id }} {{ $am->cuentacontables->nombre ?? '' }}</td>
                    <td class="right">{{ $debeTxt }}</td>
                    <td class="right">{{ $haberTxt }}</td>
                    <td>{{ $am->observacion ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="total-grande">
    TOTAL GENERAL:
    @if ($monedaAbrTotal !== '')
        {{ $monedaAbrTotal }}
    @endif
    {{ number_format($totalAbs, 2, ',', '.') }}
</div>

<table class="firma-box meta">
    <tr>
        <td style="width:50%;">
            <div class="firma-linea"></div>
            Recib&iacute; conforme<br>
            <span class="muted">(firma del cliente)</span>
        </td>
        <td style="width:50%;">
            <div class="firma-linea"></div>
            Firma / autorizaci&oacute;n
        </td>
    </tr>
</table>
    </td>
    <td class="marco-lat"></td>
</tr></table>
</body>
</html>
