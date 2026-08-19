@extends("theme.$theme.layout")
@section('titulo')
    Flash — reporte histórico
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js')) ?: time() }}" type="text/javascript"></script>
<script>
(function () {
    var form = document.getElementById('form-flash-historico');
    if (! form) {
        return;
    }
    form.addEventListener('submit', function () {
        var btnConsol = form.querySelector('.btn-toggle-consolidar-empresas');
        var inputConsol = form.querySelector('input[name="consolidar_empresas"]');
        if (btnConsol && inputConsol) {
            inputConsol.value = btnConsol.classList.contains('btn-success') ? '1' : '0';
        }
        var btn = document.getElementById('btn-consultar-flash-historico');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Consultando…';
        }
    });
})();
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Consolidated Income (Flash Report)</h3>
                <div class="card-tools">
                    <a href="{{ route('flash_caja_reporte_historico') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                    @if (can('listar-flash-reporte-agg', false))
                        <a href="{{ route('flash_reporte_agg') }}" class="btn btn-outline-success btn-sm">
                            <i class="fa fa-file-excel-o"></i> Flash Report AGG
                        </a>
                    @endif
                    <a href="{{ route('flash_caja') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('flash_caja_reporte_historico') }}" id="form-flash-historico" class="mb-0" autocomplete="off">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Informe mensual estilo l-flash (Consolidated Income). Permite consolidar varias empresas.
                    </p>

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'flash_caja_historico',
                        'id_prefix' => 'flh',
                        'col_label' => 'col-lg-2 text-right',
                    ])

                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right requerido">Desde / Hasta</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-3">
                                    <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" required
                                        value="{{ $filtros['fecha_desde'] ?? '' }}">
                                </div>
                                <div class="col-md-3">
                                    <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" required
                                        value="{{ $filtros['fecha_hasta'] ?? '' }}">
                                </div>
                            </div>
                            <small class="form-text text-muted">El listado arranca el día 1 del mes (como l-flash).</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="con_season" class="col-lg-2 control-label text-right">Season index</label>
                        <div class="col-lg-4">
                            <select name="con_season" id="con_season" class="form-control">
                                <option value="1" {{ (int) ($filtros['con_season'] ?? 1) === 1 ? 'selected' : '' }}>Con season index</option>
                                <option value="0" {{ (int) ($filtros['con_season'] ?? 1) === 0 ? 'selected' : '' }}>Sin season index</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row mb-0 mt-3">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar-flash-historico">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if($consultado ?? false)
                <div class="card-body border-top pt-3">
                    @if($reporte !== null)
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_flash_caja_reporte_historico',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                        <p class="text-muted small mb-3">
                            {{ $subtitulo ?? '' }}
                            — {{ $reporte['cantidad_dias'] ?? 0 }} día(s) registrados
                            @if(!empty($reporte['through_day']))
                                — Through day: {{ $reporte['through_day'] }}
                            @endif
                        </p>

                        @if(!empty($reporte['empresas_texto']))
                            <p class="mb-2 small">
                                <strong>Empresa{{ count($filtros['empresa_ids'] ?? []) > 1 ? 's' : '' }}:</strong>
                                {{ $reporte['empresas_texto'] }}
                                @if(count($filtros['empresa_ids'] ?? []) > 1)
                                    · <strong>Modo:</strong>
                                    @if(!empty($reporte['consolidar_empresas']))
                                        consolidado
                                    @else
                                        un reporte por empresa
                                    @endif
                                @endif
                            </p>
                        @endif

                        @php
                            $secciones = (!empty($reporte['secciones']) && empty($reporte['consolidar_empresas']))
                                ? $reporte['secciones']
                                : [$reporte];
                        @endphp

                        @foreach($secciones as $seccionReporte)
                            @if(count($secciones) > 1)
                                <h5 class="mt-3 mb-2">
                                    {{ $seccionReporte['empresa']->nombre ?? ($seccionReporte['empresas_texto'] ?? '') }}
                                </h5>
                            @endif
                            @if(!empty($seccionReporte['filas_diarias']))
                                @include('caja.flash.partials.tabla_consolidated_income', [
                                    'reporte' => $seccionReporte,
                                    'mostrar_acciones' => count($secciones) === 1,
                                ])
                            @else
                                <div class="alert alert-warning">No hay registros flash en el período seleccionado.</div>
                            @endif
                        @endforeach
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
