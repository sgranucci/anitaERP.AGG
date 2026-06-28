@php
    $d = $datos;
    $lineas = $d['lineas_medios'] ?? [];
    $resumen = $d['resumen_rendicion'] ?? [];
@endphp

<table class="cabecera-doc">
    <tr>
        <td style="width: 35%;">
            @if (!empty($d['logo']['uri']))
                <img src="{{ $d['logo']['uri'] }}" alt="Logo" class="logo">
            @endif
        </td>
        <td style="width: 65%; text-align: right;">
            <h1>{{ $d['titulo'] }}</h1>
            <div class="subtitulo">{{ $d['subtitulo'] }}</div>
            <div class="muted">PDF generado: {{ $d['fecha_emision_comprobante'] }}</div>
        </td>
    </tr>
</table>

<table class="bloque-fechas">
    <thead>
        <tr>
            <th style="width:50%;">
                Fecha de jornada
                <span class="fecha-leyenda">Turno contable &middot; imputaci&oacute;n en Anita</span>
            </th>
            <th style="width:50%;">
                Registro en caja
                <span class="fecha-leyenda">Momento real del ingreso en tesorer&iacute;a</span>
            </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="fecha-valor fecha-jornada-valor">{{ $d['fecha_jornada'] ?: '—' }}</td>
            <td class="fecha-valor fecha-registro-valor">{{ $d['fecha_registro_caja'] ?: '—' }}</td>
        </tr>
    </tbody>
</table>

@if (empty($d['fechas_mismo_dia']) && ! empty($d['fecha_jornada']) && ! empty($d['fecha_registro_caja']))
<p class="aviso-fechas-distintas">
    La rendici&oacute;n se registr&oacute; en caja en una fecha distinta a la jornada contable.
</p>
@endif

<h2>Datos de la rendici&oacute;n</h2>
<table>
    <tr>
        <td class="lbl">C&oacute;digo caja</td>
        <td>{{ $d['codigo_anita'] ?: '—' }}</td>
        <td class="lbl">Presentaci&oacute;n #</td>
        <td>{{ $d['rendicion_id'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">N&ordm; cierre Ventas</td>
        <td>#{{ (int) ($d['numero_cierre_ventas'] ?? 0) }}</td>
        <td class="lbl">Empresa</td>
        <td>{{ $d['empresa_nombre'] }}</td>
    </tr>
    <tr>
        <td class="lbl">Caja</td>
        <td>{{ $d['caja_nombre'] ?: '—' }}</td>
        <td class="lbl">Registr&oacute;</td>
        <td>{{ $d['usuario_registro'] ?: '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">M&aacute;quina vending</td>
        <td colspan="3">{{ $d['maquina_nombre'] ?: '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Punto de venta</td>
        <td colspan="3">{{ $d['pv_cae_label'] ?? '—' }}</td>
    </tr>
</table>

<h2>Totales Ventas</h2>
<table>
    <tr>
        <td class="lbl">Total ventas</td>
        <td class="num">${{ number_format((float) ($d['totalfactura'] ?? 0), 2, ',', '.') }}</td>
        <td class="lbl">Total cobrado</td>
        <td class="num">${{ number_format((float) ($d['totalcobrado'] ?? 0), 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td class="lbl">Sobrante / faltante</td>
        <td class="num" colspan="3">${{ number_format((float) ($d['sobrante_faltante'] ?? 0), 2, ',', '.') }}</td>
    </tr>
</table>

<h2>Medios rendidos en caja</h2>
<p class="muted" style="font-size:11px; margin:0 0 8px;">Importes declarados en la presentaci&oacute;n (grilla de caja).</p>
<table>
    <thead>
        <tr>
            <th>Medio de pago</th>
            <th class="num">Monto rendido</th>
            <th class="num">Cotizaci&oacute;n</th>
            <th class="num">Pesos</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lineas as $linea)
        <tr>
            <td>{{ $linea['nombre'] ?: ($linea['codigo'] ?: '—') }}</td>
            <td class="num">${{ number_format((float) $linea['monto'], 2, ',', '.') }}</td>
            <td class="num">${{ number_format((float) $linea['cotizacion'], 2, ',', '.') }}</td>
            <td class="num">${{ number_format((float) ($linea['monto_pesos'] ?? ($linea['monto'] * $linea['cotizacion'])), 2, ',', '.') }}</td>
        </tr>
        @empty
        <tr><td colspan="4" class="muted">Sin movimientos registrados.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="total-grande">
            <td class="lbl">Total grilla</td>
            <td class="num" colspan="3">${{ number_format((float) ($resumen['total_grilla'] ?? 0), 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

@if (!empty($d['observacion']))
<h2>Observaciones</h2>
<p class="bloque-obs">{{ $d['observacion'] }}</p>
@endif
