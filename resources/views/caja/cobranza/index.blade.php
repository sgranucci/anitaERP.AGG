@extends("theme.$theme.layout")
@section('titulo')
    Cobranzas
@endsection

@if ($wbActivo ?? false)
@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<style>
    /* Acciones: editar / borrar / imprimir. Sticky lo prende tabla-ancha-reporte.js si hay scroll. */
    .lw-workbench .cobranza-grilla-ancha {
        --tabla-ancha-der: 6.5rem;
    }
    .lw-workbench .cobranza-grilla-ancha th.lw-col-acciones,
    .lw-workbench .cobranza-grilla-ancha td.lw-col-acciones {
        width: var(--tabla-ancha-der) !important;
        min-width: var(--tabla-ancha-der) !important;
        max-width: var(--tabla-ancha-der) !important;
        padding-left: 0.1rem;
        padding-right: 0.1rem;
    }
    .lw-workbench .cobranza-grilla-ancha .lw-acciones-inner {
        gap: 0;
    }
    .lw-workbench .cobranza-grilla-ancha .lw-acciones-inner .btn-accion-tabla {
        min-width: 1.45rem;
        padding: 0 0.05rem;
    }
    .lw-workbench .cobranza-grilla-ancha .lw-acciones-inner form {
        display: inline-flex;
        align-items: center;
        margin: 0;
        padding: 0;
        border: 0;
        flex: 0 0 auto;
    }
    /* Vista estándar: tabla al 100% (tabla-ancha trae width:max-content). */
    .lw-workbench .cobranza-grilla-ancha .lw-table-fit .tabla-ancha {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100%;
    }
    .lw-workbench .cobranza-grilla-ancha .lw-table-fit .tabla-ancha td.lw-col {
        max-width: 0;
    }
    #tabla-paginada td.lw-col ul {
        margin: 0;
        padding-left: 1rem;
        max-width: 100%;
    }
    #tabla-paginada td.lw-col li {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 100%;
    }
</style>
@endsection
@endif

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cobranza/index.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/cobranza/index.js')) }}" type="text/javascript"></script>
@if ($wbActivo ?? false)
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $cobranzaWorkbenchJs = public_path('assets/pages/scripts/caja/cobranza/workbench.js');
    $columnasDndJs = public_path('assets/pages/scripts/listado/workbench-columnas-dnd.js');
    $tablaAnchaJs = public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/cobranza/workbench.js') }}?v={{ file_exists($cobranzaWorkbenchJs) ? filemtime($cobranzaWorkbenchJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-columnas-dnd.js') }}?v={{ file_exists($columnasDndJs) ? filemtime($columnasDndJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ file_exists($tablaAnchaJs) ? filemtime($tablaAnchaJs) : time() }}"></script>
@endif

<script>
    function eliminarCobranza(event) {
        var opcion = confirm("Desea eliminar la cobranza?");
        if(!opcion) {
            event.preventDefault();
        }
    }
</script>

@endsection

<?php use App\Helpers\biblioteca ?>

@php
    use App\Support\Caja\CobranzaListadoFiltros;
    use App\Support\Listado\ListadoOrdenamientoSupport;
    use App\Support\Listado\ListadoQbeSupport;

    $wbActivo = (bool) ($wbActivo ?? false);
    $filtrosQuery = $filtrosQuery ?? [];
    $vistaActiva = $vistaActiva ?? null;

    // Hidden de los chips (empresa / estado): solo lo que se eligió explícitamente.
    $hiddenEmpresaTodas = ! empty($filtrosQuery['empresa_todas']);
    $hiddenEmpresaId = (! $hiddenEmpresaTodas && ! empty($filtrosQuery['empresa_id'])) ? (int) $filtrosQuery['empresa_id'] : 0;
    $hiddenEstado = (string) ($filtrosQuery['estado'] ?? '');

    $columnasVisibles = $columnasVisibles ?? [];
    $limpiarQ = CobranzaListadoFiltros::paraQueryStringEmpresa($filtros ?? []);
    if (($filtros['estado'] ?? '') !== '') {
        $limpiarQ['estado'] = $filtros['estado'];
    }
    if ($vistaActiva) {
        $limpiarQ['vista_id'] = $vistaActiva->id;
    } else {
        $limpiarQ['vista_estandar'] = 1;
    }
    $limpiarQ['quitar_qbe'] = 1;
    $limpiarUrl = route('cobranza', $limpiarQ);

    $qsQuitarOrden = $filtrosQuery;
    unset($qsQuitarOrden['sort']);
    $qsQuitarOrden['quitar_orden'] = 1;
    $urlQuitarOrden = route('cobranza', $qsQuitarOrden);

    $qbeAbierto = ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
    $hayCriteriosTexto = $qbeAbierto
        || trim((string) ($filtros['busqueda'] ?? '')) !== ''
        || ! empty($filtros['fecha_desde'])
        || ! empty($filtros['fecha_hasta']);
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Cobranzas</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    <a href="{{route('crear_cobranza')}}" class="btn btn-light btn-sm">
                       	@if (can('crear-cobranza', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
            </div>
            @if (! ($workbenchListo ?? false))
                <div class="alert alert-warning lw-aviso-migracion mb-0">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif

            <form action="{{ route('cobranza') }}" method="GET" class="mb-0" id="form-filtros-cobranza">
                @if ($hiddenEmpresaTodas)
                    <input type="hidden" name="empresa_todas" value="1">
                @elseif ($hiddenEmpresaId > 0)
                    <input type="hidden" name="empresa_id" value="{{ $hiddenEmpresaId }}">
                @endif
                @if ($hiddenEstado !== '')
                    <input type="hidden" name="estado" value="{{ $hiddenEstado }}">
                @endif
                @include('caja.cobranza.partials.filtro_origen_ferli')

                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="todos">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if (($filtros['busqueda'] ?? '') !== '')
                    <input type="hidden" name="busqueda" value="{{ $filtros['busqueda'] }}">
                @endif
                @if (! empty($fechaDesde))
                    <input type="hidden" name="fecha_desde" value="{{ $fechaDesde }}">
                @endif
                @if (! empty($fechaHasta))
                    <input type="hidden" name="fecha_hasta" value="{{ $fechaHasta }}">
                @endif
                @if ($vistaActiva)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @elseif (! empty($filtrosQuery['vista_estandar']))
                    <input type="hidden" name="vista_estandar" value="1">
                @endif

                {{-- Igual que IE/pagos: toolbar workbench arriba, chips de filtros abajo. --}}
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('cobranza') }}"
                                title="Vistas guardadas"
                                @if (! ($workbenchListo ?? false)) disabled @endif>
                            <option value="">Vista estándar</option>
                            @foreach (($vistasListado ?? []) as $vista)
                                <option value="{{ $vista->id }}" @if ($vistaActiva && (int) $vistaActiva->id === (int) $vista->id) selected @endif>
                                    {{ $vista->nombre }}
                                    @if ($vista->es_default) ★ @endif
                                    @if ($vista->compartida) (compartida) @endif
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal-lw-grilla"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-th"></i> Diseñar vista
                        </button>
                        <button type="button" class="btn btn-sm {{ $qbeAbierto ? 'btn-info' : 'btn-outline-info' }} {{ $qbeAbierto ? '' : 'collapsed' }}" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="{{ $qbeAbierto ? 'true' : 'false' }}" aria-controls="lw-qbe-panel" title="Consulta avanzada">
                            <i class="fa fa-filter"></i> Consulta avanzada
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info collapsed" data-toggle="collapse" data-target="#lw-analisis-panel" aria-expanded="false" title="Gráfico y color de fila">
                            <i class="fa fa-bar-chart"></i> Visual
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-font"></i> Defaults instalación
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ $filtros['busqueda'] ?? '' }}"
                               placeholder="Cliente, número, detalle…"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if ($hayCriteriosTexto)
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                        @if (($ordenActualThead ?? []) !== [])
                            <a href="{{ $urlQuitarOrden }}" class="btn btn-sm btn-outline-secondary" title="Vuelve al orden por ID descendente y lo saca de la vista">
                                <i class="fa fa-undo"></i> Orden estándar
                            </a>
                        @endif
                    </div>
                </div>

                @include('caja.cobranza.partials.filtros_externos')
                @include('caja.cobranza.partials.workbench_qbe')
                @php
                    $ejesVisual = \App\Support\Caja\CobranzaListadoVisual::campos();
                    $medidasVisual = \App\Support\Listado\ListadoVisualSupport::medidasDisponibles($ejesVisual);
                    $lienzoVisual = true;
                @endphp
                @include('includes.listado.workbench_visual')
            </form>
            @include('includes.listado.workbench_grafico', [
                'recursoVisual' => 'caja.cobranza',
                'rutaListadoVisual' => 'cobranza',
            ])
            <p class="text-muted small mb-2 px-3">
                Prom. días: días desde la fecha de cada factura hasta el recibo, ponderados por el importe cobrado.
                Para el listado de un día, cargá la misma fecha en Desde y Hasta y exportá.
            </p>

            @include('includes.proceso_overlay_aviso', [
                'overlayId' => 'cobranza-export-overlay',
                'tituloId' => 'cobranza-export-titulo',
                'subtituloId' => 'cobranza-export-subtitulo',
                'titulo' => 'Exportando…',
                'subtitulo' => 'Generando el listado. Puede demorar según el volumen.',
            ])
            @include('caja.cobranza.partials.tabla_workbench')
        </div>
    </div>
</div>
{{ $cobranza->appends($filtrosQuery ?? [])->links() }}
{{-- Reordenar/ocultar columnas en thead; agregar = Diseñar vista. --}}
@include('includes.listado.workbench_columnas_dnd', [
    'rutaGuardarColumnasDnd' => 'guardar_columnas_listado_cobranza',
    'paraQueryStringDnd' => [\App\Support\Caja\CobranzaListadoFiltros::class, 'paraQueryString'],
    'filtrosDnd' => $filtros ?? [],
])
@include('caja.cobranza.partials.workbench_modales')
@endsection
