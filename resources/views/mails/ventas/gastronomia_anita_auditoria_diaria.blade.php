<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría Anita jornada {{ $informe['fecha_jornada'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $fechaJornada = $informe['fecha_jornada'] ?? $informe['fecha_calendario'] ?? '—';
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
    $circuitos = [
        'gastro' => 'Gastronomía (salón)',
        'estacionamiento' => 'Estacionamiento',
    ];
@endphp

<h2 style="margin:0 0 8px 0;">Auditoría ERP ↔ Anita</h2>
<p style="margin:0 0 16px 0;">
    Jornada auditada: <strong>{{ $fechaJornada }}</strong>
    · Empresa {{ $informe['empresa_id'] ?? '—' }}
</p>

@foreach ($circuitos as $claveCircuito => $tituloCircuito)
    @php
        $bloque = $informe[$claveCircuito] ?? [];
        $post = $bloque['post']['resumen_global'] ?? [];
        $rep = $bloque['replicacion'] ?? [];
        if ((int) ($post['ventas_erp'] ?? 0) === 0 && (int) ($rep['replicadas'] ?? 0) === 0) {
            continue;
        }
    @endphp

    <h3 style="margin:18px 0 6px 0;">{{ $tituloCircuito }}</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px; margin-bottom:8px;">
        <tr style="background:#f0f0f0;">
            <th align="left">Concepto</th>
            <th align="right">Valor</th>
        </tr>
        <tr>
            <td>Ventas ERP (jornada)</td>
            <td align="right">{{ (int) ($post['ventas_erp'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Sin cabecera en Anita</td>
            <td align="right">{{ (int) ($post['conteo']['solo_erp'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Con diferencia de importe</td>
            <td align="right">{{ (int) ($post['conteo']['diferencia'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Replicadas en esta corrida</td>
            <td align="right">{{ (int) ($rep['replicadas'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Errores al replicar / lectura Anita</td>
            <td align="right">{{ count($rep['errores'] ?? []) + (int) ($post['conteo']['error'] ?? 0) }}</td>
        </tr>
    </table>

    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px; margin-bottom:12px;">
        <tr style="background:#f0f0f0;">
            <th></th>
            <th align="right">Total</th>
            <th align="right">Gravado</th>
            <th align="right">IVA</th>
            <th align="right">Exento</th>
        </tr>
        <tr>
            <td>ERP</td>
            <td align="right">{{ $fmt($post['totales_erp']['total'] ?? 0) }}</td>
            <td align="right">{{ $fmt($post['totales_erp']['gravado'] ?? 0) }}</td>
            <td align="right">{{ $fmt($post['totales_erp']['iva'] ?? 0) }}</td>
            <td align="right">{{ $fmt($post['totales_erp']['exento'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Anita (signo ERP)</td>
            <td align="right">{{ $fmt($post['totales_anita_signo_erp']['total'] ?? 0) }}</td>
            <td align="right">{{ $fmt($post['totales_anita_signo_erp']['gravado'] ?? 0) }}</td>
            <td align="right">{{ $fmt($post['totales_anita_signo_erp']['iva'] ?? 0) }}</td>
            <td align="right">{{ $fmt($post['totales_anita_signo_erp']['exento'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Delta ERP − Anita</td>
            <td align="right"><strong>{{ $fmt($post['delta_totales']['total'] ?? 0) }}</strong></td>
            <td align="right">{{ $fmt($post['delta_totales']['gravado'] ?? 0) }}</td>
            <td align="right">{{ $fmt($post['delta_totales']['iva'] ?? 0) }}</td>
            <td align="right">{{ $fmt($post['delta_totales']['exento'] ?? 0) }}</td>
        </tr>
    </table>

    @if (! empty($bloque['post']['por_puntoventa']))
        <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px; width:100%; margin-bottom:16px;">
            <tr style="background:#f0f0f0;">
                <th align="left">PV</th>
                <th align="right">Ventas</th>
                <th align="right">Sin Anita</th>
                <th align="right">Dif.</th>
                <th align="right">Tot ERP</th>
                <th align="right">Tot Anita</th>
                <th align="right">Delta</th>
            </tr>
            @foreach ($bloque['post']['por_puntoventa'] as $pv)
                @php $r = $pv['resumen'] ?? []; @endphp
                <tr>
                    <td>{{ $pv['puntoventa'] ?? '—' }}</td>
                    <td align="right">{{ (int) ($r['ventas_erp'] ?? 0) }}</td>
                    <td align="right">{{ (int) ($r['conteo']['solo_erp'] ?? 0) }}</td>
                    <td align="right">{{ (int) ($r['conteo']['diferencia'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($r['totales_erp']['total'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($r['totales_anita_signo_erp']['total'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($r['delta_totales']['total'] ?? 0) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (! empty($rep['detalle']))
        <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px; width:100%; margin-bottom:16px;">
            <tr style="background:#f0f0f0;">
                <th align="left">Estado</th>
                <th align="left">Comprobante</th>
                <th align="left">PV</th>
                <th align="right">Total</th>
                <th align="left">Obs.</th>
            </tr>
            @foreach ($rep['detalle'] as $fila)
                <tr>
                    <td>{{ $fila['estado'] ?? '' }}</td>
                    <td>{{ $fila['codigo'] ?? '' }}</td>
                    <td>{{ $fila['puntoventa'] ?? '' }}</td>
                    <td align="right">{{ $fmt($fila['total'] ?? 0) }}</td>
                    <td>{{ $fila['mensaje'] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (! empty($rep['errores']))
        <ul>
            @foreach ($rep['errores'] as $err)
                <li>{{ $err['codigo'] ?? '' }} — {{ $err['mensaje'] ?? '' }}</li>
            @endforeach
        </ul>
    @endif
@endforeach

<p style="margin-top:24px; font-size:12px; color:#666;">
    Criterio ERP: <code>venta.fechajornada</code> + emisión gastronomía o estacionamiento.<br>
    Criterio Anita: cabecera por comprobante (<code>ven_sucursal + ven_fecha_vto + ven_tipo + ven_nro + ven_letra=B</code>).<br>
    Comando manual:
    <code>php artisan gastronomia:auditoria-anita-diaria --fecha={{ $fechaJornada }}</code>
</p>
</body>
</html>
