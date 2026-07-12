@php
    $paraPdf = $para_pdf ?? false;
    $mostrarAcciones = $mostrar_acciones ?? false;
    $puedeVerTicket = $puede_ver_ticket ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];

    $nombresTecnicos = static function ($data): string {
        $nombres = [];
        foreach ($data->ticket_tareas ?? [] as $tarea) {
            $nombre = trim((string) ($tarea->tecnicos->nombre ?? ''));
            if ($nombre !== '' && ! in_array($nombre, $nombres, true)) {
                $nombres[] = $nombre;
            }
        }
        if ($nombres === []) {
            $fallback = trim((string) ($data->nombretecnico ?? ''));
            if ($fallback !== '') {
                $nombres[] = $fallback;
            }
        }

        return implode(', ', $nombres);
    };
@endphp
<thead>
    <tr>
        <th style="@if($paraPdf) width: 4%; @endif">ID</th>
        <th style="@if($paraPdf) width: 7%; @endif">Fecha</th>
        <th style="@if($paraPdf) width: 8%; @endif">Sala</th>
        <th style="@if($paraPdf) width: 8%; @endif">Sector</th>
        <th style="@if($paraPdf) width: 10%; @endif">&Aacute;rea de destino</th>
        <th style="@if($paraPdf) width: 10%; @endif">Gener&oacute; usuario</th>
        <th style="@if($paraPdf) width: 9%; @endif">Categor&iacute;a</th>
        <th style="@if($paraPdf) width: 9%; @endif">Subcategor&iacute;a</th>
        <th style="@if($paraPdf) width: 7%; @endif">Estado</th>
        <th style="@if($paraPdf) width: 12%; @endif">T&iacute;tulo</th>
        <th style="@if($paraPdf) width: 14%; @endif">Comentario</th>
        <th style="@if($paraPdf) width: 10%; @endif">T&eacute;cnico asignado</th>
        @if ($mostrarAcciones)
            <th class="width40" data-orderable="false"></th>
        @endif
    </tr>
</thead>
<tbody>
    @foreach ($ticket as $data)
        <tr>
            <td>
                @if (! $paraPdf && $puedeVerTicket && (int) ($data->id ?? 0) > 0)
                    <a href="{{ route('edita_administracion_ticket', array_merge(['id' => $data->id], $queryConsulta)) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $data->id }}
                    </a>
                @else
                    {{ $data->id }}
                @endif
            </td>
            <td>{{ date('d/m/Y', strtotime($data->fecha ?? '')) }}</td>
            <td>{{ $data->nombresala ?? '' }}</td>
            <td>{{ $data->nombresector ?? '' }}</td>
            <td>{{ $data->nombreareadestino ?? '' }}</td>
            <td>{{ $data->nombreusuario ?? '' }}</td>
            <td>{{ $data->nombrecategoria_ticket ?? '' }}</td>
            <td>{{ $data->nombresubcategoria_ticket ?? '' }}</td>
            <td>{{ $data->estado }}</td>
            <td>{{ $data->titulo }}</td>
            <td>{{ $data->comentario }}</td>
            <td>{{ $nombresTecnicos($data) }}</td>
            @if ($mostrarAcciones)
                <td>
                    @if ($puedeVerTicket)
                        <a href="{{ route('edita_administracion_ticket', ['id' => $data->id] + ($retornoListadoQuery ?? [])) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                            <i class="fa fa-edit"></i>
                        </a>
                    @endif
                    @if (can('borrar-ticket', false))
                        <form action="{{ route('elimina_administracion_ticket', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                            @csrf @method('delete')
                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro" onclick="eliminarTicket(event)">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </form>
                    @endif
                </td>
            @endif
        </tr>
    @endforeach
</tbody>
