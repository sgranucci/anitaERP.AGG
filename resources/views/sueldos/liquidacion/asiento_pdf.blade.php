<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $payload['titulo'] ?? 'Asiento de liquidación' }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 14px; margin: 0 0 4px 0; }
        h2 { font-size: 11px; margin: 12px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #444; padding: 3px 4px; vertical-align: top; }
        th { background: #e8e8e8; font-weight: bold; text-align: left; }
        .cabecera td { width: 25%; }
        .cabecera .lbl { background: #f0f0f0; font-weight: bold; width: 16%; }
        .muted { color: #555; font-size: 8px; }
        .pdf-cabecera { margin-bottom: 8px; }
        .pdf-cabecera td { border: none !important; vertical-align: top; }
        .pdf-cabecera .logo-empresa {
            max-width: 180px;
            max-height: 56px;
            width: auto;
            height: auto;
            object-fit: contain;
            vertical-align: middle;
        }
        .movimientos th, .movimientos td { font-size: 9px; padding: 3px 4px; }
        .movimientos .num { text-align: right; white-space: nowrap; }
        .totales td { font-weight: bold; background: #f5f5f5; }
        .badge-preview {
            display: inline-block;
            background: #f39c12;
            color: #fff;
            padding: 2px 6px;
            font-size: 9px;
            font-weight: bold;
        }
        .alert { border: 1px solid #c0392b; background: #fdecea; padding: 4px 6px; margin: 4px 0; }
        .alert-warn { border-color: #d68910; background: #fef5e7; }
    </style>
</head>
<body>
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $nombreEmpresaLogo = $payload['empresa_nombre'] ?? null;
    $logoEmpresaDat = EmpresaLogoArchivo::dataUriDesdeNombre($nombreEmpresaLogo);
    $logoEmpresaDataUri = $logoEmpresaDat['uri'] ?? null;
    $fmt = fn ($v) => ((float) $v) > 0 ? number_format((float) $v, 2, ',', '.') : '';
@endphp
<table class="pdf-cabecera">
    <tr>
        <td style="width:55%;">
            @if ($logoEmpresaDataUri)
                <img class="logo-empresa" src="{{ $logoEmpresaDataUri }}" alt="">
            @endif
            <div style="font-size: 12px; font-weight: bold; margin-top: 4px;">{{ $nombreEmpresaLogo ?: '—' }}</div>
        </td>
        <td style="text-align: right;">
            <h1 style="margin-top: 0;">{{ $payload['titulo'] ?? 'Asiento de liquidación' }}</h1>
            @if (! empty($payload['es_preview']))
                <span class="badge-preview">PREVIEW — sin n&uacute;mero de asiento</span>
            @endif
            <p class="muted" style="margin: 4px 0 0;">Generado el {{ date('d/m/Y H:i') }}</p>
        </td>
    </tr>
</table>

<h2>Datos de la corrida</h2>
<table class="cabecera">
    <tr>
        <td class="lbl">Empresa</td>
        <td>{{ $payload['empresa_nombre'] ?? '—' }}</td>
        <td class="lbl">Corrida</td>
        <td>{{ $payload['liquidacion_numero'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Per&iacute;odo</td>
        <td>{{ $payload['periodo_label'] ?? '—' }}</td>
        <td class="lbl">Estado</td>
        <td>{{ $payload['estado_label'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Modo</td>
        <td>{{ $payload['modo_label'] ?? '—' }}</td>
        <td class="lbl">N&deg; asiento</td>
        <td>{{ $payload['numeros_asiento_label'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Fecha</td>
        <td>{{ $payload['fecha_fmt'] ?? '—' }}</td>
        <td class="lbl">Descripci&oacute;n</td>
        <td>{{ $payload['descripcion'] ?? '—' }}</td>
    </tr>
</table>

@foreach ($payload['errores'] ?? [] as $err)
    <div class="alert">{{ $err }}</div>
@endforeach
@foreach ($payload['advertencias'] ?? [] as $adv)
    <div class="alert alert-warn">{{ $adv }}</div>
@endforeach

@foreach ($payload['grupos'] ?? [] as $grupo)
    <h2>
        @if (! empty($grupo['numeroasiento']))
            Asiento N&deg; {{ $grupo['numeroasiento'] }}
        @elseif (! empty($payload['es_preview']))
            Asiento propuesto (preview)
        @else
            Asiento
        @endif
        @if (! empty($grupo['etiqueta']))
            — {{ $grupo['etiqueta'] }}
        @endif
    </h2>
    <table class="movimientos">
        <thead>
            <tr>
                <th style="width:12%;">Cuenta</th>
                <th style="width:34%;">Nombre</th>
                <th style="width:24%;">Centro de costo</th>
                <th style="width:15%;" class="num">Debe</th>
                <th style="width:15%;" class="num">Haber</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($grupo['lineas'] ?? [] as $linea)
                <tr>
                    <td>{{ $linea['cuenta_codigo'] ?? '' }}</td>
                    <td>{{ $linea['cuenta_nombre'] ?? '' }}</td>
                    <td>
                        @php
                            $cc = trim(($linea['centrocosto_codigo'] ?? '').' '.($linea['centrocosto_nombre'] ?? ''));
                        @endphp
                        {{ $cc !== '' ? $cc : '—' }}
                    </td>
                    <td class="num">{{ $fmt($linea['debe'] ?? 0) }}</td>
                    <td class="num">{{ $fmt($linea['haber'] ?? 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;">Sin renglones.</td>
                </tr>
            @endforelse
            <tr class="totales">
                <td colspan="3" style="text-align:right;">Totales</td>
                <td class="num">{{ number_format((float) ($grupo['total_debe'] ?? 0), 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) ($grupo['total_haber'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
@endforeach

@if (count($payload['grupos'] ?? []) > 1)
    <table class="movimientos">
        <tr class="totales">
            <td style="text-align:right; width:70%;">Totales generales</td>
            <td class="num" style="width:15%;">{{ number_format((float) ($payload['total_debe'] ?? 0), 2, ',', '.') }}</td>
            <td class="num" style="width:15%;">{{ number_format((float) ($payload['total_haber'] ?? 0), 2, ',', '.') }}</td>
        </tr>
    </table>
@endif
</body>
</html>
