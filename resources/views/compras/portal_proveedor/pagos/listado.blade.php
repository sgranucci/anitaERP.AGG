@php
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $logosCabecera = $logos ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Pagos del proveedor</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 4px; vertical-align: top; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 30%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 160px; margin-right: 8px;">
                @endforeach
            </td>
            <td style="width: 45%; text-align: center;">
                <h2 style="margin: 0; font-size: 16px; font-weight: bold;">Pagos del proveedor</h2>
                <div class="meta">{{ $proveedor->nombre }} · CUIT {{ $proveedor->nroinscripcion ?: '—' }}</div>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo ?? '' }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                Registros: {{ $totalFilas }}<br>
                Monto: {{ number_format((float) ($resumen['monto_pagado'] ?? 0), 2, ',', '.') }}<br>
                Retenciones: {{ number_format((float) ($resumen['monto_retenciones'] ?? 0), 2, ',', '.') }}
            </td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Orden de pago</th>
                <th>Empresa</th>
                <th class="text-right">Monto</th>
                <th class="text-right">Retenciones</th>
                <th class="text-right">Neto</th>
                <th>Estado</th>
                <th>Moneda</th>
                <th class="text-right">Cert.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $pago)
                @php
                    $ret = (float) ($pago->total_retenciones ?? 0);
                    $neto = (float) $pago->monto - $ret;
                @endphp
                <tr>
                    <td>{{ optional($pago->fecha)->format('d/m/Y') }}</td>
                    <td>{{ $pago->etiquetaComprobante() }}</td>
                    <td>{{ $pago->empresas->nombre ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) $pago->monto, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($ret, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($neto, 2, ',', '.') }}</td>
                    <td>{{ $pago->estado }}</td>
                    <td>{{ $pago->monedas->abreviatura ?? '' }}</td>
                    <td class="text-right">{{ (int) ($pago->pagoproveedor_retenciones_count ?? 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;">Sin pagos en el período</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
