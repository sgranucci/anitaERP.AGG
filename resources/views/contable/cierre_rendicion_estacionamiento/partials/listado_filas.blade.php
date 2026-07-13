@php
    use App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport;
    use App\Support\Contable\CierreRendicionEstacionamientoMediosCobroSupport;

    $vistaPorTurno = ! empty($vistaPorTurno);
    $columnasMedios = $columnasMedios ?? [];
    $colspan = (int) ($colspan ?? 1);
@endphp
@if ($vistaPorTurno)
    @forelse ($rendiciones ?? [] as $row)
    @php
        $pv = $row->puntoventaCae;
        $pvLabel = $pv ? trim(($pv->codigo ?? '').' — '.($pv->nombre ?? '')) : '—';
        $fechaJornada = $row->turnoOperativo?->jornada?->fecha_jornada?->format('d/m/Y')
            ?? $row->jornada?->fecha_jornada?->format('d/m/Y')
            ?? '—';
        $nc = (float) ($row->totalnotacredito ?? 0);
        $neta = (float) ($row->totalfactura ?? 0);
        $bruta = round($neta + $nc, 2);
        $mediosFila = CierreRendicionEstacionamientoMediosCobroSupport::agregarDesdeRendiciones([$row]);
    @endphp
    <tr>
        <td>{{ $row->id }}</td>
        <td>{{ $row->codigo }}</td>
        <td>{{ $fechaJornada }}</td>
        <td>{{ $row->empresa?->nombre }}</td>
        <td>{{ $pvLabel }}</td>
        <td>{{ CierreRendicionEstacionamientoGrupoSupport::etiquetaTurno($row) }}</td>
        <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
        <td>
            @if ($row->tieneCierreContable())
                {{ $row->esCierreContableLegacy() ? 'Cerrada (hist.)' : 'Cerrada' }}
            @else
                Pendiente
            @endif
        </td>
        <td>{{ $row->asiento?->numeroasiento ?? '—' }}</td>
            <td class="num" style="text-align:right;">{{ number_format($neta, 2, ',', '.') }}</td>
        <td class="num" style="text-align:right;">{{ number_format($nc, 2, ',', '.') }}</td>
        <td class="num" style="text-align:right;">{{ number_format($bruta, 2, ',', '.') }}</td>
        <td class="num" style="text-align:right;">{{ number_format((float) $row->totalinvitacion, 2, ',', '.') }}</td>
        @foreach ($columnasMedios as $medioCol)
            @php
                $montoMedio = CierreRendicionEstacionamientoMediosCobroSupport::montoDe(
                    $mediosFila,
                    (int) ($medioCol['cuentacaja_id'] ?? 0),
                );
            @endphp
            <td class="num" style="text-align:right;">{{ $montoMedio > 0.009 ? number_format($montoMedio, 2, ',', '.') : '—' }}</td>
        @endforeach
        <td class="num" style="text-align:right;">{{ number_format((float) $row->totalcobrado, 2, ',', '.') }}</td>
    </tr>
    @empty
    <tr>
        <td colspan="{{ $colspan }}">Sin registros</td>
    </tr>
    @endforelse
@else
    @forelse ($grupos ?? [] as $grupo)
    @php
        $estado = $grupo['estado_grupo'] ?? '';
        $estadoTxt = match ($estado) {
            CierreRendicionEstacionamientoGrupoSupport::ESTADO_CERRADA => 'Cerrado',
            CierreRendicionEstacionamientoGrupoSupport::ESTADO_LEGACY => 'Histórico',
            CierreRendicionEstacionamientoGrupoSupport::ESTADO_PARCIAL => 'Parcial',
            default => 'Pendiente',
        };
        $mediosFila = is_array($grupo['medios_cobro'] ?? null)
            ? $grupo['medios_cobro']
            : CierreRendicionEstacionamientoMediosCobroSupport::agregarDesdeRendiciones($grupo['rendiciones'] ?? []);
    @endphp
    <tr>
        <td>{{ $grupo['fecha_dia_fmt'] ?? '—' }}</td>
        <td>{{ $grupo['empresa_nombre'] ?? '—' }}</td>
        <td>{{ $grupo['puntoventa_label'] ?? '—' }}</td>
        <td class="num" style="text-align:right;">{{ $grupo['cantidad_rendiciones'] ?? 0 }}</td>
        <td class="num" style="text-align:right;">{{ number_format((float) ($grupo['total_ventas'] ?? 0), 2, ',', '.') }}</td>
        <td class="num" style="text-align:right;">{{ number_format((float) ($grupo['total_notas_credito'] ?? 0), 2, ',', '.') }}</td>
        <td class="num" style="text-align:right;">{{ number_format((float) ($grupo['total_ventas_brutas'] ?? 0), 2, ',', '.') }}</td>
        <td class="num" style="text-align:right;">{{ number_format((float) ($grupo['total_invitaciones'] ?? 0), 2, ',', '.') }}</td>
        @foreach ($columnasMedios as $medioCol)
            @php
                $montoMedio = CierreRendicionEstacionamientoMediosCobroSupport::montoDe(
                    $mediosFila,
                    (int) ($medioCol['cuentacaja_id'] ?? 0),
                );
            @endphp
            <td class="num" style="text-align:right;">{{ $montoMedio > 0.009 ? number_format($montoMedio, 2, ',', '.') : '—' }}</td>
        @endforeach
        <td class="num" style="text-align:right;">{{ number_format((float) ($grupo['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
        <td>{{ $estadoTxt }}</td>
        <td>
            @if (($grupo['asiento_ids_distintos'] ?? 0) > 1)
                Varios asientos
            @else
                {{ $grupo['asiento_numero'] ?? '—' }}
            @endif
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="{{ $colspan }}">Sin registros</td>
    </tr>
    @endforelse
@endif
