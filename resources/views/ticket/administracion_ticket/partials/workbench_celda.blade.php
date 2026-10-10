@php
    use App\Support\Ticket\AdministracionTicketListadoColumnas;
    use App\Support\Ticket\TicketEstadisticaSupport;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
@endphp
@switch ($key)
    @case ('id')
        <td>
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
        <td>{{ date('d/m/Y', strtotime($data->fecha ?? '')) }}</td>
        @break
    @case ('fecha_resolucion')
        <td>{{ TicketEstadisticaSupport::formatearResolucionDisplay($data->fecha_resolucion ?? null, $data->hora_resolucion ?? null) }}</td>
        @break
    @case ('tiempo_insumido')
        <td class="text-right">{{ TicketEstadisticaSupport::formatearTiempoInsumido($data->tiempo_insumido_total ?? null) }}</td>
        @break
    @case ('tecnico')
        <td>{{ AdministracionTicketListadoColumnas::valorCelda($data, 'tecnico') }}</td>
        @break
    @default
        <td>{{ AdministracionTicketListadoColumnas::valorCelda($data, $key) }}</td>
@endswitch
