@php
    $filas = collect($resultado['filas'] ?? [])->filter(fn ($f) => ($f['kind'] ?? 'rubro') === 'rubro');
    $columnas = $resultado['columnas'] ?? [];
    $avisos = array_values(array_filter((array) ($resultado['advertencias'] ?? [])));
    $principales = $filas->filter(fn ($f) => (int) ($f['nivel'] ?? 1) <= 2)->take(12);
    if ($principales->isEmpty()) {
        $principales = $filas->take(12);
    }
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $reporte->nombre }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 4px 0;">{{ $reporte->nombre }}</h2>
<p style="margin:0 0 16px 0; color:#555;">
    {{ $periodoTexto }}
    · Envío automático <strong>{{ $suscripcion->nombre }}</strong>
    · Generado el {{ now()->format('d/m/Y H:i') }}
</p>

@if (trim((string) ($suscripcion->mensaje ?? '')) !== '')
    <p style="padding:10px; background:#f4f6f7; border-left:4px solid #85C1E9; margin:0 0 16px 0;">
        {{ $suscripcion->mensaje }}
    </p>
@endif

@if ($publicacion)
    <p style="padding:10px; background:#e8f6ef; border:1px solid #1E8449; margin:0 0 16px 0;">
        Este resultado quedó <strong>publicado</strong> como «{{ $publicacion->nombre }}»: se puede volver a imprimir
        idéntico desde el ERP, aunque después cambien la definición o los asientos.
    </p>
@endif

@if ($avisos !== [])
    <div style="padding:10px; background:#fdf6e3; border:1px solid #B7950B; margin:0 0 16px 0;">
        <strong>Avisos de la corrida</strong>
        <ul style="margin:6px 0 0 18px; padding:0;">
            @foreach ($avisos as $aviso)
                <li>{{ $aviso }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($principales->isNotEmpty())
    <h3 style="margin:18px 0 6px 0;">Resumen</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th align="left">Línea</th>
            @foreach ($columnas as $columna)
                <th align="right">{{ $columna['label'] ?? '' }}</th>
            @endforeach
        </tr>
        @foreach ($principales as $fila)
            <tr>
                <td style="padding-left:{{ 6 + (max(1, (int) ($fila['nivel'] ?? 1)) - 1) * 14 }}px;">
                    {{ $fila['nombre'] ?? '' }}
                </td>
                @foreach ($columnas as $columna)
                    @php $valor = $fila['saldos'][$columna['key'] ?? ''] ?? null; @endphp
                    <td align="right">
                        {{ $valor === null ? '' : number_format((float) $valor, 2, ',', '.') }}
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
    <p style="margin:6px 0 0 0; color:#777; font-size:12px;">
        Resumen de las primeras líneas; el detalle completo va en el archivo adjunto.
    </p>
@endif

@if (!empty($resultado['notas']))
    <h3 style="margin:18px 0 6px 0;">Notas</h3>
    <ol style="margin:0 0 0 18px; padding:0; font-size:12px; color:#444;">
        @foreach ($resultado['notas'] as $nota)
            <li value="{{ (int) $nota['marca'] }}" style="margin-bottom:4px;">
                @if (!empty($nota['codigo_linea']))<strong>{{ $nota['codigo_linea'] }}</strong> — @endif{{ $nota['texto'] }}
            </li>
        @endforeach
    </ol>
@endif

@if ($adjuntos !== [])
    <h3 style="margin:18px 0 6px 0;">Adjuntos</h3>
    <ul style="margin:0 0 0 18px; padding:0;">
        @foreach ($adjuntos as $adjunto)
            <li>{{ $adjunto['nombre'] }}</li>
        @endforeach
    </ul>
@endif

<p style="margin:20px 0 0 0; color:#777; font-size:12px;">
    Mail automático de {{ config('app.name', 'anitaERP') }}. Para dejar de recibirlo o cambiar el día,
    editá el envío «{{ $suscripcion->nombre }}» en la solapa Distribución del informe.
</p>
</body>
</html>
