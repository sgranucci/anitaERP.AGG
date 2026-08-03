@php
    /** @var array $d */
    $fmt = static fn ($n) => $n === null || $n === '' ? '' : number_format((float) $n, 2, ',', '.');
@endphp
@if (! empty($d['multiempresa_activo']) && ($d['multiempresa_total'] ?? 1) > 1)
    <div class="sec-title" style="background:#1A5276;color:#fff;">
        Multiempresa {{ $d['multiempresa_indice'] ?? 1 }}/{{ $d['multiempresa_total'] }}
        — {{ $d['empresa_linea'] }}
    </div>
@endif
<div class="hdr">
    <div class="hdr-emp">{{ $d['empresa_linea'] }}</div>
</div>

<table class="meta">
    <tr>
        <th style="width:12%">LEGAJO</th>
        <th style="width:12%">DOCUMENTO</th>
        <th style="width:36%">APELLIDO Y NOMBRE</th>
        <th style="width:20%">CATEGORIA</th>
        <th style="width:20%">A.R.T.</th>
    </tr>
    <tr>
        <td>{{ $d['legajo'] }}</td>
        <td>{{ $d['documento'] }}</td>
        <td>{{ $d['apellido_nombre'] }}</td>
        <td>{{ $d['categoria'] }}</td>
        <td>{{ $d['art'] }}</td>
    </tr>
</table>
<table class="meta">
    <tr>
        <th style="width:22%">BANCO DEPOSITO</th>
        <th style="width:14%">PERIODO DEP.</th>
        <th style="width:14%">F.PAGO APORTES</th>
        <th style="width:28%">CONVENIO</th>
        <th style="width:22%">F. INGRESO</th>
    </tr>
    <tr>
        <td>{{ $d['banco'] }}</td>
        <td>{{ $d['periodo_dep'] }}</td>
        <td>{{ $d['fecha_pago_aportes'] }}</td>
        <td>{{ $d['convenio'] }}</td>
        <td>{{ $d['fecha_ingreso'] }}</td>
    </tr>
</table>
<table class="meta">
    <tr>
        <th style="width:18%">PERIODO LIQ.</th>
        <th style="width:14%">LUGAR PAGO</th>
        <th style="width:12%">FECHA PAGO</th>
        <th style="width:16%">C.U.I.L.</th>
        <th style="width:20%">LUGAR TRAB.</th>
        <th style="width:20%">TAREAS</th>
    </tr>
    <tr>
        <td>{{ $d['periodo_liq'] }}</td>
        <td>{{ $d['lugar_pago'] }}</td>
        <td>{{ $d['fecha_pago'] }}</td>
        <td>{{ $d['cuil'] }}</td>
        <td>{{ $d['lugar_trabajo'] }}</td>
        <td>{{ $d['tareas'] }}</td>
    </tr>
</table>
<table class="meta">
    <tr>
        <th style="width:6%">Q.</th>
        <th style="width:16%">MES / ANIO</th>
        <th style="width:16%">REMUN. BASICA</th>
        <th style="width:22%">ANTIGUEDAD</th>
        <th style="width:40%">OBRA SOCIAL</th>
    </tr>
    <tr>
        <td>{{ $d['quincena'] }}</td>
        <td>{{ $d['mes_anio'] }}</td>
        <td>{{ $fmt($d['remun_basica']) }}</td>
        <td>{{ $d['antiguedad'] }}</td>
        <td>{{ $d['obra_social'] }}</td>
    </tr>
</table>

<div class="sec-title">COSTO TOTAL EMPLEADOR</div>
<table class="conc">
    <thead>
        <tr>
            <th style="width:55%">CONCEPTO</th>
            <th class="num" style="width:12%">UNIDAD</th>
            <th class="num" style="width:15%">BASE</th>
            <th class="num" style="width:18%">MONTO</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($d['lineas_ce'] as $l)
            <tr>
                <td>{{ $l['descripcion'] }}</td>
                <td class="num">{{ $l['unidad'] }}</td>
                <td class="num">{{ $fmt($l['base']) }}</td>
                <td class="num">{{ $fmt($l['monto']) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="color:#888;">Sin contribuciones empleador en esta liquidaci&oacute;n.</td></tr>
        @endforelse
        <tr class="subtotal">
            <td colspan="3">SUBTOTAL CONTRIBUCIONES EMPLEADOR</td>
            <td class="num">{{ $fmt($d['subtotal_ce']) }}</td>
        </tr>
    </tbody>
</table>

<div class="sec-title">SUELDO BRUTO</div>
<table class="conc">
    <thead>
        <tr>
            <th style="width:55%">CONCEPTO</th>
            <th class="num" style="width:12%">UNIDAD</th>
            <th class="num" style="width:15%">BASE</th>
            <th class="num" style="width:18%">MONTO</th>
        </tr>
    </thead>
    <tbody>
        @if (count($d['lineas_rem']))
            <tr><td colspan="4" class="sec-sub">REMUNERATIVO</td></tr>
            @foreach ($d['lineas_rem'] as $l)
                <tr>
                    <td>{{ $l['descripcion'] }}</td>
                    <td class="num">{{ $l['unidad'] }}</td>
                    <td class="num">{{ $fmt($l['base']) }}</td>
                    <td class="num">{{ $fmt($l['monto']) }}</td>
                </tr>
            @endforeach
        @endif
        @if (count($d['lineas_norem']))
            <tr><td colspan="4" class="sec-sub">NO REMUNERATIVO</td></tr>
            @foreach ($d['lineas_norem'] as $l)
                <tr>
                    <td>{{ $l['descripcion'] }}</td>
                    <td class="num">{{ $l['unidad'] }}</td>
                    <td class="num">{{ $fmt($l['base']) }}</td>
                    <td class="num">{{ $fmt($l['monto']) }}</td>
                </tr>
            @endforeach
        @endif
        @if (count($d['lineas_ded']))
            <tr><td colspan="4" class="sec-sub">DESCUENTOS</td></tr>
            @foreach ($d['lineas_ded'] as $l)
                <tr>
                    <td>{{ $l['descripcion'] }}</td>
                    <td class="num">{{ $l['unidad'] }}</td>
                    <td class="num">{{ $fmt($l['base']) }}</td>
                    <td class="num">{{ $fmt($l['monto']) }}</td>
                </tr>
            @endforeach
        @endif
    </tbody>
</table>

<p style="margin:4px 0;">
    COMPOSICION SALARIAL:
    Remunerativo: {{ $fmt($d['tot_rem']) }}
    &nbsp; No Remunerativo: {{ $fmt($d['tot_norem']) }}
    &nbsp; Descuentos: {{ $fmt($d['tot_ded']) }}
</p>
<div class="neto-box">SUELDO NETO $ {{ $fmt($d['neto']) }}</div>
<p>Recibi la suma de: {{ $d['neto_letras'] }}</p>
<p>
    Depositado en: {{ $d['banco_deposito'] }}
    &nbsp; Cuenta: {{ $d['cuenta'] }}
    &nbsp; CBU: {{ $d['cbu'] }}
    @if ($d['fecha_pago']) , {{ $d['fecha_pago'] }} @endif
</p>

<table class="pie-grid">
    <tr>
        <td style="width:58%;">
            <div class="sec-title">Detalle de la composicion salarial</div>
            <table class="comp-tbl">
                <tr>
                    <td class="lbl">Total Costo Sindical</td>
                    <td class="num">{{ $fmt($d['tot_sindical']) }}</td>
                    <td class="lbl">Total costo INSSJP:</td>
                    <td class="num">{{ $fmt($d['tot_inssjp']) }}</td>
                </tr>
                <tr>
                    <td>&nbsp;&nbsp;Empleador</td>
                    <td class="num">{{ $fmt($d['rubros_emp']['sindical'] ?? 0) }}</td>
                    <td>&nbsp;&nbsp;Empleador</td>
                    <td class="num">{{ $fmt($d['rubros_emp']['inssjp'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td>&nbsp;&nbsp;Trabajador</td>
                    <td class="num">{{ $fmt($d['rubros_tra']['sindical'] ?? 0) }}</td>
                    <td>&nbsp;&nbsp;Trabajador</td>
                    <td class="num">{{ $fmt($d['rubros_tra']['inssjp'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td class="lbl">Total Seguridad Social</td>
                    <td class="num">{{ $fmt($d['tot_segsoc']) }}</td>
                    <td class="lbl">Total costo ART:</td>
                    <td class="num">{{ $fmt($d['tot_art']) }}</td>
                </tr>
                <tr>
                    <td>&nbsp;&nbsp;Empleador</td>
                    <td class="num">{{ $fmt($d['rubros_emp']['seguridad_social'] ?? 0) }}</td>
                    <td>&nbsp;&nbsp;Empleador</td>
                    <td class="num">{{ $fmt($d['tot_art']) }}</td>
                </tr>
                <tr>
                    <td>&nbsp;&nbsp;Trabajador</td>
                    <td class="num">{{ $fmt($d['rubros_tra']['seguridad_social'] ?? 0) }}</td>
                    <td></td><td></td>
                </tr>
                <tr>
                    <td class="lbl">Total Obra Social:</td>
                    <td class="num">{{ $fmt($d['tot_os']) }}</td>
                    <td class="lbl">Total Costo SCVO:</td>
                    <td class="num">{{ $fmt($d['tot_scvo']) }}</td>
                </tr>
                <tr>
                    <td>&nbsp;&nbsp;Empleador</td>
                    <td class="num">{{ $fmt($d['rubros_emp']['obra_social'] ?? 0) }}</td>
                    <td>&nbsp;&nbsp;Empleador</td>
                    <td class="num">{{ $fmt($d['tot_scvo']) }}</td>
                </tr>
                <tr>
                    <td>&nbsp;&nbsp;Trabajador</td>
                    <td class="num">{{ $fmt($d['rubros_tra']['obra_social'] ?? 0) }}</td>
                    <td></td><td></td>
                </tr>
            </table>
            <p class="nota">Nota: Seguridad social del empleador incluye SIPA, Fondo Nacional de Empleo y Asignaciones Familiares.</p>
        </td>
        <td style="width:42%; text-align:center;">
            <div class="sec-title">Costo total empleador</div>
            {!! $d['torta_svg'] !!}
            <table class="comp-tbl" style="margin:4px auto; width:90%;">
                @foreach ($d['pie_leyenda'] as $lab => $val)
                    @if ((float)$val > 0)
                        <tr>
                            <td>{{ $lab }}</td>
                            <td class="num">{{ $fmt($val) }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        </td>
    </tr>
</table>

<div class="firma">
    FIRMA DEL EMPLEADOR
    <strong>{{ $d['firma_nombre'] }}</strong>
    {{ $d['firma_cargo'] }}
</div>
