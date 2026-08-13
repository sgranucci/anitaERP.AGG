@php
    use App\Support\Ticket\TicketEstadisticaSupport;

    $fechaRes = old(
        'fecha_resolucion',
        isset($data) ? TicketEstadisticaSupport::fechaYmd($data->fecha_resolucion ?? null) : ''
    );
    $horaRes = old(
        'hora_resolucion',
        isset($data) ? TicketEstadisticaSupport::formatearHora($data->hora_resolucion ?? null) : ''
    );
    $tiempoTotal = old('tiempo_insumido_total', null);
    if ($tiempoTotal === null && isset($data)) {
        $tiempoTotal = $data->tiempo_insumido_total;
        if ($tiempoTotal === null || $tiempoTotal === '') {
            $tiempoTotal = TicketEstadisticaSupport::tiempoInsumidoDesdeTareas($data->ticket_tareas ?? []);
        }
    }
    $tiempoTexto = TicketEstadisticaSupport::formatearTiempoInsumido($tiempoTotal);
    $estaFinalizado = old('estado_ticket', $data->estado_ticket ?? '') === TicketEstadisticaSupport::ESTADO_FINALIZADO;
@endphp
<div class="card card-outline card-info mb-3" id="panel-estadisticas-ticket">
    <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <strong>
            <i class="fa fa-clock-o"></i>
            Estadísticas de resolución
        </strong>
        @if ($estaFinalizado)
            <span class="badge badge-success">Finalizado</span>
        @endif
    </div>
    <div class="card-body py-3">
        <p class="text-muted small mb-3 mb-md-2">
            Fecha y hora se completan al pasar el ticket a <strong>Finalizado</strong>.
            El tiempo insumido es la <strong>suma de minutos de todas las tareas</strong> del ticket.
        </p>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group row mb-2">
                    <label for="fecha_resolucion" class="col-lg-5 control-label text-right pr-2">Fecha resolución</label>
                    <div class="col-lg-7">
                        <input type="date"
                               name="fecha_resolucion"
                               id="fecha_resolucion"
                               class="form-control"
                               value="{{ $fechaRes }}"
                               readonly
                               title="Se completa al finalizar el ticket">
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group row mb-2">
                    <label for="hora_resolucion" class="col-lg-5 control-label text-right pr-2">Hora resolución</label>
                    <div class="col-lg-7">
                        <input type="time"
                               name="hora_resolucion"
                               id="hora_resolucion"
                               class="form-control"
                               value="{{ $horaRes }}"
                               readonly
                               step="60"
                               title="Hora (hh:mm) al finalizar el ticket">
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group row mb-2">
                    <label for="tiempo_insumido_total" class="col-lg-5 control-label text-right pr-2">Tiempo insumido</label>
                    <div class="col-lg-7">
                        <div class="input-group">
                            <input type="text"
                                   name="tiempo_insumido_total"
                                   id="tiempo_insumido_total"
                                   class="form-control text-right font-weight-bold"
                                   value="{{ $tiempoTexto }}"
                                   readonly>
                            <div class="input-group-append">
                                <span class="input-group-text">min</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
