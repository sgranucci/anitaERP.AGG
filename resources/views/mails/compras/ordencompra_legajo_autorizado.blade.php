<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Legajo autorizado</title>
</head>
<body style="font-family: DejaVu Sans, Arial, sans-serif; font-size: 14px; color: #222;">
    <p>El referente de Gastronomía autorizó el legajo. Ya está en <strong>Cuentas a pagar</strong>.</p>
    <ul>
        <li><strong>OC:</strong> {{ $datos['numero_oc'] ?? '' }}</li>
        <li><strong>Empresa:</strong> {{ $datos['empresa'] ?? '' }}</li>
        <li><strong>Proveedor:</strong> {{ $datos['proveedor'] ?? '' }}</li>
        @if (! empty($datos['firmante']))
            <li><strong>Autorizó:</strong> {{ $datos['firmante'] }}</li>
        @endif
    </ul>
    @if (! empty($datos['url']))
        <p><a href="{{ $datos['url'] }}">Abrir orden de compra</a></p>
    @endif
</body>
</html>
