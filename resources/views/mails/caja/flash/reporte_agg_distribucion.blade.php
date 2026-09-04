<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flash Report AGG</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 4px 0;">Flash Report AGG</h2>
<p style="margin:0 0 16px 0; color:#555;">
    {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}
    · Envío automático <strong>{{ $suscripcion->nombre }}</strong>
    · Generado el {{ now()->format('d/m/Y H:i') }}
</p>

@if (trim((string) ($suscripcion->mensaje ?? '')) !== '')
    <p style="padding:10px; background:#f4f6f7; border-left:4px solid #85C1E9; margin:0 0 16px 0;">
        {{ $suscripcion->mensaje }}
    </p>
@endif

@if (!empty($archivo['empresas']))
    <p style="margin:0 0 12px 0;">
        Empresas: {{ implode(', ', $archivo['empresas']) }}
        @if (!empty($archivo['dias']))
            · {{ $archivo['dias'] }} día(s) con flash
        @endif
    </p>
@endif

@if (!empty($archivo['perfil_vista']) && $archivo['perfil_vista'] === 'finanzas')
    <p style="margin:0 0 8px 0; color:#555;">Vista Finanzas — día y MTD consolidado</p>
@elseif (!empty($archivo['imagen_path']) && is_file($archivo['imagen_path']))
    <p style="margin:0 0 8px 0; color:#555;">Resumen solapa Tabla (A1:G13)</p>
@elseif (!empty($archivo['tabla_resumen']))
    <p style="margin:0 0 8px 0; color:#555;">Resumen solapa Tabla (A1:G13)</p>
@endif

@if (!empty($archivo['imagen_path']) && is_file($archivo['imagen_path']) && (($archivo['perfil_vista'] ?? '') !== 'finanzas'))
    <p style="margin:0 0 16px 0;">
        <img src="{{ $message->embed($archivo['imagen_path']) }}" alt="Tabla Flash A1:G13" style="max-width:100%;border:1px solid #cccccc;">
    </p>
@elseif (!empty($archivo['tabla_resumen']))
    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px 0;font-size:12px;">
        @foreach ($archivo['tabla_resumen'] as $fila)
            <tr>
                @foreach ($fila as $celda)
                    @php
                        $encabezado = !empty($celda['encabezado']);
                        $rojo = !empty($celda['rojo']);
                    @endphp
                    <td style="border:1px solid #cccccc;min-width:90px;{{ $encabezado ? 'background:#85C1E9;color:#17202A;font-weight:bold;' : '' }}{{ $rojo ? 'color:#C0392B;' : '' }}">
                        {{ $celda['texto'] ?? '' }}
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
@endif

<p style="margin:0 0 12px 0;">
    Adjunto: <strong>{{ $archivo['nombre'] ?? 'Flash Report AGG.xlsx' }}</strong>
</p>

<p style="margin:20px 0 0 0; color:#777; font-size:12px;">
    Mail automático de {{ config('app.name', 'anitaERP') }}. Para dejar de recibirlo o cambiar el día,
    editá el envío «{{ $suscripcion->nombre }}» en Caja → Flash → Flash Report AGG.
</p>
</body>
</html>
