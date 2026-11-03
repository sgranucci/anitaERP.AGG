@php
    use App\Support\Listado\ListadoGrillaConfigSupport;
    use App\Support\Solicitudpago\SolicitudpagoListadoColumnas;
    $cfg = $cfg ?? null;
    $alinea = is_array($cfg) ? ($cfg['alinea'] ?? 'izquierda') : 'izquierda';
    $cls = ListadoGrillaConfigSupport::claseAlineacion($alinea);
    $valor = SolicitudpagoListadoColumnas::valorCelda($data, $key);
    $title = trim($valor);
    $retornoListadoQuery = $retornoListadoQuery ?? [];
@endphp
@if ($key === 'codigo')
    <td class="{{ $cls }} lw-col text-nowrap">
        @if (can('editar-solicitud-pago', false))
            <a href="{{ route('editar_solicitudpago', ['id' => $data->id] + $retornoListadoQuery) }}" class="text-primary font-weight-bold">{{ $data->codigo }}</a>
        @else
            {{ $data->codigo }}
        @endif
        @php
            $esHija = (int) ($data->solicitudpago_madre_id ?? 0) > 0;
            $esMadrePlan = ((int) ($data->cuotas_total_count ?? 0) > 0) || ((int) ($data->hijas_count ?? 0) > 0);
        @endphp
        @if ($esMadrePlan)
            <span class="badge badge-primary ml-1">Madre</span>
        @elseif ($esHija)
            <span class="badge badge-light border ml-1">Hija</span>
        @endif
    </td>
@elseif ($key === 'estado')
    <td class="{{ $cls }} lw-col">
        @include('solicitudpago.solicitudpago.partials.estado_badge', ['estado' => $data->estado ?? ''])
    </td>
@elseif ($key === 'madre')
    <td class="{{ $cls }} lw-col">
        @if ($data->madre)
            <a href="{{ route('editar_solicitudpago', ['id' => $data->madre->id] + $retornoListadoQuery) }}" class="text-primary">#{{ $data->madre->codigo }}</a>
        @else
            —
        @endif
    </td>
@elseif ($key === 'cuotas')
    @php
        $cuotasPend = (int) ($data->cuotas_pendientes_count ?? 0);
        $cuotasTotal = (int) ($data->cuotas_total_count ?? 0);
    @endphp
    <td class="{{ $cls }} lw-col">
        @if ($cuotasTotal > 0)
            <a href="{{ route('editar_solicitudpago', ['id' => $data->id] + $retornoListadoQuery) }}#tab-cuotas" class="text-primary">
                {{ $cuotasTotal - $cuotasPend }}/{{ $cuotasTotal }}
                <span class="text-muted small">({{ $cuotasPend }} pend.)</span>
            </a>
        @else
            {{ $cuotasPend }}
        @endif
    </td>
@elseif ($key === 'pago')
    <td class="{{ $cls }} lw-col">
        @php $pagosCol = $data->cajaMovimientosPago ?? collect(); @endphp
        @if ($pagosCol->isEmpty())
            —
        @else
            @foreach ($pagosCol->take(2) as $pagoCol)
                @if (can('listar-ingresos-egresos-caja', false) || can('editar-ingresos-egresos-caja', false))
                    <a href="{{ route('editar_ingresoegreso', ['id' => $pagoCol->id, 'origen' => 'solicitudpago']) }}" class="text-primary d-block" target="_blank" rel="noopener">
                        {{ $pagoCol->tipotransaccioncajas->abreviatura ?? 'OP' }} {{ $pagoCol->numerotransaccion }}
                    </a>
                @else
                    <span class="d-block">{{ $pagoCol->tipotransaccioncajas->abreviatura ?? 'OP' }} {{ $pagoCol->numerotransaccion }}</span>
                @endif
            @endforeach
            @if ($pagosCol->count() > 2)
                <span class="text-muted small">+{{ $pagosCol->count() - 2 }} más</span>
            @endif
        @endif
    </td>
@else
    <td class="{{ $cls }} lw-col" @if ($title !== '') title="{{ $title }}" @endif><span class="lw-celda-texto">{{ $valor !== '' ? $valor : '—' }}</span></td>
@endif
