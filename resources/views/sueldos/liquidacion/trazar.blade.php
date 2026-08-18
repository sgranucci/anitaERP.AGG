@extends("theme.$theme.layout")
@section('titulo')
    Ejecuci&oacute;n de conceptos &mdash; {{ $empleado->nombre }}
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/sueldos/liquidacion/trazar.js') }}"></script>
@endsection

@section('contenido')
@php
    $tipoBadge = [
        'remunerativo' => 'success', 'no_remunerativo' => 'info', 'asignacion' => 'primary',
        'descuento' => 'danger', 'aporte' => 'danger', 'retencion' => 'danger',
        'contribucion' => 'warning', 'informativo' => 'secondary', 'neto' => 'primary',
    ];
    $tipoLabel = [
        'remunerativo' => 'Remunerativo', 'no_remunerativo' => 'No remunerativo',
        'asignacion' => 'Asignaci&oacute;n', 'descuento' => 'Descuento', 'aporte' => 'Aporte',
        'retencion' => 'Retenci&oacute;n', 'contribucion' => 'Contribuci&oacute;n',
        'informativo' => 'Informativo', 'neto' => 'Neto',
    ];
    $cantidadPasos = count($pasos);
    $cantidadErrores = collect($pasos)->filter(fn ($p) => ! empty($p['error']))->count();
    $cantidadConImporte = collect($pasos)->filter(fn ($p) => abs((float) ($p['importe'] ?? 0)) > 0.0001)->count();
    $totalHaberes = collect($pasos)
        ->filter(fn ($p) => in_array($p['tipo'] ?? '', ['remunerativo', 'no_remunerativo', 'asignacion'], true))
        ->sum(fn ($p) => (float) ($p['importe'] ?? 0));
    $totalDescuentos = collect($pasos)
        ->filter(fn ($p) => in_array($p['tipo'] ?? '', ['descuento', 'aporte', 'retencion'], true))
        ->sum(fn ($p) => (float) ($p['importe'] ?? 0));
    $tiposPresentes = collect($pasos)->pluck('tipo')->filter()->unique()->sort()->values();
    $contextoInicial = $traza['contexto_inicial'] ?? [];
    $contextoFinal = $traza['contexto_final'] ?? [];
    $fmtContexto = static function ($valor): string {
        if (is_bool($valor)) {
            return $valor ? 'verdadero' : 'falso';
        }
        if (is_numeric($valor)) {
            return number_format((float) $valor, 4, ',', '.');
        }
        if (is_array($valor)) {
            return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $valor;
    };
@endphp
<style>
    .traza-resumen .card { min-height: 76px; }
    .traza-resumen .valor { font-size: 1.15rem; font-weight: 700; line-height: 1.2; }
    .traza-toolbar { position: sticky; top: 0; z-index: 20; background: #fff; border: 1px solid #dee2e6; border-radius: .25rem; padding: .65rem; }
    .traza-paso { border-left: 4px solid #85C1E9; }
    .traza-paso.traza-error { border-left-color: #dc3545; }
    .traza-paso-header { cursor: pointer; background: #f8f9fa; }
    .traza-paso-header:hover { background: #eef6fb; }
    .traza-numero { display: inline-block; min-width: 28px; color: #6c757d; font-weight: 600; }
    .traza-formula { background: #f6f8fa; border: 1px solid #e1e4e8; border-radius: 3px; padding: 5px 7px; overflow-wrap: anywhere; }
    .rastro-panel { background: #fbfcfd; border: 1px solid #e8ecef; border-radius: 3px; padding: 7px; }
    .rastro-tree ul { list-style: none; margin: 0 0 0 1.1rem; padding: 0; border-left: 1px dashed #b7c4cc; }
    .rastro-tree > ul { border-left: none; margin-left: 0; }
    .rastro-tree li { padding: 3px 0 3px 10px; position: relative; }
    .rastro-tree code { font-size: 11px; white-space: normal; }
    .traza-contexto-table td:first-child { width: 42%; font-family: monospace; color: #1A5276; }
    .traza-contexto-table td { font-size: 11px; padding: 3px 5px; overflow-wrap: anywhere; }
</style>
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-project-diagram"></i> Ejecuci&oacute;n de conceptos
                    &mdash; Legajo {{ $empleado->legajo }} &middot; {{ $empleado->nombre }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('resultado_liquidacion_sueldos', ['id' => $liq->id]) }}"
                       class="btn btn-sm btn-outline-info" id="btn-cerrar-traza">
                        <i class="fa fa-reply-all"></i> Cerrar y volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-light border py-2 mb-3">
                    <strong>Corrida N&deg; {{ $liq->numero }}</strong>
                    &middot; Per&iacute;odo {{ $liq->periodo_mes ? sprintf('%02d/%04d', $liq->periodo_mes, $liq->periodo_anio) : $liq->periodo }}
                    &middot; {{ $liq->tipoLabel() }}
                    &middot; Set efectivo: {{ $traza['set_efectivo']['modo_label'] ?? 'Sin identificar' }}
                    <span class="text-muted">
                        ({{ $traza['set_efectivo']['cantidad_conceptos'] ?? $cantidadPasos }} conceptos,
                        {{ $traza['set_efectivo']['cantidad_excluidos'] ?? 0 }} excluidos)
                    </span>
                </div>

                <div class="row traza-resumen">
                    <div class="col-6 col-md">
                        <div class="card card-outline card-info mb-2"><div class="card-body py-2 text-center">
                            <small class="text-muted d-block">Ejecutados</small><span class="valor">{{ $cantidadPasos }}</span>
                        </div></div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="card card-outline {{ $cantidadErrores > 0 ? 'card-danger' : 'card-success' }} mb-2"><div class="card-body py-2 text-center">
                            <small class="text-muted d-block">Errores</small><span class="valor">{{ $cantidadErrores }}</span>
                        </div></div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="card card-outline card-secondary mb-2"><div class="card-body py-2 text-center">
                            <small class="text-muted d-block">Con importe</small><span class="valor">{{ $cantidadConImporte }}</span>
                        </div></div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="card card-outline card-success mb-2"><div class="card-body py-2 text-center">
                            <small class="text-muted d-block">Haberes</small><span class="valor">$ {{ number_format($totalHaberes, 2, ',', '.') }}</span>
                        </div></div>
                    </div>
                    <div class="col-12 col-md">
                        <div class="card card-outline card-danger mb-2"><div class="card-body py-2 text-center">
                            <small class="text-muted d-block">Descuentos</small><span class="valor">$ {{ number_format($totalDescuentos, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="traza-toolbar mb-3">
                    <div class="form-row align-items-center">
                        <div class="col-md-5 mb-2 mb-md-0">
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-search"></i></span></div>
                                <input type="search" id="traza-busqueda" class="form-control" placeholder="Buscar por c&oacute;digo, concepto o f&oacute;rmula&hellip;">
                            </div>
                        </div>
                        <div class="col-md-2 mb-2 mb-md-0">
                            <select id="traza-filtro-estado" class="form-control form-control-sm">
                                <option value="">Todos los estados</option>
                                <option value="ok">Sin error</option>
                                <option value="error">Con error</option>
                                <option value="importe">Con importe</option>
                                <option value="cero">Importe cero</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2 mb-md-0">
                            <select id="traza-filtro-tipo" class="form-control form-control-sm">
                                <option value="">Todos los tipos</option>
                                @foreach ($tiposPresentes as $tipo)
                                    <option value="{{ $tipo }}">{{ strip_tags($tipoLabel[$tipo] ?? $tipo) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 text-md-right">
                            <button type="button" class="btn btn-outline-info btn-sm" id="traza-expandir">
                                <i class="fa fa-expand-alt"></i> Abrir
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="traza-contraer">
                                <i class="fa fa-compress-alt"></i> Cerrar
                            </button>
                        </div>
                    </div>
                    <div class="small text-muted mt-1">
                        Mostrando <strong id="traza-visibles">{{ $cantidadPasos }}</strong> de {{ $cantidadPasos }} conceptos.
                        El orden de la lista es el orden real de ejecuci&oacute;n.
                    </div>
                </div>

                <div id="traza-listado">
                    @forelse ($pasos as $p)
                        @php
                            $pasoId = 'paso-'.$loop->iteration.'-'.$p['codigo'];
                            $tieneError = ! empty($p['error']);
                            $tieneImporte = abs((float) ($p['importe'] ?? 0)) > 0.0001;
                            $textoBusqueda = strtolower(implode(' ', [
                                $p['codigo'] ?? '', $p['descripcion'] ?? '', $p['tipo'] ?? '',
                                $p['formula'] ?? '', $p['formula_cantidad'] ?? '', $p['formula_valor'] ?? '',
                                $p['origen_label'] ?? '',
                            ]));
                        @endphp
                        <div class="card mb-2 traza-paso {{ $tieneError ? 'traza-error' : '' }}"
                             data-texto="{{ $textoBusqueda }}"
                             data-tipo="{{ $p['tipo'] ?? '' }}"
                             data-estado="{{ $tieneError ? 'error' : 'ok' }}"
                             data-importe="{{ $tieneImporte ? 'importe' : 'cero' }}">
                            <div class="card-header py-2 traza-paso-header d-flex justify-content-between align-items-center"
                                 data-toggle="collapse" data-target="#{{ $pasoId }}" aria-expanded="false">
                                <span>
                                    <span class="traza-numero">{{ $loop->iteration }}.</span>
                                    <span class="badge badge-{{ $tipoBadge[$p['tipo']] ?? 'secondary' }}">{{ $p['codigo'] }}</span>
                                    <strong>{{ $p['descripcion'] }}</strong>
                                    <span class="badge badge-light border ml-1">{{ $p['origen_label'] ?? 'Sistema' }}</span>
                                </span>
                                <span class="text-right">
                                    @if ($tieneError)
                                        <span class="badge badge-danger">Error de evaluaci&oacute;n</span>
                                    @else
                                        <small class="text-muted">{{ number_format((float) $p['cantidad'], 4, ',', '.') }} &times; {{ number_format((float) $p['valor'], 4, ',', '.') }}</small>
                                        <strong class="ml-2">$ {{ number_format((float) $p['importe'], 2, ',', '.') }}</strong>
                                    @endif
                                    <i class="fa fa-chevron-down ml-2 traza-chevron"></i>
                                </span>
                            </div>
                            <div class="collapse" id="{{ $pasoId }}">
                                <div class="card-body py-2">
                                    @if ($tieneError)
                                        <div class="alert alert-danger mb-2">
                                            <strong>No se pudo evaluar:</strong> {{ $p['error'] }}
                                        </div>
                                    @endif
                                    @if (! empty($p['aviso']))
                                        <div class="alert alert-warning py-2 mb-2">{{ $p['aviso'] }}</div>
                                    @endif

                                    <div class="row">
                                        <div class="col-lg-4">
                                            <small class="text-muted d-block">1. Cantidad</small>
                                            <div class="traza-formula mb-2">
                                                @if (! empty($p['formula_cantidad']))
                                                    <code>{{ $p['formula_cantidad'] }}</code>
                                                @else
                                                    <span class="text-muted">Valor predeterminado</span>
                                                @endif
                                                <strong class="float-right">{{ number_format((float) $p['cantidad'], 4, ',', '.') }}</strong>
                                            </div>
                                            @if (! empty($p['rastro_cantidad']))
                                                <div class="rastro-panel rastro-tree mb-2">
                                                    <ul>
                                                        @foreach ($p['rastro_cantidad'] as $nodo)
                                                            @include('sueldos.liquidacion.partials.rastro_nodo', ['nodo' => $nodo])
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="col-lg-4">
                                            <small class="text-muted d-block">2. Valor</small>
                                            <div class="traza-formula mb-2">
                                                @if (! empty($p['formula_valor']))
                                                    <code>{{ $p['formula_valor'] }}</code>
                                                @else
                                                    <span class="text-muted">Valor predeterminado</span>
                                                @endif
                                                <strong class="float-right">{{ number_format((float) $p['valor'], 4, ',', '.') }}</strong>
                                            </div>
                                            @if (! empty($p['rastro_valor']))
                                                <div class="rastro-panel rastro-tree mb-2">
                                                    <ul>
                                                        @foreach ($p['rastro_valor'] as $nodo)
                                                            @include('sueldos.liquidacion.partials.rastro_nodo', ['nodo' => $nodo])
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="col-lg-4">
                                            <small class="text-muted d-block">3. Importe</small>
                                            <div class="traza-formula mb-2">
                                                @if (! empty($p['formula']))
                                                    <code>{{ $p['formula'] }}</code>
                                                @else
                                                    <span class="text-muted">Cantidad &times; valor</span>
                                                @endif
                                                <strong class="float-right">$ {{ number_format((float) $p['importe'], 2, ',', '.') }}</strong>
                                            </div>
                                            @if (! empty($p['rastro']))
                                                <div class="rastro-panel rastro-tree mb-2">
                                                    <ul>
                                                        @foreach ($p['rastro'] as $nodo)
                                                            @include('sueldos.liquidacion.partials.rastro_nodo', ['nodo' => $nodo])
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @elseif (! empty($p['rastro_texto']))
                                                <div class="rastro-panel mb-2"><code>{{ $p['rastro_texto'] }}</code></div>
                                            @endif
                                        </div>
                                    </div>

                                    @if (! empty($p['acumuladores']))
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-link btn-sm p-0" data-toggle="collapse" data-target="#acum-{{ $pasoId }}">
                                                <i class="fa fa-database"></i> Acumuladores despu&eacute;s del concepto ({{ count($p['acumuladores']) }})
                                            </button>
                                            <div class="collapse mt-1" id="acum-{{ $pasoId }}">
                                                @foreach ($p['acumuladores'] as $cod => $val)
                                                    <span class="badge badge-light border mb-1">{{ $cod }}: {{ number_format((float) $val, 2, ',', '.') }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-warning">No hay conceptos activos para ejecutar en el set efectivo del empleado.</div>
                    @endforelse
                </div>

                <div id="traza-sin-resultados" class="alert alert-warning d-none">
                    No hay conceptos que coincidan con los filtros.
                </div>

                <div class="card card-outline card-info mt-3">
                    <div class="card-header py-2" data-toggle="collapse" data-target="#traza-contexto" style="cursor:pointer;">
                        <strong><i class="fa fa-info-circle"></i> Contexto de la liquidaci&oacute;n</strong>
                        <span class="text-muted ml-1">Variables iniciales y resultado final del motor</span>
                    </div>
                    <div class="collapse" id="traza-contexto">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-6">
                                    <h6>Variables iniciales</h6>
                                    <div class="table-responsive" style="max-height:320px;">
                                        <table class="table table-sm table-striped traza-contexto-table">
                                            <tbody>
                                                @forelse (($contextoInicial['variables'] ?? []) as $clave => $valor)
                                                    <tr><td>{{ $clave }}</td><td>{{ $fmtContexto($valor) }}</td></tr>
                                                @empty
                                                    <tr><td colspan="2" class="text-muted">Sin variables registradas.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <h6>Acumuladores finales</h6>
                                    <div class="table-responsive" style="max-height:320px;">
                                        <table class="table table-sm table-striped traza-contexto-table">
                                            <tbody>
                                                @forelse (($contextoFinal['acumuladores'] ?? []) as $clave => $valor)
                                                    <tr><td>{{ $clave }}</td><td>{{ $fmtContexto($valor) }}</td></tr>
                                                @empty
                                                    <tr><td colspan="2" class="text-muted">Sin acumuladores registrados.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
