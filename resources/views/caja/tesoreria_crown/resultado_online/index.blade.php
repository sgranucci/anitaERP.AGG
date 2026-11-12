@extends("theme.$theme.layout")

@section('titulo')
    Resultado online
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-orden.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-agrupar.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-disenador-preview.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-vista-guardar.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/workbench.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/workbench.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/listado.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/listado.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tesoreria-crown-overlay',
    'tituloId' => 'tesoreria-crown-overlay-titulo',
    'subtituloId' => 'tesoreria-crown-overlay-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo.',
])
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info lw-workbench shadow-sm" data-form="#form-filtros-resultado-online">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Resultado online</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center" style="gap:.4rem;">
                    @if (can('listar-tesoreria-crown-casino-online', false) || can('crear-tesoreria-crown-casino-online', false))
                        <a href="{{ route('tesoreria_crown_casino_online') }}" class="btn btn-sm" style="background:linear-gradient(135deg,#8e44ad 0%,#2980b9 55%,#16a085 100%);border:0;color:#fff;font-weight:600;" title="Carga global Del Río / Betsur / Apuestas (una vez por día)">
                            <i class="fa fa-globe"></i> Casino online diario
                        </a>
                    @endif
                    @if ($sala && can('crear-tesoreria-crown-resultado-online', false))
                        <a href="{{ route('crear_tesoreria_crown_resultado_online', ['sala_id' => $sala->id]) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus-circle"></i> Nuevo resultado
                        </a>
                    @endif
                </div>
            </div>
            <form method="get" action="{{ route('tesoreria_crown_resultado_online') }}" id="form-filtros-resultado-online" class="mb-0">
                <input type="hidden" name="sala_id" value="{{ $sala->id ?? '' }}">
                <input type="hidden" name="vista_id" value="{{ $vistaActiva->id ?? '' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                @include('caja.tesoreria_crown.partials.workbench_barra', [
                    'rutaListado' => 'tesoreria_crown_resultado_online',
                    'limpiarParams' => ($sala ?? null) ? ['sala_id' => $sala->id, 'vista_estandar' => 1] : ['vista_estandar' => 1],
                    'conVisual' => true,
                ])
                <div class="px-3 py-2 border-bottom bg-light d-flex flex-wrap align-items-center" style="gap:.4rem;">
                    <label for="fecha_desde" class="mb-0 small text-muted">Desde</label>
                    <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm" style="width:10rem;" value="{{ $filtros['fecha_desde'] ?? '' }}">
                    <label for="fecha_hasta" class="mb-0 small text-muted">Hasta</label>
                    <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm" style="width:10rem;" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Aplicar</button>
                </div>
                @include('caja.tesoreria_crown.resultado_online.partials.workbench_qbe')
                @php
                    $ejesVisual = \App\Support\Caja\TesoreriaCrownResultadoOnlineListadoFiltros::camposOrdenables();
                    $medidasVisual = \App\Support\Listado\ListadoVisualSupport::medidasDisponibles($ejesVisual);
                    $lienzoVisual = true;
                @endphp
                @include('includes.listado.workbench_visual')
            </form>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_resultado_online',
                'exportRuta' => 'listar_tesoreria_crown_resultado_online',
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            @include('includes.listado.workbench_cortes')
            @include('includes.listado.workbench_grafico', [
                'recursoVisual' => \App\Support\Caja\TesoreriaCrownResultadoOnlineListadoColumnas::RECURSO,
                'rutaListadoVisual' => 'tesoreria_crown_resultado_online',
            ])
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('caja.tesoreria_crown.partials.workbench_th', [
                                    'key' => $keyColumna,
                                    'rutaListado' => 'tesoreria_crown_resultado_online',
                                    'thClass' => \App\Support\Caja\TesoreriaCrownResultadoOnlineListadoColumnas::esNumero($keyColumna) ? 'text-right' : '',
                                ])
                            @endforeach
                            <th class="text-left" style="width:90px;" data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            <tr>
                                @foreach ($columnasVisibles as $keyColumna)
                                    <td class="{{ \App\Support\Caja\TesoreriaCrownResultadoOnlineListadoColumnas::esNumero($keyColumna) ? 'text-right' : '' }}">
                                        @if ($keyColumna === 'fecha')
                                            <a class="text-primary" href="{{ route('editar_tesoreria_crown_resultado_online', ['id' => $fila->id, 'sala_id' => $fila->sala_id]) }}">{{ $fila->fecha?->format('d/m/Y') }}</a>
                                        @else
                                            {{ \App\Support\Caja\TesoreriaCrownResultadoOnlineListadoColumnas::valorCelda($fila, $keyColumna) }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-left text-nowrap">
                                    @if (can('editar-tesoreria-crown-resultado-online', false))
                                        <a href="{{ route('editar_tesoreria_crown_resultado_online', ['id' => $fila->id, 'sala_id' => $fila->sala_id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('eliminar-tesoreria-crown-resultado-online', false))
                                        <form action="{{ route('eliminar_tesoreria_crown_resultado_online', $fila->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar el resultado del {{ $fila->fecha?->format('d/m/Y') }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-accion-tabla tooltipsC" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columnasVisibles) + 1 }}" class="text-center text-muted py-3">{{ $tieneCriterios ? 'No hay resultados para este filtro.' : 'No hay resultados cargados en esta sala.' }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                @if ($filas->total() > 0)
                    <span class="text-muted">{{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}</span>
                @endif
                {{ $filas->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@include('caja.tesoreria_crown.partials.workbench_modales', [
    'rutaGuardarColumnas' => 'guardar_columnas_tesoreria_crown_resultado_online',
    'rutaGuardarVista' => 'guardar_vista_tesoreria_crown_resultado_online',
    'rutaEliminarVista' => 'eliminar_vista_tesoreria_crown_resultado_online',
    'rutaPreview' => 'preview_tesoreria_crown_resultado_online',
    'rutaGuardarEtiquetas' => 'guardar_etiquetas_tesoreria_crown_resultado_online',
])
@endsection
