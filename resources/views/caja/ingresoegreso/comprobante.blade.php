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
    </style>
</head>
<body>
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logo = EmpresaLogoArchivo::dataUriDesdeNombre($movimiento->empresas->nombre ?? null);
    $tipo = $movimiento->tipotransaccioncajas->abreviatura
        ?? $movimiento->tipotransaccioncajas->nombre
        ?? '';
    $sp = $movimiento->solicitudpagos;
    $totalAbs = 0.0;
    foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
        $coef = ((int) ($linea->moneda_id ?? 1) > 1) ? (float) ($linea->cotizacion ?: 1) : 1.0;
        $totalAbs += abs((float) $linea->monto) * $coef;
    }
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
        <td><strong>Empresa:</strong> {{ $movimiento->empresas->nombre ?? '' }}</td>
        <td><strong>Fecha:</strong> {{ $movimiento->fecha ? date('d/m/Y', strtotime($movimiento->fecha)) : '' }}</td>
    </tr>
    <tr>
        <td><strong>Proveedor:</strong> {{ $movimiento->proveedores->nombre ?? '' }}</td>
        <td><strong>Tipo:</strong> {{ $movimiento->tipotransaccioncajas->nombre ?? $tipo }}</td>
    </tr>
    @if ($sp)
        <tr>
            <td>
                <strong>Solicitud de pago:</strong> #{{ $sp->codigo }}
                (id {{ $sp->id }})
            </td>
            <td><strong>Estado SP:</strong> {{ $sp->estado }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="2"><strong>Detalle:</strong> {{ $movimiento->detalle }}</td>
    </tr>
    <tr>
        <td colspan="2"><strong>Total:</strong> {{ number_format($totalAbs, 2, ',', '.') }}</td>
    </tr>
</table>

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
</body>
</html>
