@extends("theme.$theme.layout")
@section('titulo')
    Cola agentes IA (HITL)
@endsection

@section('contenido')
    <div class="row">
        <div class="col-lg-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Cola de agentes por evento (HITL)</h3>
                    <div class="card-tools">
                        <a href="{{ route('manual_ia') }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                            <i class="fa fa-book"></i> Manual IA
                        </a>
                        <a href="{{ route('ai_decision') }}" class="btn btn-sm btn-default">
                            <i class="fa fa-arrow-left"></i> Gobernanza IA
                        </a>
                    </div>
                </div>
                <div class="card-body"
                     data-hitl-visto-url="{{ url('configuracion/ai-agente-eventos') }}"
                     id="ai-agente-evento-hitl">
                    <form method="get" action="{{ route('ai_agente_evento') }}" class="mb-3">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3">
                                <label for="estado">Estado</label>
                                <select class="form-control" name="estado" id="estado">
                                    <option value="">Todos</option>
                                    @foreach ($estados as $valor => $etiqueta)
                                        <option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="severidad">Severidad</label>
                                <select class="form-control" name="severidad" id="severidad">
                                    <option value="">Todas</option>
                                    @foreach (['baja', 'media', 'alta'] as $sev)
                                        <option value="{{ $sev }}" @selected(($filtros['severidad'] ?? '') === $sev)>{{ $sev }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="evento">Evento</label>
                                <input type="text" class="form-control" name="evento" id="evento"
                                       value="{{ $filtros['evento'] ?? '' }}" placeholder="ej. desvio_conciliacion">
                            </div>
                            <div class="form-group col-md-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fa fa-search"></i> Filtrar
                                </button>
                            </div>
                        </div>
                    </form>

                    @include('configuracion.ai_agente_evento.partials.tabla_cola', [
                        'coleccion' => $coleccion,
                        'mostrarAcciones' => true,
                    ])

                    <div class="mt-2">
                        {{ $coleccion->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        window.AI_AGENTE_EVENTO_CSRF = @json(csrf_token());
    </script>
    <script src="{{ asset('assets/pages/scripts/configuracion/ai/agente_evento_hitl.js') }}"></script>
@endsection
