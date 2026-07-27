<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Solicitud de pago Nro. {{ $data->codigo }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #1a1a1a; margin: 0; }
        h1 { font-size: 16px; margin: 0 0 2px 0; color: #0d3b66; }
        h2 {
            font-size: 11px; margin: 14px 0 6px 0; padding: 4px 6px;
            background: #85C1E9; color: #17202A; border: 1px solid #5dade2;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th, td { border: 1px solid #b0b0b0; padding: 3px 5px; vertical-align: top; }
        th { background: #85C1E9; color: #17202A; font-weight: bold; text-align: left; font-size: 8px; }
        .no-border td { border: none !important; }
        .lbl { background: #f4f6f7; font-weight: bold; width: 16%; white-space: nowrap; }
        .muted { color: #555; font-size: 8px; }
        .num { text-align: right; white-space: nowrap; }
        .cen { text-align: center; }
        .logo-empresa { max-width: 160px; max-height: 52px; }
        .estado-box {
            display: inline-block; padding: 2px 8px; border: 1px solid #333;
            font-weight: bold; font-size: 10px; letter-spacing: 0.5px;
        }
        .firma-box { margin-top: 36px; }
        .firma-box td { border: none; text-align: center; padding-top: 28px; }
        .firma-linea { border-top: 1px solid #333; width: 70%; margin: 0 auto 4px auto; }
        .total-pago { font-size: 12px; font-weight: bold; }
        .detalle { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
@php
    $monedaAbr = optional($data->monedas)->abreviatura
        ?? optional($data->monedas)->nombre
        ?? '';
    $provCodigo = optional($data->proveedores)->codigo;
    $provNombre = optional($data->proveedores)->nombre;
@endphp

<table class="no-border" style="margin-bottom: 8px;">
    <tr>
        <td style="width: 48%;">
            @if (!empty($logoEmpresaDataUri))
                <img class="logo-empresa" src="{{ $logoEmpresaDataUri }}" alt="">
            @endif
            <div style="font-size: 11px; font-weight: bold; margin-top: 4px;">
                {{ optional($data->empresas)->nombre ?? '—' }}
            </div>
        </td>
        <td style="text-align: right;">
            <h1>Solicitud de pago</h1>
            <div style="font-size: 13px; font-weight: bold;">Nro. {{ $data->codigo }}</div>
            <div class="muted" style="margin-top: 4px;">Generado {{ $generadoEn }}</div>
            <div style="margin-top: 6px;">
                <span class="estado-box">ESTADO: {{ $estadoLabel }}</span>
            </div>
        </td>
    </tr>
</table>

<div class="muted" style="margin-bottom: 8px;">
    Fecha: {{ optional($data->fecha)->format('d/m/Y') ?: '—' }}
    &nbsp;&nbsp;|&nbsp;&nbsp;
    Fecha de vencimiento: {{ optional($data->fecha_vencimiento)->format('d/m/Y') ?: '—' }}
</div>

<h2>Datos de la solicitud</h2>
<table>
    <tr>
        <td class="lbl">Moneda</td>
        <td>{{ $monedaAbr !== '' ? $monedaAbr : '—' }}</td>
        <td class="lbl">Tratamiento</td>
        <td>{{ $tratamientoLabel }}</td>
    </tr>
    <tr>
        <td class="lbl">Raz&oacute;n social</td>
        <td colspan="3">
            @if ($provCodigo || $provNombre)
                {{ $provCodigo ? str_pad((string) $provCodigo, 6, '0', STR_PAD_LEFT).' ' : '' }}{{ $provNombre }}
            @else
                —
            @endif
        </td>
    </tr>
    <tr>
        <td class="lbl">Concepto</td>
        <td colspan="3">
            @if ($data->conceptos)
                {{ $data->conceptos->codigo ?? '' }} {{ $data->conceptos->nombre ?? '' }}
            @else
                —
            @endif
        </td>
    </tr>
    <tr>
        <td class="lbl">Sector</td>
        <td>
            @if ($data->sectores)
                {{ $data->sectores->codigo ?? '' }} {{ $data->sectores->nombre ?? '' }}
            @else
                —
            @endif
        </td>
        <td class="lbl">Centro de costo</td>
        <td>
            @if ($data->centrocostos)
                {{ $data->centrocostos->codigo ?? '' }} {{ $data->centrocostos->nombre ?? '' }}
            @else
                —
            @endif
        </td>
    </tr>
    <tr>
        <td class="lbl">Beneficiario</td>
        <td colspan="3">{{ $data->beneficiario ?: '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Forma de pago</td>
        <td colspan="3">{{ optional($data->formapagosol)->nombre ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Endoso</td>
        <td colspan="3">{{ $data->endoso ?: '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Total pago</td>
        <td class="total-pago" colspan="3">
            {{ $monedaAbr }} {{ number_format((float) $data->monto, 2, ',', '.') }}
        </td>
    </tr>
    <tr>
        <td class="lbl">Fecha entrega</td>
        <td>{{ optional($data->fecha_entrega)->format('d/m/Y') ?: '—' }}</td>
        <td class="lbl">Fecha vencimiento</td>
        <td>{{ optional($data->fecha_vencimiento)->format('d/m/Y') ?: '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Detalle</td>
        <td class="detalle" colspan="3">{{ $data->detalle ?: '—' }}</td>
    </tr>
    @if ($data->observacion)
    <tr>
        <td class="lbl">Observaci&oacute;n</td>
        <td class="detalle" colspan="3">{{ $data->observacion }}</td>
    </tr>
    @endif
    @if ($data->madre)
    <tr>
        <td class="lbl">SP madre</td>
        <td colspan="3">#{{ $data->madre->codigo }}</td>
    </tr>
    @endif
</table>

<h2>Imputaci&oacute;n contable</h2>
<table>
    <thead>
        <tr>
            <th style="width: 14%;">Cuenta</th>
            <th>Descripci&oacute;n</th>
            <th style="width: 8%;" class="cen">CC</th>
            <th>Detalle del centro de costo</th>
            <th style="width: 6%;" class="cen">D/H</th>
            <th style="width: 16%;" class="num">Importe</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data->cuentas as $cta)
            @php
                $ctaCod = optional($cta->cuentacontables)->codigo;
                $ctaNom = optional($cta->cuentacontables)->nombre;
                $ccCod = optional($cta->centrocostos)->codigo;
                $ccNom = optional($cta->centrocostos)->nombre;
                $dh = strtoupper((string) ($cta->debe_haber ?? ''));
            @endphp
            <tr>
                <td>{{ $ctaCod ?: '—' }}</td>
                <td>{{ $ctaNom ?: '—' }}</td>
                <td class="cen">{{ $ccCod ?: '—' }}</td>
                <td>{{ $ccNom ?: '—' }}</td>
                <td class="cen">{{ $dh !== '' ? $dh : '—' }}</td>
                <td class="num">{{ $monedaAbr }} {{ number_format((float) $cta->monto, 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="cen muted">Sin cuentas imputadas</td>
            </tr>
        @endforelse
    </tbody>
</table>

@if ($data->cuotas->count() > 0)
    <h2>Plan de pagos</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 12%;" class="cen">Cuota</th>
                <th style="width: 20%;" class="cen">Fecha vto.</th>
                <th class="num">Monto</th>
                <th>SP hija</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data->cuotas as $cuota)
                <tr>
                    <td class="cen">{{ $cuota->nro_cuota }}</td>
                    <td class="cen">{{ optional($cuota->fecha_vencimiento)->format('d/m/Y') ?: '—' }}</td>
                    <td class="num">{{ $monedaAbr }} {{ number_format((float) $cuota->monto, 2, ',', '.') }}</td>
                    <td>
                        @if ($cuota->hijas)
                            #{{ $cuota->hijas->codigo }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($data->archivos->count() > 0)
    <h2>Archivos asociados</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;" class="cen">#</th>
                <th>Nombre</th>
                <th style="width: 28%;">Usuario</th>
                <th style="width: 18%;">Fecha</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data->archivos as $arch)
                <tr>
                    <td class="cen">{{ $arch->nro_linea }}</td>
                    <td>{{ $arch->nombre_original ?: basename((string) $arch->archivo) }}</td>
                    <td>{{ optional($arch->usuarios)->nombre ?? '—' }}</td>
                    <td>{{ optional($arch->fecha)->format('d/m/Y') }} {{ $arch->hora }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<table class="firma-box no-border">
    <tr>
        <td style="width: 50%;">
            <div class="firma-linea"></div>
            FIRMA DEL EMISOR
        </td>
        <td style="width: 50%;">
            <div class="firma-linea"></div>
            AUTORIZO
        </td>
    </tr>
</table>

@if (!empty($emitio))
    <p class="muted" style="margin-top: 10px;">
        Emiti&oacute;: {{ $emitio['id'] ?? '' }} {{ $emitio['nombre'] ?? '' }}
    </p>
@endif
</body>
</html>
