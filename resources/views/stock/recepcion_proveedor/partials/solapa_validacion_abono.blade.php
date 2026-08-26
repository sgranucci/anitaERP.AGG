@php
    $val = $validacionAbono ?? null;
    $politica = $politicaValidacionAbono ?? [];
    $preguntas = optional($val?->plantillas)->preguntas ?? collect();
    $respuestasPorPregunta = [];
    foreach ($val?->respuestas ?? [] as $respuesta) {
        $respuestasPorPregunta[(int) $respuesta->pregunta_id] = $respuesta;
    }
    $completa = $val?->estaCompleta() ?? false;
    $snapshot = is_array($val?->snapshot_ingresos_json) ? $val->snapshot_ingresos_json : [];
    $periodoEtiqueta = '—';
    if ($val && $val->periodo_desde && $val->periodo_hasta) {
        $periodoEtiqueta = \App\Support\Compras\ContratoPeriodoServicioSupport::etiqueta((string) $val->periodo_modalidad)
            .': '.$val->periodo_desde->format('d/m/Y').' a '.$val->periodo_hasta->format('d/m/Y');
    }
    $ingresosVivos = (int) ($ingresosValidacionVivos ?? 0);
    $ingresosSnapshot = (int) ($snapshot['conteo_tickets'] ?? $snapshot['cantidad'] ?? $val?->ingresos_informados ?? 0);
    $minimo = (int) ($snapshot['minimo'] ?? $politica['minimo_ingresos'] ?? 0);
    $exigeIngresos = (bool) ($politica['exige_ingresos'] ?? false);
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
    <div>
        <h5 class="mb-1">
            <i class="fa fa-check-square-o text-info"></i>
            Validación de abono / consulta de ingresos
        </h5>
        <p class="text-muted small mb-0">
            Última carga de respuestas del cuestionario, con el conteo de tickets del módulo de ingresos de seguridad.
        </p>
    </div>
    @if (! empty($urlValidacionAbono))
        <a href="{{ $urlValidacionAbono }}" class="btn btn-sm {{ $completa ? 'btn-outline-success' : 'btn-primary' }}">
            <i class="fa {{ $completa ? 'fa-search' : 'fa-pencil' }}"></i>
            {{ $completa ? 'Abrir validación' : 'Completar validación' }}
        </a>
    @endif
</div>

@if (! $val)
    <div class="alert alert-warning mb-0">
        Esta OC exige validación de abono, pero todavía no hay una carga de respuestas.
        @if (! empty($urlValidacionAbono))
            <a href="{{ $urlValidacionAbono }}" class="alert-link">Completar ahora</a>
        @endif
    </div>
@else
    @if ($completa)
        <div class="alert alert-success">
            Validación completa
            @if ($val->usuarios)
                — respondida por {{ $val->usuarios->nombre }}
            @endif
            @if ($val->confirmado_at)
                el {{ $val->confirmado_at->format('d/m/Y H:i') }}
            @endif
        </div>
    @else
        <div class="alert alert-warning">
            La validación está pendiente. Hay que responder el cuestionario antes de confirmar la recepción.
        </div>
    @endif

    <div class="row mb-3">
        <div class="col-md-6">
            <div class="text-muted small">Período consultado</div>
            <div>{{ $periodoEtiqueta }}</div>
        </div>
        <div class="col-md-6">
            <div class="text-muted small">Módulo de ingresos de seguridad</div>
            <div>
                @if ($completa)
                    Última carga: <strong>{{ $ingresosSnapshot }}</strong> ticket(s) finalizado(s)
                    @if ($minimo > 0)
                        <span class="text-muted">(mínimo {{ $minimo }})</span>
                    @endif
                @else
                    Aún no hay snapshot. El conteo se guarda al confirmar la validación.
                @endif
            </div>
            @if ($exigeIngresos || $completa)
                <div class="small text-muted mt-1">
                    Conteo actual en el período: <strong>{{ $ingresosVivos }}</strong> ticket(s) finalizado(s)
                    @if ($completa && $ingresosVivos !== $ingresosSnapshot)
                        <span class="badge badge-warning ml-1">Cambió desde la última carga</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <h6 class="mb-2">Respuestas</h6>
    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead class="thead-light">
                <tr>
                    <th style="width: 4rem;">N°</th>
                    <th>Pregunta</th>
                    <th style="width: 6rem;">Respuesta</th>
                    <th>Comentario</th>
                    <th style="width: 10rem;">Origen</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($preguntas as $pregunta)
                    @php
                        $respuesta = $respuestasPorPregunta[(int) $pregunta->id] ?? null;
                        $valor = strtolower((string) ($respuesta->valor ?? ''));
                        $esTickets = (bool) $pregunta->es_tickets
                            || (string) $pregunta->codigo === \App\Support\Compras\ContratoValidacionAbonoEstados::CODIGO_TICKETS;
                    @endphp
                    <tr>
                        <td>{{ $pregunta->orden }}</td>
                        <td>{{ $pregunta->enunciado }}</td>
                        <td>
                            @if ($valor === 'si')
                                <span class="badge badge-success">Sí</span>
                            @elseif ($valor === 'no')
                                <span class="badge badge-danger">No</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $respuesta->comentario ?: '—' }}</td>
                        <td>
                            @if ($esTickets)
                                <span class="badge badge-info">Ingresos seguridad</span>
                            @else
                                <span class="text-muted">Cuestionario</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">La plantilla no tiene preguntas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif
