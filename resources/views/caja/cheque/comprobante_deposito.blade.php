<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Boleta de depósito de cheques</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #17202A; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        h3 { font-size: 11px; margin: 12px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #85C1E9; color: #17202A; padding: 4px; border: 1px solid #cccccc; text-align: left; font-size: 8px; }
        td { padding: 4px; border: 1px solid #cccccc; font-size: 9px; vertical-align: top; }
        table.data { table-layout: fixed; }
        table.data td, table.data th { word-wrap: break-word; overflow-wrap: break-word; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        .meta td { border: none; padding: 2px 4px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .logo { max-height: 48px; max-width: 160px; }
        .muted { color: #555; font-size: 8px; }
        .total-grande {
            margin-top: 10px;
            padding: 6px 8px;
            border: 1px solid #17202A;
            font-size: 12px;
            font-weight: bold;
            text-align: right;
        }
        .importe-letras { margin-top: 6px; font-size: 10px; }
        .firma-box { margin-top: 36px; }
        .firma-box td { border: none; text-align: center; padding-top: 8px; vertical-align: top; }
        .firma-linea { border-top: 1px solid #333; width: 80%; margin: 36px auto 6px auto; }
    </style>
</head>
<body>
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Caja\ChequeDepositoComprobanteSupport;
    use App\Support\Sueldos\NumeroALetrasEs;

    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($cheques);
    $totalPrincipal = $totales[0] ?? ['moneda' => '$', 'monto' => 0.0];
    $importeLetras = mb_strtoupper(NumeroALetrasEs::monto((float) ($totalPrincipal['monto'] ?? 0)), 'UTF-8');
@endphp

<table class="meta" style="margin-bottom:8px;">
    <tr>
        <td style="width:30%;">
            @foreach ($logosCabecera as $logo)
                <img class="logo" src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}">
            @endforeach
        </td>
        <td style="width:70%; vertical-align:middle;">
            <h1>Boleta de depósito de cheques de terceros</h1>
            <div>Generado {{ date('d/m/Y H:i') }}</div>
            <div class="muted">Para archivo de tesorería / banco</div>
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td><strong>Empresa:</strong> {{ $empresa }}</td>
        <td><strong>Fecha depósito:</strong> {{ $fecha_deposito }}</td>
    </tr>
    <tr>
        <td><strong>Cuenta destino:</strong> {{ $cuenta_deposito !== '' ? $cuenta_deposito : '—' }}</td>
        <td><strong>Nro. boleta:</strong> {{ $nro_boleta !== '' ? $nro_boleta : '—' }}</td>
    </tr>
    <tr>
        <td><strong>Cheques:</strong> {{ $total_cantidad }}</td>
        <td><strong>Depositó:</strong> {{ $usuario !== '' ? $usuario : '—' }}</td>
    </tr>
</table>

<h3>Detalle</h3>
<table class="data">
    <thead>
        <tr>
            <th style="width:12%;">Nro. cheque</th>
            <th style="width:8%;">Nro. interno</th>
            <th style="width:15%;">Banco librador</th>
            <th style="width:11%;">Cta. libradora</th>
            <th style="width:16%;">Cliente / entregado</th>
            <th style="width:8%;">Pago</th>
            @if (! $deposito_homogeneo)
                <th style="width:8%;">Depósito</th>
            @endif
            <th style="width:6%;">Mon</th>
            <th class="right" style="width:16%;">Monto</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($cheques as $cheque)
            <tr>
                <td>{{ ChequeDepositoComprobanteSupport::numeroChequeImpresion($cheque) }}</td>
                <td>{{ $cheque->nro_interno_anita }}</td>
                <td>{{ $cheque->bancos->nombre ?? '' }}</td>
                <td>{{ $cheque->cuentalibradora }}</td>
                <td>{{ $cheque->clientes->nombre ?? ($cheque->entregado ?: $cheque->anombrede) }}</td>
                <td>{{ ChequeDepositoComprobanteSupport::fechaDmy($cheque->fechapago) }}</td>
                @if (! $deposito_homogeneo)
                    <td>{{ ChequeDepositoComprobanteSupport::fechaDmy($cheque->fecha_deposito) }}</td>
                @endif
                <td>{{ $cheque->monedas->abreviatura ?? '$' }}</td>
                <td class="right">{{ number_format((float) $cheque->monto, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@foreach ($totales as $tot)
    <div class="total-grande">
        Total {{ $tot['moneda'] }} ({{ $tot['cantidad'] }} cheque{{ $tot['cantidad'] === 1 ? '' : 's' }}):
        {{ number_format((float) $tot['monto'], 2, ',', '.') }}
    </div>
@endforeach

@if (count($totales) === 1)
    <div class="importe-letras">
        <strong>Son:</strong> {{ $importeLetras }} {{ $totalPrincipal['moneda'] }}
    </div>
@endif

<table class="firma-box">
    <tr>
        <td style="width:50%;">
            <div class="firma-linea"></div>
            Tesorería
        </td>
        <td style="width:50%;">
            <div class="firma-linea"></div>
            Banco / recepción
        </td>
    </tr>
</table>
</body>
</html>
