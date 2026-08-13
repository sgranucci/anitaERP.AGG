<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
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
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Sueldos\NumeroALetrasEs;

    $logo = EmpresaLogoArchivo::dataUriDesdeNombre($movimiento->empresas->nombre ?? null);
    $tipo = $movimiento->tipotransaccioncajas->abreviatura
        ?? $movimiento->tipotransaccioncajas->nombre
        ?? '';
    $sp = $movimiento->solicitudpagos;
    $empresa = $movimiento->empresas;
    $proveedor = $movimiento->proveedores;
    $usuarioLogin = optional($movimiento->usuarios)->usuario
        ?: optional($movimiento->usuarios)->nombre
        ?: '';

    $totalAbs = 0.0;
    $cotizacionMostrada = null;
    $monedaAbrTotal = '';
    foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
        $totalAbs += abs((float) $linea->monto);
        $monedaIdLinea = (int) ($linea->moneda_id ?? 1);
        $cotizLinea = (float) ($linea->cotizacion ?? 0);
        if ($monedaIdLinea > 1 && $cotizLinea > 0 && $cotizacionMostrada === null) {
            $cotizacionMostrada = $cotizLinea;
        }
        if ($monedaAbrTotal === '') {
            $monedaAbrTotal = (string) ($linea->monedas->abreviatura ?? '');
        }
    }
    if ($monedaAbrTotal === '' && $sp) {
        $monedaAbrTotal = (string) (optional($sp->monedas)->abreviatura ?? '');
    }
    if ($cotizacionMostrada === null) {
        foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
            $c = (float) ($linea->cotizacion ?? 0);
            if ($c > 1.0001) {
                $cotizacionMostrada = $c;
                break;
            }
        }
    }

    $conceptoTexto = '';
    if ($sp && $sp->conceptos) {
        $conceptoTexto = trim(($sp->conceptos->codigo ?? '').' '.($sp->conceptos->nombre ?? ''));
    }
    if ($sp && trim((string) ($sp->detalle ?? '')) !== '') {
        $conceptoTexto = $conceptoTexto !== ''
            ? $conceptoTexto.' — '.$sp->detalle
            : $sp->detalle;
    }
    if ($conceptoTexto === '' && trim((string) ($movimiento->detalle ?? '')) !== '') {
        $conceptoTexto = $movimiento->detalle;
    }

    // CC de la imputación (asiento / cuentas SP), no el de cabecera de la SP.
    $centroCostoTexto = '';
    $asientoMovs = optional(optional($movimiento->asientos)->asiento_movimientos) ?: collect();
    foreach ($asientoMovs as $am) {
        if (! empty($am->centrocosto_id) && $am->centrocostos) {
            $centroCostoTexto = trim(($am->centrocostos->codigo ?? '').' '.($am->centrocostos->nombre ?? ''));
            break;
        }
    }
    if ($centroCostoTexto === '' && $sp) {
        foreach ($sp->cuentas ?? [] as $ctaSp) {
            if (! empty($ctaSp->centrocosto_id) && $ctaSp->centrocostos) {
                $centroCostoTexto = trim(($ctaSp->centrocostos->codigo ?? '').' '.($ctaSp->centrocostos->nombre ?? ''));
                break;
            }
        }
    }
    if ($centroCostoTexto === '' && $sp && $sp->centrocostos) {
        $centroCostoTexto = trim(($sp->centrocostos->codigo ?? '').' '.($sp->centrocostos->nombre ?? ''));
    }

    $importeLetras = mb_strtoupper(NumeroALetrasEs::monto($totalAbs), 'UTF-8');
@endphp

<table class="meta" style="margin-bottom:10px;">
    <tr>
        <td style="width:30%;">
            @if (! empty($logo['uri']))
                <img class="logo" src="{{ $logo['uri'] }}" alt="logo">
            @endif
        </td>
        <td style="width:70%; vertical-align:middle;">
            <h1>Orden de pago {{ $tipo }} {{ $movimiento->numerotransaccion }}</h1>
            <div>Generado {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td><strong>Empresa:</strong> {{ $empresa->nombre ?? '' }}</td>
        <td><strong>Fecha:</strong> {{ $movimiento->fecha ? date('d/m/Y', strtotime($movimiento->fecha)) : '' }}</td>
    </tr>
    @php
        $direccionEmpresa = trim((string) ($empresa->domicilio ?? ''));
        $localidadEmpresa = trim((string) (optional($empresa->localidad)->nombre ?? ''));
        if ($direccionEmpresa !== '' && $localidadEmpresa !== '' && stripos($direccionEmpresa, $localidadEmpresa) === false) {
            $direccionEmpresa .= ' - '.$localidadEmpresa;
        }
    @endphp
    @if ($direccionEmpresa !== '')
        <tr>
            <td colspan="2"><strong>Direcci&oacute;n:</strong> {{ $direccionEmpresa }}</td>
        </tr>
    @endif
    @if (! empty($empresa->nroinscripcion))
        <tr>
            <td colspan="2"><strong>CUIT empresa:</strong> {{ $empresa->nroinscripcion }}</td>
        </tr>
    @endif
    <tr>
        <td><strong>Proveedor:</strong> {{ $proveedor->nombre ?? '' }}</td>
        <td><strong>Tipo:</strong> {{ $movimiento->tipotransaccioncajas->nombre ?? $tipo }}</td>
    </tr>
    @if ($proveedor)
        <tr>
            <td colspan="2">
                <strong>Datos del proveedor:</strong>
                CUIT {{ $proveedor->nroinscripcion ?: '—' }}
                &nbsp;|&nbsp; Ing. Brutos {{ $proveedor->nroIIBB ?: '—' }}
                &nbsp;|&nbsp; Cond. IVA {{ optional($proveedor->condicionivas)->nombre ?: '—' }}
                &nbsp;|&nbsp; Tel. {{ $proveedor->telefono ?: '—' }}
            </td>
        </tr>
    @endif
    @if ($usuarioLogin !== '')
        <tr>
            <td colspan="2"><strong>Usuario:</strong> {{ $usuarioLogin }}</td>
        </tr>
    @endif
    @if ($sp)
        <tr>
            <td>
                <strong>Solicitud de pago:</strong> #{{ $sp->codigo }}
                (id {{ $sp->id }})
            </td>
            <td><strong>Estado SP:</strong> {{ $sp->estado }}</td>
        </tr>
    @endif
    @if ($conceptoTexto !== '')
        <tr>
            <td colspan="2"><strong>Concepto:</strong> {{ $conceptoTexto }}</td>
        </tr>
    @endif
    @if ($centroCostoTexto !== '')
        <tr>
            <td colspan="2"><strong>Centro de costos:</strong> {{ $centroCostoTexto }}</td>
        </tr>
    @endif
    @if ($cotizacionMostrada !== null)
        <tr>
            <td colspan="2"><strong>Cotizaci&oacute;n:</strong> {{ number_format((float) $cotizacionMostrada, 4, ',', '.') }}</td>
        </tr>
    @endif
    @if (! $sp && trim((string) ($movimiento->detalle ?? '')) !== '')
        <tr>
            <td colspan="2"><strong>Detalle:</strong> {{ $movimiento->detalle }}</td>
        </tr>
    @endif
</table>

<div class="importe-letras">
    <strong>Importe en letras:</strong> {{ $importeLetras }}
    @if ($monedaAbrTotal !== '')
        ({{ $monedaAbrTotal }})
    @endif
</div>

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
        @forelse ($movimiento->caja_movimiento_cuentacajas as $linea)
            <tr>
                <td>{{ $linea->cuentacajas->codigo ?? '' }} — {{ $linea->cuentacajas->nombre ?? '' }}</td>
                <td class="right">{{ number_format((float) $linea->monto, 2, ',', '.') }}</td>
                <td>{{ $linea->monedas->abreviatura ?? $linea->moneda_id }}</td>
                <td class="right">{{ number_format((float) ($linea->cotizacion ?: 1), 4, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Sin cuentas de caja</td></tr>
        @endforelse
    </tbody>
</table>

@if ($movimiento->cheques && $movimiento->cheques->count() > 0)
    <h3>Cheques</h3>
    <table>
        <thead>
            <tr>
                <th>Nro</th>
                <th>Banco</th>
                <th>Origen</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($movimiento->cheques as $cheque)
                <tr>
                    <td>{{ $cheque->numerocheque }}</td>
                    <td>{{ $cheque->bancos->nombre ?? '' }}</td>
                    <td>{{ $cheque->origen }}</td>
                    <td class="right">{{ number_format((float) $cheque->importe, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@php
    $asiento = $movimiento->asientos;
    $lineasAsiento = $asiento && $asiento->asiento_movimientos ? $asiento->asiento_movimientos : collect();
@endphp
@if ($lineasAsiento->count() > 0)
    <h3>Asiento contable{{ $asiento && $asiento->numeroasiento ? ' Nº '.$asiento->numeroasiento : '' }}</h3>
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
            <span class="muted">(firma del proveedor)</span>
        </td>
        <td style="width:50%;">
            <div class="firma-linea"></div>
            Firma / autorizaci&oacute;n
        </td>
    </tr>
</table>
</body>
</html>
