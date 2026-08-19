<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 4px; }
        table.data td { border: 1px solid #cccccc; padding: 3px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
@php
    $logos = $logosCabecera ?? [];
@endphp
@if (!empty($logos))
    <div style="margin-bottom:8px;">
        @foreach($logos as $logo)
            @if (!empty($logo))
                <img src="{{ $logo }}" height="40" style="margin-right:8px;">
            @endif
        @endforeach
    </div>
@endif
<h2>Órdenes de pago a proveedores</h2>
<p>Generado {{ date('d/m/Y H:i') }} — {{ $datas->count() }} registros</p>
<table class="data">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>OP</th>
            <th>Empresa</th>
            <th>Proveedor</th>
            <th>Cuentas de caja</th>
            <th class="text-right">Monto</th>
            <th>Estado</th>
            <th>Detalle</th>
        </tr>
    </thead>
    <tbody>
        @foreach($datas as $fila)
            <tr>
                <td>{{ optional($fila->fecha)->format('d/m/Y') }}</td>
                <td>
                    {{ $fila->etiquetaComprobante() }}
                    @if ($fila instanceof \App\Support\Compras\PagoproveedorListadoFila && $fila->esIeOpp())
                        (IE)
                    @endif
                </td>
                <td>{{ $fila->empresas->nombre ?? '' }}</td>
                <td>{{ $fila->proveedores->nombre ?? '' }}</td>
                <td>
                    @if ($fila instanceof \App\Support\Compras\PagoproveedorListadoFila)
                        {{ implode(' | ', $fila->cuentasCajaLista()) }}
                    @endif
                </td>
                <td class="text-right">{{ number_format((float)$fila->monto, 2, ',', '.') }}</td>
                <td>{{ $fila->estado }}</td>
                <td>{{ $fila->detalle }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
