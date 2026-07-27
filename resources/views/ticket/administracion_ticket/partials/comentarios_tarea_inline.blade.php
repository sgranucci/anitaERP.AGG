@php
    $comentarios = ($tarea->ticket_tarea_comentarios_usuario ?? collect())->sortBy('id');
    $collapseId = 'comentarios-tarea-admin-'.($tarea->id ?? $loop->index);
    $ticketTareaId = $tarea->id ?? null;
@endphp
@if ($ticketTareaId)
    @once
        <style>
            .fila-comentarios-tarea-admin .comentario-item {
                font-size: 14px;
                line-height: 1.45;
            }
            .fila-comentarios-tarea-admin .comentario-item .comentario-texto {
                font-size: 15px;
                margin-top: 0.25rem;
                white-space: pre-wrap;
            }
            .fila-comentarios-tarea-admin .comentario-tarea-texto {
                font-size: 14px;
            }
        </style>
    @endonce
    <tr class="fila-comentarios-tarea-admin" data-ticket-tarea-id="{{ $ticketTareaId }}">
        <td colspan="9" class="p-1 bg-light">
            <button type="button"
                    class="btn btn-link btn-sm p-0 text-left btn-toggle-comentarios-tarea"
                    data-toggle="collapse"
                    data-target="#{{ $collapseId }}"
                    aria-expanded="true"
                    aria-controls="{{ $collapseId }}">
                <i class="fa fa-chevron-down small toggle-icon"></i>
                <i class="fa fa-comments text-primary"></i>
                Comentarios
                <span class="badge badge-light contador-comentarios">{{ $comentarios->count() }}</span>
            </button>
            <div class="collapse show mt-1 panel-comentarios-tarea" id="{{ $collapseId }}">
                <div class="lista-comentarios-tarea mb-2" data-ticket-tarea-id="{{ $ticketTareaId }}">
                    @forelse ($comentarios as $comentario)
                        <div class="comentario-item border rounded bg-white p-2 mb-1">
                            <div class="d-flex justify-content-between flex-wrap">
                                <strong>{{ $comentario->usuarios->nombre ?? $comentario->usuarios->usuario ?? '' }}</strong>
                                <span class="text-muted">
                                    {{ $comentario->created_at ? $comentario->created_at->format('d/m/Y H:i') : '' }}
                                </span>
                            </div>
                            <div class="comentario-texto">{{ $comentario->comentario }}</div>
                        </div>
                    @empty
                        <p class="text-muted mb-1 sin-comentarios">Sin comentarios todavía.</p>
                    @endforelse
                </div>
                <div class="form-nuevo-comentario border rounded bg-white p-2">
                    <div class="form-group mb-1">
                        <textarea class="form-control form-control-sm comentario-tarea-texto"
                                  rows="2"
                                  maxlength="2000"
                                  placeholder="Escriba un comentario para el usuario que generó el ticket..."></textarea>
                    </div>
                    <button type="button"
                            class="btn btn-primary btn-sm btn-agregar-comentario-tarea"
                            data-ticket-tarea-id="{{ $ticketTareaId }}">
                        <i class="fa fa-paper-plane"></i> Agregar comentario
                    </button>
                </div>
            </div>
        </td>
    </tr>
@endif
