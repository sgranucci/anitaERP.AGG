@extends("theme.$theme.layout")
@section('titulo')
    Control de ganancias
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor_control_ganancias_reporte/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor_control_ganancias_reporte/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Control de ganancias — proveedores</h3>
                <div class="card-tools">
                    <a href="{{ route('proveedor_control_ganancias_reporte') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('proveedor_control_ganancias_reporte') }}" id="form-control-ganancias" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Cruza el ABM de proveedores con la constancia de inscripción ARCA y propone el código de retención
                        de ganancias según la actividad principal (RI) o “No aplica retención” (Monotributistas).
                        <strong>Solo genera el reporte</strong>; no actualiza el maestro.
                        El mapeo actividad→código se configura en <code>config/compras_control_ganancias.php</code>.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                        $empresasDisponibles = collect($empresa_query ?? []);
                    @endphp

                    <div class="form-group row">
                        <label for="empresa_id" class="{{ $colLabel }} requerido">Empresa</label>
                        <div class="{{ $colInput }}">
                            @if ($empresasDisponibles->count() > 1)
                                <select name="empresa_id" id="empresa_id" class="form-control" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($empresasDisponibles as $emp)
                                        <option value="{{ $emp->id }}" @selected((int) ($filtros['empresa_id'] ?? 0) === (int) $emp->id)>
                                            {{ $emp->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            @elseif ($empresasDisponibles->count() === 1)
                                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ (int) $empresasDisponibles->first()->id }}">
                                <span class="form-control-plaintext">{{ $empresasDisponibles->first()->nombre }}</span>
                            @else
                                <p class="text-danger small mb-0">Sin empresas asignadas.</p>
                            @endif
                        </div>
                        <label for="segmento" class="{{ $colLabel }}">Segmento</label>
                        <div class="{{ $colInput }}">
                            <select name="segmento" id="segmento" class="form-control">
                                @foreach ($segmento_enum ?? [] as $value => $label)
                                    <option value="{{ $value }}" @selected(($filtros['segmento'] ?? 'todos') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="estado" class="{{ $colLabel }}">Estado</label>
                        <div class="{{ $colInput }}">
                            <select name="estado" id="estado" class="form-control">
                                <option value="Activo" @selected(($filtros['estado'] ?? 'Activo') === 'Activo')>Activo</option>
                                <option value="Suspendido" @selected(($filtros['estado'] ?? '') === 'Suspendido')>Suspendido</option>
                                <option value="todos" @selected(($filtros['estado'] ?? '') === '')>Todos</option>
                            </select>
                        </div>
                        <label for="limite" class="{{ $colLabel }}">Límite proveedores</label>
                        <div class="{{ $colInput }}">
                            <input type="number" name="limite" id="limite" class="form-control" min="0"
                                max="{{ (int) config('compras_control_ganancias.limite_max', 20000) }}"
                                placeholder="0 = todos"
                                value="{{ (int) ($filtros['limite'] ?? 100) }}">
                            <small class="form-text text-muted">0 = todos los proveedores del filtro. Con consulta ARCA, el total puede demorar varios minutos.</small>
                        </div>
                    </div>

                    @include('includes.compras.campo_proveedor_consulta', [
                        'proveedor_id' => ($filtros['proveedor_id'] ?? 0) > 0 ? $filtros['proveedor_id'] : '',
                        'codigo_proveedor' => $filtros['proveedor_codigo'] ?? '',
                        'nombre_proveedor' => $filtros['proveedor_nombre'] ?? '',
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                        'requerido' => false,
                        'mostrar_ayuda' => true,
                    ])

                    <div class="form-group row">
                        <div class="{{ $colLabel }} d-none d-lg-block"></div>
                        <div class="{{ $colInput }}">
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="consultar_arca" id="consultar_arca" value="1"
                                    @checked($filtros['consultar_arca'] ?? true)>
                                <label class="form-check-label" for="consultar_arca">Consultar constancia ARCA (actividad / padrón)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="solo_discrepancias" id="solo_discrepancias" value="1"
                                    @checked($filtros['solo_discrepancias'] ?? true)>
                                <label class="form-check-label" for="solo_discrepancias">Solo filas con discrepancia</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="{{ $colLabel }} d-none d-lg-block"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @include('includes.proceso_overlay_aviso', [
                'overlayId' => 'control-ganancias-overlay',
                'tituloId' => 'control-ganancias-titulo',
                'subtituloId' => 'control-ganancias-subtitulo',
                'titulo' => 'Generando control de ganancias…',
                'subtitulo' => 'Consulta ARCA por CUIT. Puede demorar según el límite. No cierre la página.',
            ])

            @if (is_array($lote ?? null) && ($lote['estado'] ?? '') !== 'listo')
                <div class="card-body border-top">
                    <div class="alert alert-info mb-0">
                        <strong>{{ $lote['mensaje'] ?? 'Generando el control…' }}</strong>
                        @if ((int) ($lote['total'] ?? 0) > 0)
                            <div class="small mt-1">{{ (int) ($lote['procesados'] ?? 0) }} / {{ (int) $lote['total'] }} proveedores</div>
                        @endif
                        @if (($lote['estado'] ?? '') === 'error')
                            <div class="small text-danger mt-1">{{ $lote['error'] ?? '' }} Volvé a consultar para reintentar.</div>
                        @else
                            <div class="small text-muted mt-1">La consulta corre en segundo plano. Esta página se actualiza sola.</div>
                        @endif
                    </div>
                </div>
                @if (($lote['estado'] ?? '') === 'corriendo')
                    <script>setTimeout(function () { window.location.reload(); }, 5000);</script>
                @endif
            @endif

            @if ($consultado ?? false)
                <div class="card-body p-0 border-top">
                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-0 small">{{ $subtitulo ?? '' }}</p>
                        @foreach ($resultado['advertencias'] ?? [] as $adv)
                            <p class="mb-0 small text-warning"><i class="fa fa-exclamation-triangle"></i> {{ $adv }}</p>
                        @endforeach
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_proveedor_control_ganancias_reporte',
                                'queryparams' => array_merge($filtrosQuery ?? [], ['consultar' => 1]),
                            ])
                        </div>
                        @if (! empty($resultado['stats']))
                            <div class="small mb-1 mb-md-0 text-md-right">
                                Evaluados: <strong>{{ (int) ($resultado['stats']['proveedores_evaluados'] ?? 0) }}</strong>
                                · Filas: <strong>{{ (int) ($resultado['stats']['filas'] ?? 0) }}</strong>
                                · Discrepancias: <strong>{{ (int) ($resultado['stats']['discrepancias'] ?? 0) }}</strong>
                                · ARCA: <strong>{{ (int) ($resultado['stats']['consultas_arca'] ?? 0) }}</strong>
                            </div>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table id="tabla-paginada" class="table table-sm table-bordered table-hover mb-0" style="font-size: 0.8rem;">
                            @include('compras.proveedor_control_ganancias_reporte.partials.tabla_datos', [
                                'filas' => $filasVista ?? [],
                                'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="px-3 py-2 d-flex flex-wrap align-items-center justify-content-between border-top">
                            <div class="small text-muted mb-1">
                                @if ($filas->total() > 0)
                                    Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                                @endif
                            </div>
                            <div>{{ $filas->links() }}</div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@endsection
