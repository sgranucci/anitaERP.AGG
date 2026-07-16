<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #17202A; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #85C1E9; color: #17202A; padding: 4px; border: 1px solid #ccc; }
        td { padding: 4px; border: 1px solid #ccc; }
        .meta td { border: none; padding: 2px 4px; }
    </style>
</head>
<body>
    <h1>Orden de pago {{ $pago->etiquetaComprobante() }}</h1>
    <table class="meta">
        <tr><td><strong>Empresa:</strong> {{ $pago->empresas->nombre ?? '' }}</td><td><strong>Fecha:</strong> {{ optional($pago->fecha)->format('d/m/Y') }}</td></tr>
        <tr><td><strong>Proveedor:</strong> {{ $pago->proveedores->nombre ?? '' }}</td><td><strong>Estado:</strong> {{ $pago->estado }}</td></tr>
        <tr><td colspan="2"><strong>Detalle:</strong> {{ $pago->detalle }}</td></tr>
        <tr><td><strong>Monto:</strong> {{ number_format((float)$pago->monto, 2, ',', '.') }} {{ $pago->monedas->abreviatura ?? '' }}</td><td><strong>Cotización:</strong> {{ $pago->cotizacion }}</td></tr>
    </table>

    <h3>Aplicaciones</h3>
    <table>
        <thead><tr><th>CC</th><th class="text-right">Monto</th><th>Moneda</th><th>Cotiz.</th></tr></thead>
        <tbody>
            @forelse($aplicaciones as $apl)
                <tr>
                    <td>#{{ $apl->proveedor_cuentacorriente_id }}</td>
                    <td style="text-align:right">{{ number_format((float)$apl->montoaplicado, 2, ',', '.') }}</td>
                    <td>{{ $apl->monedas->abreviatura ?? $apl->moneda_id }}</td>
                    <td>{{ $apl->cotizacion }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Sin aplicaciones</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Retenciones</h3>
    <table>
        <thead><tr><th>Tipo</th><th>Cert.</th><th class="text-right">Importe</th><th>Alícuota</th></tr></thead>
        <tbody>
            @forelse($retenciones as $ret)
                <tr>
                    <td>{{ $ret->etiquetaTipo() }}</td>
                    <td>{{ $ret->nro_certificado }}</td>
                    <td style="text-align:right">{{ number_format((float)$ret->importe, 2, ',', '.') }}</td>
                    <td>{{ number_format((float)$ret->alicuota, 4, ',', '.') }}%</td>
                </tr>
            @empty
                <tr><td colspan="4">Sin retenciones</td></tr>
            @endforelse
        </tbody>
    </table>
    <p style="margin-top:20px;font-size:9px;">Generado {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
