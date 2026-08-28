<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Constancia de p&eacute;rdida de personal Nro. {{ $data->numero }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1a1a1a; margin: 0; }
        h1 { font-size: 16px; margin: 0 0 2px 0; color: #0d3b66; letter-spacing: 0.3px; }
        h2 {
            font-size: 11px; margin: 14px 0 6px 0; padding: 4px 8px;
            background: #85C1E9; color: #17202A; border: 1px solid #5dade2;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th, td { border: 1px solid #b0b0b0; padding: 4px 6px; vertical-align: top; }
        .no-border td { border: none !important; }
        .lbl { background: #f4f6f7; font-weight: bold; width: 18%; white-space: nowrap; }
        .muted { color: #555; font-size: 8px; }
        .num { text-align: right; white-space: nowrap; }
        .logo-empresa { max-width: 170px; max-height: 56px; }
        .doc-nro { font-size: 13px; font-weight: bold; }
        .importe-box {
            margin: 10px 0 6px 0;
            border: 1.5px solid #0d3b66;
            background: #f4f9fc;
            padding: 8px 10px;
        }
        .importe-box .monto { font-size: 16px; font-weight: bold; color: #0d3b66; }
        .declaracion {
            margin: 12px 0 8px 0;
            padding: 8px 10px;
            border: 1px solid #c5c5c5;
            background: #fafafa;
            text-align: justify;
            line-height: 1.45;
        }
        .firma-box { margin-top: 28px; }
        .firma-box td { border: none; text-align: center; padding: 8px 12px; vertical-align: top; }
        .firma-linea { border-top: 1px solid #333; width: 82%; margin: 46px auto 6px auto; }
        .firma-titulo { font-weight: bold; font-size: 10px; }
        .pie { margin-top: 18px; border-top: 1px solid #ccc; padding-top: 6px; }
    </style>
</head>
<body>
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Sueldos\NumeroALetrasEs;

    $empresa = $data->empresa;
    $empleado = $data->empleado;
    $supervisor = $data->supervisor;
    $logo = EmpresaLogoArchivo::dataUriDesdeNombre($empresa->nombre ?? null);
    $importe = (float) $data->importe;
    $importeFmt = number_format($importe, 2, ',', '.');
    $importeLetras = mb_strtoupper(NumeroALetrasEs::monto($importe), 'UTF-8');

    $direccionEmpresa = trim((string) ($empresa->domicilio ?? ''));
    $localidadEmpresa = trim((string) (optional(optional($empresa)->localidad)->nombre ?? ''));
    if ($direccionEmpresa !== '' && $localidadEmpresa !== '' && stripos($direccionEmpresa, $localidadEmpresa) === false) {
        $direccionEmpresa .= ' — '.$localidadEmpresa;
    }

    $empleadoNombre = trim((string) ($empleado->nombre ?? ''));
    $empleadoLegajo = trim((string) ($empleado->legajo ?? ''));
    $empleadoDoc = trim((string) ($empleado->documento ?? ''));
    $empleadoCuil = trim((string) ($empleado->cuil ?? ''));
    $identificacionEmpleado = $empleadoDoc !== '' ? $empleadoDoc : $empleadoCuil;
@endphp

<table class="no-border" style="margin-bottom: 10px;">
    <tr>
        <td style="width: 48%;">
            @if (! empty($logo['uri']))
                <img class="logo-empresa" src="{{ $logo['uri'] }}" alt="">
            @endif
            <div style="font-size: 12px; font-weight: bold; margin-top: 4px;">
                {{ $empresa->nombre ?? '—' }}
            </div>
            @if ($direccionEmpresa !== '')
                <div class="muted">{{ $direccionEmpresa }}</div>
            @endif
            @if (! empty($empresa->nroinscripcion))
                <div class="muted">CUIT {{ $empresa->nroinscripcion }}</div>
            @endif
        </td>
        <td style="text-align: right;">
            <h1>Constancia de p&eacute;rdida de personal</h1>
            <div class="doc-nro">Nro. {{ $data->numero }}</div>
            <div class="muted" style="margin-top: 6px;">Generado {{ now()->format('d/m/Y H:i') }}</div>
            <div class="muted">Estado: {{ $data->estado_label }}</div>
        </td>
    </tr>
</table>

<h2>Empleado</h2>
<table>
    <tr>
        <td class="lbl">Apellido y nombre</td>
        <td colspan="3">{{ $empleadoNombre !== '' ? $empleadoNombre : '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Legajo</td>
        <td>{{ $empleadoLegajo !== '' ? $empleadoLegajo : '—' }}</td>
        <td class="lbl">Documento / CUIL</td>
        <td>
            @if ($empleadoDoc !== '' && $empleadoCuil !== '' && $empleadoDoc !== $empleadoCuil)
                {{ $empleadoDoc }} / {{ $empleadoCuil }}
            @elseif ($identificacionEmpleado !== '')
                {{ $identificacionEmpleado }}
            @else
                —
            @endif
        </td>
    </tr>
</table>

<h2>Datos de la p&eacute;rdida</h2>
<table>
    <tr>
        <td class="lbl">Fecha</td>
        <td>{{ optional($data->fecha)->format('d/m/Y') ?: '—' }}</td>
        <td class="lbl">Turno</td>
        <td>{{ $data->turno_label ?: '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Empresa</td>
        <td>{{ $empresa->nombre ?? '—' }}</td>
        <td class="lbl">Centro de costo</td>
        <td>
            @if ($data->centrocosto)
                {{ $data->centrocosto->codigo }} — {{ $data->centrocosto->nombre }}
            @else
                —
            @endif
        </td>
    </tr>
    <tr>
        <td class="lbl">Concepto</td>
        <td colspan="3">
            @if ($data->conceptoPerdida)
                {{ $data->conceptoPerdida->codigo }} — {{ $data->conceptoPerdida->nombre }}
            @else
                —
            @endif
        </td>
    </tr>
    <tr>
        <td class="lbl">Imputaci&oacute;n</td>
        <td colspan="3">
            @if ($data->imputacionPerdida)
                {{ $data->imputacionPerdida->codigo }} — {{ $data->imputacionPerdida->nombre }}
            @else
                —
            @endif
        </td>
    </tr>
    <tr>
        <td class="lbl">Supervisor</td>
        <td>
            @if ($supervisor)
                {{ $supervisor->legajo }} — {{ $supervisor->nombre }}
            @else
                —
            @endif
        </td>
        <td class="lbl">M&aacute;quina</td>
        <td>{{ trim((string) ($data->maquina ?? '')) !== '' ? $data->maquina : '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Leyenda</td>
        <td colspan="3">{{ trim((string) ($data->leyenda ?? '')) !== '' ? $data->leyenda : '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Registr&oacute;</td>
        <td>{{ $data->usuario->nombre ?? ($data->usuario_id ? '#'.$data->usuario_id : '—') }}</td>
        <td class="lbl">Ingreso</td>
        <td>
            {{ optional($data->fecha_ingreso)->format('d/m/Y') ?: '—' }}
            @if (! empty($data->hora_ingreso))
                {{ $data->hora_ingreso }}
            @endif
        </td>
    </tr>
</table>

<div class="importe-box">
    <div>Importe de la p&eacute;rdida</div>
    <div class="monto">$ {{ $importeFmt }}</div>
    <div style="margin-top: 4px;"><strong>Son pesos:</strong> {{ $importeLetras }}</div>
</div>

<div class="declaracion">
    Por la presente, el/la empleado/a
    <strong>{{ $empleadoNombre !== '' ? $empleadoNombre : '________________' }}</strong>
    @if ($empleadoLegajo !== '')
        (legajo {{ $empleadoLegajo }})
    @endif
    declara haber tomado conocimiento de la p&eacute;rdida de personal Nro. <strong>{{ $data->numero }}</strong>
    de fecha <strong>{{ optional($data->fecha)->format('d/m/Y') ?: '—' }}</strong>,
    por el concepto indicado y por un importe de <strong>$ {{ $importeFmt }}</strong>
    ({{ $importeLetras }}),
    y se notifica que dicho importe podr&aacute; ser imputado conforme la normativa interna vigente.
    Firma el presente en se&ntilde;al de notificaci&oacute;n y conformidad.
</div>

<table class="firma-box no-border">
    <tr>
        <td style="width: 33%;">
            <div class="firma-linea"></div>
            <div class="firma-titulo">Empleado</div>
            <div class="muted">Aclaraci&oacute;n y documento</div>
            <div class="muted">Fecha: ____ / ____ / ________</div>
        </td>
        <td style="width: 34%;">
            <div class="firma-linea"></div>
            <div class="firma-titulo">Supervisor</div>
            <div class="muted">Aclaraci&oacute;n</div>
            <div class="muted">Fecha: ____ / ____ / ________</div>
        </td>
        <td style="width: 33%;">
            <div class="firma-linea"></div>
            <div class="firma-titulo">Empresa / Tesorer&iacute;a</div>
            <div class="muted">Aclaraci&oacute;n</div>
            <div class="muted">Fecha: ____ / ____ / ________</div>
        </td>
    </tr>
</table>

<p class="muted pie">
    Documento generado por el sistema para notificaci&oacute;n y firma manuscrita.
    Conservar el original firmado junto al registro de p&eacute;rdida Nro. {{ $data->numero }}.
</p>
</body>
</html>
