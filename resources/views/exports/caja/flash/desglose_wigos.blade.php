@php
    /** @var array<string, mixed> $desglose */
    $labelsComponente = $labelsComponente ?? [];
    $labelsTotal = $labelsTotal ?? [];
    $formulas = $desglose['formulas'] ?? [];
    $componentes = $desglose['componentes_aplicados'] ?? [];
    $totales = $desglose['totales_gaming'] ?? [];
    $verificacion = $desglose['verificacion'] ?? [];
    $salas = $desglose['salas'] ?? [];
@endphp
<table>
    <tr>
        <td colspan="12"><strong>Desglose Wigos — armado de totales flash</strong></td>
    </tr>
    <tr>
        <td colspan="12">Empresa: {{ $empresaNombre !== '' ? $empresaNombre : ('ID '.$desglose['empresa_id']) }} · Fecha: {{ $desglose['fecha'] ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="12">
            Generado {{ date('d/m/Y H:i') }}
            @if (! empty($formulas['nota']))
                — {{ $formulas['nota'] }}
            @endif
        </td>
    </tr>
    <tr><td colspan="12"></td></tr>

    <tr>
        <td colspan="12"><strong>Fórmulas</strong></td>
    </tr>
    <tr>
        <td>Campo</td>
        <td colspan="11">Fórmula</td>
    </tr>
    @foreach ($formulas as $campo => $formula)
        @if ($campo === 'nota')
            @continue
        @endif
        <tr>
            <td>{{ $campo }}</td>
            <td colspan="11">{{ $formula }}</td>
        </tr>
    @endforeach
    <tr><td colspan="12"></td></tr>

    <tr>
        <td colspan="12"><strong>Origen de componentes Wigos (cómo se obtienen)</strong></td>
    </tr>
    <tr>
        <td>Concepto</td>
        <td>Base</td>
        <td>Valor</td>
        <td>SP</td>
        <td colspan="2">Parámetros</td>
        <td colspan="2">Filtro</td>
        <td colspan="2">Campo monto</td>
        <td colspan="2">Nota</td>
    </tr>
    @foreach (($desglose['origen_componentes'] ?? []) as $origen)
        <tr>
            <td>{{ $origen['etiqueta'] ?? ($origen['clave'] ?? '') }}</td>
            <td>{{ $origen['base'] ?? '' }}</td>
            <td>{{ $origen['valor'] ?? 0 }}</td>
            <td>{{ $origen['sp'] ?? '' }}</td>
            <td colspan="2">{{ $origen['params'] ?? '' }}</td>
            <td colspan="2">{{ $origen['filtro'] ?? '' }}</td>
            <td colspan="2">{{ $origen['campo_monto'] ?? '' }}</td>
            <td colspan="2">{{ $origen['nota'] ?? '' }}</td>
        </tr>
    @endforeach
    <tr><td colspan="12"></td></tr>

    <tr>
        <td colspan="12"><strong>Componentes Wigos aplicados (suma salas / turnos)</strong></td>
    </tr>
    <tr>
        <td>Concepto</td>
        <td>Monto</td>
    </tr>
    @foreach ($componentes as $clave => $monto)
        <tr>
            <td>{{ $labelsComponente[$clave] ?? $clave }}</td>
            <td>{{ $monto }}</td>
        </tr>
    @endforeach
    <tr><td colspan="12"></td></tr>

    <tr>
        <td colspan="12"><strong>Totales gaming flash</strong></td>
    </tr>
    <tr>
        <td>Concepto</td>
        <td>Monto</td>
    </tr>
    @foreach ($totales as $clave => $monto)
        <tr>
            <td>{{ $labelsTotal[$clave] ?? $clave }}</td>
            <td>{{ $monto }}</td>
        </tr>
    @endforeach
    <tr><td colspan="12"></td></tr>

    <tr>
        <td colspan="12"><strong>Verificación de fórmulas</strong></td>
    </tr>
    <tr>
        <td>Campo</td>
        <td>Fórmula</td>
        <td>Suma partes</td>
        <td>Total flash</td>
        <td>OK</td>
    </tr>
    @foreach ($verificacion as $campo => $item)
        @php
            $suma = (float) ($item['suma_partes'] ?? 0);
            $total = (float) ($item['total_flash'] ?? 0);
            $ok = abs($suma - $total) < 0.005;
        @endphp
        <tr>
            <td>{{ $labelsTotal[$campo] ?? $campo }}</td>
            <td>{{ $item['formula'] ?? '' }}</td>
            <td>{{ $suma }}</td>
            <td>{{ $total }}</td>
            <td>{{ $ok ? 'OK' : 'Dif.' }}</td>
        </tr>
        @foreach (($item['partes'] ?? []) as $parte => $valor)
            <tr>
                <td>  · {{ $labelsComponente[$parte] ?? $parte }}</td>
                <td></td>
                <td>{{ $valor }}</td>
                <td></td>
                <td></td>
            </tr>
        @endforeach
    @endforeach
    <tr><td colspan="12"></td></tr>

    @foreach ($salas as $sala)
        <tr>
            <td colspan="12"><strong>Sala {{ $sala['sala'] ?? '' }} — slots: {{ $sala['cant_slots'] ?? 0 }} / ruletas: {{ $sala['cant_rul'] ?? 0 }}</strong></td>
        </tr>
        <tr>
            <td colspan="12">Totales sala</td>
        </tr>
        <tr>
            <td>Concepto</td>
            <td>Monto</td>
        </tr>
        @foreach (($sala['totales_sala'] ?? []) as $clave => $monto)
            <tr>
                <td>{{ $labelsTotal[$clave] ?? $clave }}</td>
                <td>{{ $monto }}</td>
            </tr>
        @endforeach
        <tr><td colspan="12"></td></tr>

        <tr>
            <td colspan="12">Por turno (valores aplicados a la fórmula)</td>
        </tr>
        <tr>
            <td>Turno</td>
            <td>Bill slots</td>
            <td>Ventas slots</td>
            <td>Ventas caja</td>
            <td>Neto QR</td>
            <td>Pagos man.</td>
            <td>Δ slot_d</td>
            <td>Δ slot_r</td>
            <td>Bill rul</td>
            <td>Ventas rul</td>
            <td>Δ rul_d</td>
            <td>Δ rul_r</td>
        </tr>
        @foreach (($sala['turnos'] ?? []) as $t)
            <tr>
                <td>{{ ($t['turno'] ?? '') }}{{ !empty($t['aplica_bill_tickets_qr']) ? ' (bill/tickets/QR)' : ' (solo man./tito)' }}</td>
                <td>{{ $t['bill_slots'] ?? 0 }}</td>
                <td>{{ $t['ventas_slots'] ?? 0 }}</td>
                <td>{{ $t['ventas_caja'] ?? 0 }}</td>
                <td>{{ $t['monto_neto_qr'] ?? 0 }}</td>
                <td>{{ $t['pagos_manuales'] ?? 0 }}</td>
                <td>{{ $t['delta_slot_d'] ?? 0 }}</td>
                <td>{{ $t['delta_slot_r'] ?? 0 }}</td>
                <td>{{ $t['bill_rul'] ?? 0 }}</td>
                <td>{{ $t['ventas_ruletas'] ?? 0 }}</td>
                <td>{{ $t['delta_rul_d'] ?? 0 }}</td>
                <td>{{ $t['delta_rul_r'] ?? 0 }}</td>
            </tr>
        @endforeach
        <tr><td colspan="12"></td></tr>

        <tr>
            <td colspan="12">Raw Wigos por turno (respuesta SP)</td>
        </tr>
        <tr>
            <td>Clave</td>
            <td>M</td>
            <td>T</td>
            <td>N</td>
        </tr>
        @php
            $clavesRaw = [
                'bill_slots', 'bill_rul', 'bill_poker', 'ventas_caja', 'ventas_slots', 'ventas_ruletas',
                'pagos_caja', 'pagos_slots', 'pagos_ruletas', 'monto_qr', 'monto_neto_qr', 'impuesto_qr',
                'pagos_manuales', 'tito_slots', 'tito_rul', 'tito_poker',
                'coin_in_slots', 'coin_in_rul', 'coin_in_poker', 'win_slots', 'win_rul',
                'units_slots', 'units_rul', 'units_poker',
            ];
            $porTurno = [];
            foreach (($sala['turnos'] ?? []) as $t) {
                $porTurno[$t['turno'] ?? ''] = $t['raw_wigos'] ?? [];
            }
        @endphp
        @foreach ($clavesRaw as $clave)
            <tr>
                <td>{{ $labelsComponente[$clave] ?? $clave }}</td>
                <td>{{ $porTurno['M'][$clave] ?? 0 }}</td>
                <td>{{ $porTurno['T'][$clave] ?? 0 }}</td>
                <td>{{ $porTurno['N'][$clave] ?? 0 }}</td>
            </tr>
        @endforeach
        <tr><td colspan="12"></td></tr>
    @endforeach
</table>
