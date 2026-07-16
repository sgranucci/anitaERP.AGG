<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #17202A; }
        h1 { font-size: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #85C1E9; padding: 5px; border: 1px solid #ccc; }
        td { padding: 5px; border: 1px solid #ccc; }
    </style>
</head>
<body>
    <h1>Comprobante de retención — {{ $retencion->etiquetaTipo() }}</h1>
    <p>
        OP {{ $pago->etiquetaComprobante() }} · {{ optional($pago->fecha)->format('d/m/Y') }}<br>
        Proveedor: {{ $pago->proveedores->nombre ?? '' }} · CUIT {{ $pago->proveedores->cuit ?? '' }}<br>
        Certificado N° {{ $retencion->nro_certificado ?? '—' }}
    </p>
    <table>
        <tr><th>Base</th><td style="text-align:right">{{ number_format((float)$retencion->base_calculo, 2, ',', '.') }}</td></tr>
        <tr><th>Alícuota</th><td style="text-align:right">{{ number_format((float)$retencion->alicuota, 4, ',', '.') }}%</td></tr>
        <tr><th>Importe retenido</th><td style="text-align:right"><strong>{{ number_format((float)$retencion->importe, 2, ',', '.') }}</strong></td></tr>
        <tr><th>Motivo / régimen</th><td>{{ $retencion->motivo }} {{ $retencion->codigo_regimen }}</td></tr>
    </table>
    <p style="margin-top:24px;font-size:9px;">Generado {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
