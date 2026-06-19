@if (! empty($data->id))
    @php
        $tareasTicket = ($data->ticket_tareas ?? collect())->sortBy('id');
        $ticketId = $data->id;
    @endphp
    <div class="col-md-12 mt-3">
        <h4>Tareas del ticket</h4>
        <p class="text-muted small mb-2">
            Seguimiento de las tareas que el área técnica cargó en su ticket. Puede enviar comentarios al técnico asignado.
        </p>
        <div class="table-responsive">
            <table style="font-size: 12px;" class="table table-bordered table-sm" id="tarea-ticket-table-solo-lectura">
                <thead style="background-color: #85C1E9; color: #17202A;">
                    <tr>
                        <th style="width: 22%;">Tarea</th>
                        <th style="width: 9%;">Fecha carga</th>
                        <th style="width: 9%;">Fecha program.</th>
                        <th style="width: 16%;">Técnico</th>
                        <th style="width: 7%;">Turno</th>
                        <th style="width: 11%;">Fecha finalización</th>
                        <th style="width: 6%;">Minutos</th>
                        <th style="width: 10%;">Estado</th>
                        <th style="width: 5%;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tareasTicket as $tarea)
                        @php
                            $comentariosUsuario = ($tarea->ticket_tarea_comentarios_usuario ?? collect())->sortBy('id');
                            $collapseId = 'comentarios-tarea-'.$tarea->id;
                        @endphp
                        <tr>
                            <td>{{ $tarea->tareas->nombre ?? $tarea->detalle ?? '' }}</td>
                            <td>{{ $tarea->fechaTicketLegible($tarea->fechacarga) }}</td>
                            <td>{{ $tarea->fechaTicketLegible($tarea->fechaprogramacion) }}</td>
                            <td>{{ $tarea->tecnicos->nombre ?? '' }}</td>
                            <td>{{ $tarea->turnos->nombre ?? '' }}</td>
                            <td>{{ $tarea->fechaTicketLegible($tarea->fechafinalizacion) }}</td>
                            <td>{{ $tarea->tiempoinsumido !== null && $tarea->tiempoinsumido !== '' ? $tarea->tiempoinsumido : '' }}</td>
                            <td>{{ $tarea->estadoVisual() }}</td>
                            <td class="text-center">
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary btn-toggle-comentario-tarea"
                                        data-toggle="collapse"
                                        data-target="#{{ $collapseId }}"
                                        title="Comentarios para el técnico">
                                    <i class="fa fa-comment"></i>
                                    @if ($comentariosUsuario->isNotEmpty())
                                        <span class="badge badge-light">{{ $comentariosUsuario->count() }}</span>
                                    @endif
                                </button>
                            </td>
                        </tr>
                        <tr class="fila-comentarios-tarea">
                            <td colspan="9" class="p-0 border-0">
                                <div class="collapse" id="{{ $collapseId }}">
                                    <div class="p-2 bg-light border">
                                        <div class="lista-comentarios-usuario mb-2" data-ticket-tarea-id="{{ $tarea->id }}">
                                            @forelse ($comentariosUsuario as $comentario)
                                                <div class="comentario-usuario-item small border-bottom pb-1 mb-1">
                                                    <strong>{{ $comentario->usuarios->nombre ?? '' }}</strong>
                                                    <span class="text-muted"> — {{ $comentario->created_at ? $comentario->created_at->format('d/m/Y H:i') : '' }}</span>
                                                    <div class="mt-1">{{ $comentario->comentario }}</div>
                                                </div>
                                            @empty
                                                <p class="text-muted small mb-1 sin-comentarios">Sin comentarios todavía.</p>
                                            @endforelse
                                        </div>
                                        <div class="form-group mb-1">
                                            <textarea class="form-control form-control-sm comentario-tarea-texto"
                                                      rows="2"
                                                      maxlength="2000"
                                                      placeholder="Escriba un comentario para el técnico asignado..."></textarea>
                                        </div>
                                        <button type="button"
                                                class="btn btn-primary btn-sm btn-enviar-comentario-tarea"
                                                data-ticket-id="{{ $ticketId }}"
                                                data-ticket-tarea-id="{{ $tarea->id }}">
                                            <i class="fa fa-paper-plane"></i> Enviar comentario
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @if (($tarea->ticket_tarea_novedades ?? collect())->isNotEmpty())
                            <tr>
                                <td colspan="9" class="p-2 bg-white">
                                    <button type="button"
                                            class="btn btn-link btn-sm p-0 text-left"
                                            data-toggle="collapse"
                                            data-target="#novedades-tarea-{{ $tarea->id }}">
                                        <i class="fa fa-chevron-down"></i> Novedades del técnico ({{ $tarea->ticket_tarea_novedades->count() }})
                                    </button>
                                    <div class="collapse mt-1" id="novedades-tarea-{{ $tarea->id }}">
                                        <table class="table table-sm table-bordered mb-0" style="font-size: 11px;">
                                            <thead>
                                                <tr>
                                                    <th>Desde</th>
                                                    <th>Hasta</th>
                                                    <th>Comentario</th>
                                                    <th>Estado</th>
                                                    <th>Usuario</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($tarea->ticket_tarea_novedades->sortBy('id') as $novedad)
                                                    <tr>
                                                        <td>{{ $tarea->fechaTicketLegible($novedad->desdefecha) }}</td>
                                                        <td>{{ $tarea->fechaTicketLegible($novedad->hastafecha) }}</td>
                                                        <td>{{ $novedad->comentario }}</td>
                                                        <td>{{ $novedad->estado }}</td>
                                                        <td>{{ $novedad->usuarios->nombre ?? '' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="9" class="text-muted text-center">
                                Todavía no hay tareas cargadas por el área técnica.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <input type="hidden" id="url_guarda_comentario_tarea" value="{{ url('ticket/ticket/'.$ticketId.'/tarea') }}" />
    </div>

    @include('ticket.partials.comentario_enviando_overlay', [
        'titulo' => 'Enviando comentario y notificando al técnico…',
        'subtitulo' => 'Por favor espere. Se está guardando el comentario y enviando el correo al técnico asignado.',
    ])
@endif
