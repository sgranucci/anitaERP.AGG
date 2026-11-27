@php
    $cargados = $informe['cargados'] ?? [];
    $errores = $informe['errores'] ?? [];
    $omitidos = $informe['omitidos'] ?? [];
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Huecos ARCA</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Comprobantes autorizados en ARCA que no estaban en el ERP</h2>
<p style="margin:0 0 16px 0;">
    El proceso diario encontró números de comprobante que ARCA ya había autorizado y el ERP no tenía
    (el caso típico es un rollback después del CAE). Los que se pudieron armar quedaron grabados
    con el CAE original, imputados al medio del punto de venta cuando el local tiene un solo medio
    (por ejemplo L-MERCADOLIBRE), para poder imprimirlos y cuadrar con ARCA.
</p>
<p style="margin:0 0 16px 0;">
    El renglón es un resumen por alícuota de IVA, no los artículos originales.
    <strong>No movió stock y no se replicó en Anita.</strong>
</p>
@foreach ($accionesRequeridas as $accion)
<p style="margin:0 0 16px 0; background:#FFF3CD; padding:10px; border:1px solid #FFECB5;">
    <strong>Acción requerida:</strong> {{ $accion }}
</p>
@endforeach

@if ($cargados !== [])
    <h3 style="margin:18px 0 6px 0;">Cargados</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th>Comprobante</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Total</th>
            <th>CAE</th>
            <th>Asociado</th>
            <th></th>
        </tr>
        @foreach ($cargados as $fila)
            <tr>
                <td>{{ $fila['codigo'] ?? '' }}</td>
                <td>{{ $fila['fecha'] ?? '' }}</td>
                <td>{{ $fila['cliente'] ?? '' }}</td>
                <td style="text-align:right;">{{ number_format((float) ($fila['total'] ?? 0), 2, ',', '.') }}</td>
                <td>{{ $fila['cae'] ?? '' }}</td>
                <td>{{ $fila['asociado'] ?? '' }}</td>
                <td>
                    @if (! empty($fila['url_pdf']))
                        <a href="{{ $fila['url_pdf'] }}">Imprimir</a>
                    @endif
                    @if (! empty($fila['url_editar']))
                        · <a href="{{ $fila['url_editar'] }}">Abrir</a>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@endif

@if ($omitidos !== [])
    <h3 style="margin:18px 0 6px 0;">Autorizados, sin carga automática</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th>Punto de venta</th>
            <th>Tipo</th>
            <th>Número</th>
            <th>Motivo</th>
        </tr>
        @foreach ($omitidos as $fila)
            <tr>
                <td>{{ $fila['puntoventa'] ?? '' }}</td>
                <td>{{ $fila['tipo'] ?? '' }}</td>
                <td>{{ $fila['numero'] ?? '' }}</td>
                <td>{{ $fila['detalle'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if ($errores !== [])
    <h3 style="margin:18px 0 6px 0;">No se pudieron cargar</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th>Punto de venta</th>
            <th>Tipo</th>
            <th>Número</th>
            <th>Detalle</th>
        </tr>
        @foreach ($errores as $fila)
            <tr>
                <td>{{ $fila['puntoventa'] ?? '' }}</td>
                <td>{{ $fila['tipo'] ?? '' }}</td>
                <td>{{ $fila['numero'] ?? '' }}</td>
                <td>{{ $fila['detalle'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@endif
</body>
</html>
