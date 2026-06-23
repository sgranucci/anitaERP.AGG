<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cumplimiento requisici&oacute;n de sala</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #222; }
        h1 { font-size: 14px; margin: 0 0 6px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #ccc; padding: 3px 4px; vertical-align: top; }
        th { background: #85C1E9; color: #17202A; font-weight: bold; }
        tr:nth-child(even) td { background: #f5f5f5; }
        .num { text-align: right; white-space: nowrap; }
        .pdf-cabecera td { border: none !important; vertical-align: top; }
        .pdf-cabecera .logo-empresa { max-width: 160px; max-height: 50px; }
        .muted { color: #555; font-size: 7px; }
        .bloque-req { margin-bottom: 12px; page-break-inside: avoid; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $cabeceras = $data['cabeceras'] ?? [];
    $filas = $data['filas'] ?? [];
    $nombreEmpresa = $cabeceras[0]['empresa'] ?? config('app.empresa');
    $logoDat = EmpresaLogoArchivo::dataUriDesdeNombre($nombreEmpresa);
    $logoUri = $logoDat['uri'] ?? null;
    $totalEntrega = 0.0;
    foreach ($filas as $f) {
        $totalEntrega += (float) ($f['entrega'] ?? 0);
    }
@endphp
<table class="pdf-cabecera">
    <tr>
        <td style="width:55%;">
            @if ($logoUri)
                <img class="logo-empresa" src="{{ $logoUri }}" alt="">
            @endif
            <div style="font-size: 11px; font-weight: bold; margin-top: 4px;">{{ $nombreEmpresa ?? '—' }}</div>
        </td>
        <td style="text-align: right;">
            <h1>Cumplimiento requisici&oacute;n de sala</h1>
            <p class="muted" style="margin: 0;">Generado el {{ $data['generado_en'] ?? date('d/m/Y H:i') }}</p>
            @if (!empty($data['usuario']))
                <p class="muted" style="margin: 0;">Usuario: {{ $data['usuario'] }}</p>
            @endif
        </td>
    </tr>
</table>

@foreach ($cabeceras as $cab)
<div class="bloque-req">
    <p style="margin: 0 0 4px 0;"><strong>Requisici&oacute;n N&ordm; {{ $cab['numerorequisicion'] ?? '—' }}</strong>
        — Fecha {{ $cab['fecha'] ?? '—' }}
        — CC: {{ $cab['centrocosto'] ?? '—' }}</p>
    <p style="margin: 0 0 6px 0;" class="muted">
        Dep&oacute;sito destino: {{ $cab['deposito_destino_codigo'] ?? '' }} {{ $cab['deposito_destino'] ?? '—' }}
        @if (!empty($cab['deposito_origen']))
            | Origen: {{ $cab['deposito_origen_codigo'] ?? '' }} {{ $cab['deposito_origen'] }}
        @endif
    </p>
</div>
@endforeach

<table>
    <thead>
        <tr>
            <th>Art&iacute;culo</th>
            <th>Descripci&oacute;n</th>
            <th class="num">Entrega</th>
            <th class="num">Pend.</th>
            <th class="num">P.U.C.</th>
            <th>Dep.</th>
            <th>UID</th>
            <th>NPU</th>
            <th>T&eacute;cnico</th>
            <th>Motivo pendiente</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
        <tr>
            <td>{{ $fila['sku'] ?? '' }}</td>
            <td>{{ $fila['descripcion'] ?? '' }}</td>
            <td class="num">{{ ($fila['entrega'] ?? 0) > 0 ? number_format((float) $fila['entrega'], 2, ',', '.') : '' }}</td>
            <td class="num">{{ ($fila['pendiente_restante'] ?? 0) > 0 ? number_format((float) $fila['pendiente_restante'], 2, ',', '.') : '' }}</td>
            <td class="num">{{ isset($fila['precio']) && (float) $fila['precio'] > 0 ? number_format((float) $fila['precio'], 2, ',', '.') : '' }}</td>
            <td>{{ $fila['deposito_origen_codigo'] ?? '' }}</td>
            <td>{{ $fila['uid'] ?? '' }}</td>
            <td>{{ $fila['npu'] ?? '' }}</td>
            <td>{{ $fila['tecnico'] ?? '' }}</td>
            <td>{{ $fila['motivo_parcial'] ?? '' }}</td>
        </tr>
        @empty
        <tr><td colspan="10" class="text-center">Sin movimientos</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2" class="total">Total entregado</td>
            <td class="num total">{{ number_format($totalEntrega, 2, ',', '.') }}</td>
            <td colspan="7"></td>
        </tr>
    </tfoot>
</table>

@if (!empty($data['leyenda']))
    <p style="margin-top: 10px;"><strong>LEYENDA:</strong></p>
    <p style="margin: 0; white-space: pre-wrap;">{{ $data['leyenda'] }}</p>
@endif

<p style="margin-top: 24px;" class="muted">Referencia impresi&oacute;n cumplimiento requisici&oacute;n de sala.</p>
</body>
</html>
