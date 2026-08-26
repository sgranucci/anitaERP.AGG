<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Legajo pendiente en Gastronomía</title>
</head>
<body style="font-family: DejaVu Sans, Arial, sans-serif; font-size: 14px; color: #222;">
    <p>El legajo sigue en <strong>Gastronomía</strong> sin autorización.</p>
    <ul>
        <li><strong>OC:</strong> {{ $datos['numero_oc'] ?? '' }}</li>
        <li><strong>Empresa:</strong> {{ $datos['empresa'] ?? '' }}</li>
        <li><strong>Proveedor:</strong> {{ $datos['proveedor'] ?? '' }}</li>
        <li><strong>Días en la ubicación:</strong> {{ $datos['dias'] ?? '' }}</li>
        @if (! empty($datos['referente']))
            <li><strong>Referente:</strong> {{ $datos['referente'] }}</li>
        @endif
    </ul>
    @if (! empty($datos['url']))
        <p><a href="{{ $datos['url'] }}">Ver bandeja de estados</a></p>
    @endif
</body>
</html>
