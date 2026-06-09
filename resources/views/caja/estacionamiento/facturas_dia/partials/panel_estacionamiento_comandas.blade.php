@php
    $ticket = $ticket ?? ($meta->ticket ?? null);
    $soloTabla = ! empty($solo_tabla);
@endphp
@if ($ticket)
    @if (! $soloTabla)
        <div class="card card-outline card-info mb-3" id="estacionamiento-ticket-panel">
            <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap">
                <span>
                    <i class="fa fa-ticket-alt"></i>
                    <strong>Ticket estacionamiento</strong>
                    @if (! empty($ticket->patente))
                        <span class="badge badge-info ml-1">{{ $ticket->patente }}</span>
                    @elseif ((int) ($ticket->numero_ticket ?? 0) > 0)
                        <span class="badge badge-info ml-1">#{{ $ticket->numero_ticket }}</span>
                    @endif
                </span>
                @if ((float) ($ticket->monto_estimado ?? 0) > 0.0001)
                    <span class="small text-muted">
                        Monto estimado: {{ number_format((float) $ticket->monto_estimado, 2, ',', '.') }}
                    </span>
                @endif
            </div>
            <div class="card-body py-2 px-2">
    @else
        <p class="small text-muted mb-2">Ticket de estacionamiento asociado a esta factura.</p>
    @endif
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered mb-0 est-estacionamiento-comandas-grid">
                        <thead class="thead-light">
                            <tr>
                                <th>Nº ticket</th>
                                <th>Patente</th>
                                <th>Ingreso</th>
                                <th>Salida</th>
                                <th>Estado</th>
                                <th class="text-right est-col-monto">Monto est.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-nowrap">{{ (int) ($ticket->numero_ticket ?? 0) > 0 ? $ticket->numero_ticket : '—' }}</td>
                                <td>{{ $ticket->patente ?: '—' }}</td>
                                <td class="text-nowrap"><small>{{ $ticket->ingreso_en ? $ticket->ingreso_en->format('d/m/Y H:i') : '—' }}</small></td>
                                <td class="text-nowrap"><small>{{ $ticket->salida_en ? $ticket->salida_en->format('d/m/Y H:i') : '—' }}</small></td>
                                <td><small>{{ $ticket->estado ?? '—' }}</small></td>
                                <td class="text-right est-col-monto">
                                    {{ number_format((float) ($ticket->monto_estimado ?? 0), 2, ',', '.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
    @if (! $soloTabla)
            </div>
        </div>
    @endif
@endif
