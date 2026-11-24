@php
    use App\Support\Listado\ListadoGrillaConfigSupport as LwGrilla;
    use App\Support\Ticket\AdministracionTicketListadoColumnas;
    use App\Support\Ticket\TicketEstadisticaSupport;

    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $cfg = ($layoutPorKey ?? [])[$key] ?? null;
    $alinea = is_array($cfg) ? ($cfg['alinea'] ?? 'izquierda') : ($key === 'tiempo_insumido' ? 'derecha' : 'izquierda');
    $cls = trim(LwGrilla::claseAlineacion($alinea).' lw-col');
    $valor = AdministracionTicketListadoColumnas::valorCelda($data, $key);
    $title = trim($valor);
@endphp
@switch ($key)
    @case ('id')
        <td class="{{ $cls }}" title="{{ $title }}">
            @if ($puedeVerTicket && (int) ($data->id ?? 0) > 0)
                <a href="{{ route('edita_administracion_ticket', array_merge(['id' => $data->id], $queryConsulta)) }}"
                   target="_blank" rel="noopener" class="text-primary">
                    {{ $data->id }}
                </a>
            @else
                {{ $data->id }}
            @endif
        </td>
        @break
    @case ('fecha')
        <td class="{{ $cls }}" title="{{ $title }}">
            <span class="lw-celda-texto">{{ $valor }}</span>
        </td>
        @break
    @case ('fecha_resolucion')
        <td class="{{ $cls }}" title="{{ $title }}">
            <span class="lw-celda-texto">{{ TicketEstadisticaSupport::formatearResolucionDisplay($data->fecha_resolucion ?? null, $data->hora_resolucion ?? null) }}</span>
        </td>
        @break
    @case ('tiempo_insumido')
        <td class="{{ $cls }}" title="{{ $title }}">
            <span class="lw-celda-texto">{{ TicketEstadisticaSupport::formatearTiempoInsumido($data->tiempo_insumido_total ?? null) }}</span>
        </td>
        @break
    @default
        <td class="{{ $cls }}" title="{{ $title }}">
            <span class="lw-celda-texto">{{ $valor }}</span>
        </td>
@endswitch
