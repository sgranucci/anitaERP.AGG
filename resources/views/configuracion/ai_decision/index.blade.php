@extends("theme.$theme.layout")
@section('titulo')
    Gobernanza IA
@endsection

@section('contenido')
    @php
        $filtrosQuery = $filtrosQuery ?? [];
    @endphp
    <div class="row">
        <div class="col-lg-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Gobernanza IA — decisiones</h3>
                    <div class="card-tools">
                        <a href="{{ route('manual_ia') }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" title="Manual de Plataforma IA">
                            <i class="fa fa-book"></i> Manual IA
                        </a>
                        @if ($consultado)
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_ai_decision',
                                'queryparams' => $filtrosQuery,
                            ])
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @include('configuracion.ai_decision.partials.salud_operacion', [
                        'salud' => $salud ?? null,
                        'eventosPendientes' => $eventosPendientes ?? [],
                    ])

                    <form method="get" action="{{ route('ai_decision') }}" class="mb-3">
                        <input type="hidden" name="consultar" value="1">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-2">
                                <label for="fecha_desde">Desde</label>
                                <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                                       value="{{ $filtros['fecha_desde'] ?? '' }}">
                            </div>
                            <div class="form-group col-md-2">
                                <label for="fecha_hasta">Hasta</label>
                                <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                                       value="{{ $filtros['fecha_hasta'] ?? '' }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="skill">Skill</label>
                                <select class="form-control" id="skill" name="skill">
                                    <option value="">Todas</option>
                                    @foreach ($skills as $valor => $etiqueta)
                                        <option value="{{ $valor }}" @selected(($filtros['skill'] ?? '') === $valor)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="accion">Acción</label>
                                <select class="form-control" id="accion" name="accion">
                                    <option value="">Todas</option>
                                    @foreach ($acciones as $valor => $etiqueta)
                                        <option value="{{ $valor }}" @selected(($filtros['accion'] ?? '') === $valor)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fa fa-search"></i> Consultar
                                </button>
                            </div>
                        </div>
                    </form>

                    @if (! $consultado)
                        <div class="alert alert-info mb-0">
                            Elegí un período (por defecto últimos 30 días al consultar) y pulsá <strong>Consultar</strong>
                            para ver KPIs de aceptación y el detalle de sugerencias de IA.
                        </div>
                    @else
                        @include('configuracion.ai_decision.partials.kpis', ['kpis' => $kpis])
                        @include('configuracion.ai_decision.partials.tabla_datos', [
                            'coleccion' => $coleccion,
                            'filtrosQuery' => $filtrosQuery,
                        ])
                    @endif
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
