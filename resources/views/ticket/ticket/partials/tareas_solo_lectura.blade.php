@if (! empty($data->id))
    @php
        $tareasTicket = ($data->ticket_tareas ?? collect())->sortBy('id');
        $ticketId = $data->id;
        $ticketFinalizado = ($data->estado_ticket ?? '') === 'Finalizado';
        $puedeSolicitarMasAyuda = $ticketFinalizado && $tareasTicket->isNotEmpty();
    @endphp
    <style>
        #tarea-ticket-table-solo-lectura .comentario-usuario-item {
            font-size: 14px;
            line-height: 1.45;
        }
        #tarea-ticket-table-solo-lectura .comentario-usuario-item .comentario-usuario-texto {
            font-size: 15px;
            margin-top: 0.25rem;
        }
        #tarea-ticket-table-solo-lectura .comentario-tarea-texto,
        #comentario-reabrir-ticket {
            font-size: 14px;
        }
    </style>
    <div class="col-md-12 mt-3">
        <h4>Tareas del ticket</h4>
        <p class="text-muted small mb-2">
            Seguimiento de las tareas que el área técnica cargó en su ticket. Puede enviar comentarios al técnico asignado.
        </p>

        @if ($puedeSolicitarMasAyuda)
            <div id="bloque-reabrir-ticket" class="card card-outline card-info mb-3">
                <div class="card-header py-2">
                    <strong><i class="fa fa-reply"></i> Solicitar más ayuda</strong>
                </div>
                <div class="card-body py-3">
                    <p class="mb-2">
                        Este ticket está <strong>Finalizado</strong>. Si necesita algo más, escriba su pedido:
                        el ticket volverá a <strong>Pendiente</strong> y se avisará al área técnica.
                    </p>
                    <div class="form-group mb-2">
                        <textarea id="comentario-reabrir-ticket"
                                  class="form-control comentario-tarea-texto comentario-reabrir-texto"
                                  rows="4"
                                  placeholder="Describa qué necesita..."></textarea>
                    </div>
                    <button type="button"
                            id="btn-reabrir-ticket"
                            class="btn btn-primary btn-sm">
                        <i class="fa fa-paper-plane"></i> Solicitar más ayuda
                    </button>
                </div>
            </div>
        @endif

        <div class="table-responsive">
            <table style="font-size: 12px;" class="table table-bordered table-sm" id="tarea-ticket-table-solo-lectura">
                <thead style="background-color: #85C1E9; color: #17202A;">
                    <tr>
                        <th style="width: 30%;">Tarea</th>
                        <th style="width: 12%;">Fecha carga</th>
                        <th style="width: 20%;">Técnico</th>
                        <th style="width: 14%;">Fecha finalización</th>
                        <th style="width: 8%;">Minutos</th>
                        <th style="width: 12%;">Estado</th>
                        <th style="width: 4%;"></th>
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
                            <td>{{ $tarea->tecnicos->nombre ?? '' }}</td>
                            <td>{{ $tarea->fechaTicketLegible($tarea->fechafinalizacion) }}</td>
                            <td>{{ $tarea->tiempoinsumido !== null && $tarea->tiempoinsumido !== '' ? $tarea->tiempoinsumido : '' }}</td>
                            <td>{{ $tarea->estadoVisual() }}</td>
                            <td class="text-center">
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary btn-toggle-comentario-tarea"
                                        data-toggle="collapse"
                                        data-target="#{{ $collapseId }}"
                                        title="Comentarios para el técnico"
                                        aria-expanded="true">
                                    <i class="fa fa-comment"></i>
                                    @if ($comentariosUsuario->isNotEmpty())
                                        <span class="badge badge-light">{{ $comentariosUsuario->count() }}</span>
                                    @endif
                                </button>
                            </td>
                        </tr>
                        <tr class="fila-comentarios-tarea">
                            <td colspan="7" class="p-0 border-0">
                                <div class="collapse show" id="{{ $collapseId }}">
                                    <div class="p-2 bg-light border">
                                        <div class="lista-comentarios-usuario mb-2" data-ticket-tarea-id="{{ $tarea->id }}">
                                            @forelse ($comentariosUsuario as $comentario)
                                                <div class="comentario-usuario-item border-bottom pb-1 mb-1">
                                                    <strong>{{ $comentario->usuarios->nombre ?? '' }}</strong>
                                                    <span class="text-muted"> — {{ $comentario->created_at ? $comentario->created_at->format('d/m/Y H:i') : '' }}</span>
                                                    <div class="comentario-usuario-texto">{{ $comentario->comentario }}</div>
                                                </div>
                                            @empty
                                                <p class="text-muted mb-1 sin-comentarios">Sin comentarios todavía.</p>
                                            @endforelse
                                        </div>
                                        <div class="form-group mb-1">
                                            <textarea class="form-control form-control-sm comentario-tarea-texto"
                                                      rows="3"
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
                    @empty
                        <tr>
                            <td colspan="7" class="text-muted text-center">
                                Todavía no hay tareas cargadas por el área técnica.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <input type="hidden" id="url_guarda_comentario_tarea" value="{{ url('ticket/ticket/'.$ticketId.'/tarea') }}" />
        <input type="hidden" id="url_reabrir_ticket" value="{{ url('ticket/ticket/'.$ticketId.'/reabrir') }}" />
    </div>

    @include('ticket.partials.comentario_enviando_overlay', [
        'titulo' => 'Enviando comentario y notificando al técnico…',
        'subtitulo' => 'Por favor espere. Se está guardando el comentario y enviando el correo al técnico asignado.',
    ])
@endif
