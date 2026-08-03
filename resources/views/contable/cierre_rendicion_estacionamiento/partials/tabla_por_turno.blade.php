@php
    use App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport;
@endphp
@forelse ($coleccion as $row)
@php
    $cerrada = $row->tieneCierreContable();
    $fechaDia = CierreRendicionEstacionamientoGrupoSupport::fechaDiaDesdeRendicion($row);
    $pv = $row->puntoventaCae;
    $pvLabel = $pv ? trim(($pv->codigo ?? '').' — '.($pv->nombre ?? '')) : '—';
    $nc = (float) ($row->totalnotacredito ?? 0);
    $neta = (float) ($row->totalfactura ?? 0);
    $bruta = round($neta + $nc, 2);
@endphp
<tr class="{{ $cerrada ? 'table-success' : '' }} grupo-resumen"
    data-empresa-id="{{ (int) $row->empresa_id }}"
    data-fecha-dia="{{ $fechaDia }}"
    data-puntoventa-cae-id="{{ (int) ($row->puntoventa_cae_id ?? 0) }}">
    <td>{{ $row->fecharendicion?->format('d/m/Y') ?? \Carbon\Carbon::parse($fechaDia)->format('d/m/Y') }}</td>
    <td>{{ $row->empresa?->nombre ?? '—' }}</td>
    <td><small>{{ $pvLabel }}</small></td>
    <td>
        {{ CierreRendicionEstacionamientoGrupoSupport::etiquetaTurno($row) }}
        @if ($row->turnoOperativo?->identificador_pc)
            <br><small class="text-muted">{{ $row->turnoOperativo->identificador_pc }}</small>
        @endif
    </td>
    <td>
        @if (can('listar-rendicion-estacionamiento-caja', false))
            <a href="{{ route('editar_rendicionestacionamiento', ['id' => $row->id, 'origen' => 'modal_consulta', 'vista' => 'consulta'] + ($retornoListadoQuery ?? [])) }}"
               class="text-primary" target="_blank" rel="noopener">{{ $row->codigo }}</a>
        @else
            {{ $row->codigo }}
        @endif
        <br><small class="text-muted">ID {{ $row->id }}</small>
    </td>
    <td class="text-right text-nowrap">{{ number_format($neta, 2, ',', '.') }}</td>
    <td class="text-right text-nowrap">
        @if ($nc > 0.009)
            {{ number_format($nc, 2, ',', '.') }}
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
    <td class="text-right text-nowrap font-weight-bold">{{ number_format($bruta, 2, ',', '.') }}</td>
    <td class="text-right text-nowrap">
        @if ((float) $row->totalinvitacion > 0.009)
            {{ number_format((float) $row->totalinvitacion, 2, ',', '.') }}
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
    <td class="text-right text-nowrap">{{ number_format((float) $row->totalcobrado, 2, ',', '.') }}</td>
    <td>
        @if ($cerrada)
            @if ($row->esCierreContableLegacy())
                <span class="badge badge-secondary" title="Cerrada sin asiento porque no hubo montos a imputar">
                    {{ \App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport::ETIQUETA_ESTADO_LEGACY }}
                </span>
            @else
                <span class="badge badge-success">Cerrada</span>
            @endif
        @else
            <span class="badge badge-warning">Pendiente</span>
        @endif
    </td>
    <td>
        @if ($row->asiento_id && can('listar-asiento', false))
            <a href="{{ route('editar_asiento', ['id' => $row->asiento_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
               class="text-primary" target="_blank" rel="noopener">
                {{ $row->asiento?->numeroasiento ?? ('#'.$row->asiento_id) }}
            </a>
        @else
            —
        @endif
    </td>
    <td class="text-nowrap">
        @if (! $cerrada && can('ejecutar-cierre-rendicion-estacionamiento-contable', false))
            <button type="button" class="btn btn-success btn-sm js-cerrar-grupo"
                    title="Generar cierre contable del grupo (fecha + PV)">
                <i class="fa fa-lock"></i>
            </button>
        @endif
        @if ($cerrada && ! $row->esCierreContableLegacy() && $row->asiento_id
            && can('anular-cierre-rendicion-estacionamiento-contable', false))
            <button type="button" class="btn btn-outline-danger btn-sm js-anular-grupo"
                    title="Anular cierre contable del grupo (fecha + PV)">
                <i class="fa fa-unlock"></i>
            </button>
        @endif
        @if (can('listar-rendicion-estacionamiento-caja', false))
            <a href="{{ route('imprimir_rendicion_estacionamiento', ['id' => $row->id, 'inline' => 1]) }}"
               class="btn-accion-tabla tooltipsC" title="PDF rendici&oacute;n" target="_blank" rel="noopener">
                <i class="fa fa-file-pdf-o text-danger"></i>
            </a>
        @endif
    </td>
</tr>
@empty
<tr>
    <td colspan="13" class="text-center text-muted py-4">Sin rendiciones de turno presentadas en caja.</td>
</tr>
@endforelse
