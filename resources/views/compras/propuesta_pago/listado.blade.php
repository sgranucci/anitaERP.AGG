<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 3px; }
        table.data td { border: 1px solid #cccccc; padding: 2px 3px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .meta { margin-bottom: 8px; }
    </style>
</head>
<body>
@php
    use App\Support\Compras\PropuestaPagoLineaPresentacionSupport;
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([(object)['nombreempresa' => $data->empresas->nombre ?? '']]));
@endphp
<div class="meta">
    @foreach($logos as $logo)
        @if (! empty($logo['uri']))
            <img src="{{ $logo['uri'] }}" height="40" style="margin-right:8px;">
        @endif
    @endforeach
    <h2 style="margin:4px 0;">Propuesta de pagos #{{ $data->id }}</h2>
    <div>Generado {{ now()->format('d/m/Y H:i') }} — Estado {{ $data->estado }} — Empresa {{ $data->empresas->nombre ?? '' }}</div>
    <div>
        Fecha {{ optional($data->fecha)->format('d/m/Y') }}
        — Autorizado {{ number_format((float)($data->monto_autorizado ?: $data->monto_total), 2, ',', '.') }}
        — A ejecutar {{ number_format((float)$data->monto_total, 2, ',', '.') }}
    </div>
    @if ($data->detalle)
        <div>Detalle: {{ $data->detalle }}</div>
    @endif
</div>
@php $resumen = PropuestaPagoLineaPresentacionSupport::resumenBuckets($data->lineas ?? collect(), $data); @endphp
<p>
    Vencidos: {{ number_format($resumen['vencidos'], 2, ',', '.') }}
    | A vencer: {{ number_format($resumen['a_vencer'], 2, ',', '.') }}
    | Total: {{ number_format($resumen['total'], 2, ',', '.') }}
</p>
<table class="data">
    <thead>
        <tr>
            <th>Incl</th>
            <th>N.Pro</th>
            <th>Nombre</th>
            <th>Tip</th>
            <th>Comprobante</th>
            <th>F.Vto</th>
            <th>M.Pago</th>
            <th>Detalle pago</th>
            <th>Saldo</th>
            <th>Monto</th>
            <th>Bucket</th>
        </tr>
    </thead>
    <tbody>
        @foreach(($data->lineas ?? []) as $linea)
            @php $p = PropuestaPagoLineaPresentacionSupport::enriquecer($linea, $data); @endphp
            <tr>
                <td>{{ $linea->incluido ? 'Sí' : 'No' }}</td>
                <td>{{ $p['codigo_proveedor'] }}</td>
                <td>{{ $p['nombre_proveedor'] }}</td>
                <td>{{ $p['tipo'] }}</td>
                <td>{{ $p['comprobante'] }}</td>
                <td>{{ $p['fecha_vto'] }}</td>
                <td>{{ $p['medio_pago'] }}</td>
                <td>{{ $p['detalle_pago'] }}</td>
                <td style="text-align:right">{{ number_format((float)$linea->saldo_deuda, 2, ',', '.') }}</td>
                <td style="text-align:right">{{ number_format((float)$linea->monto_propuesto, 2, ',', '.') }}</td>
                <td>{{ $p['bucket'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
