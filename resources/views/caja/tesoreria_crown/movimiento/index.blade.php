@extends("theme.$theme.layout")

@section('titulo')
    Habilitaciones y rendiciones
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
        <div class="card card-info lw-workbench shadow-sm" data-form="#form-filtros-tesoreria-crown-movimiento">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Habilitaciones y rendiciones</h3>
            </div>
            <form method="get" action="{{ route('tesoreria_crown_movimiento') }}" id="form-filtros-tesoreria-crown-movimiento" class="mb-0">
                <input type="hidden" name="sala_id" value="{{ $sala->id ?? '' }}">
                <input type="hidden" name="vista_id" value="{{ $vistaActiva->id ?? '' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                @include('caja.tesoreria_crown.partials.workbench_barra', ['rutaListado' => 'tesoreria_crown_movimiento'])
                @include('caja.tesoreria_crown.partials.workbench_qbe')
            </form>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_movimiento',
                'exportRuta' => 'listar_tesoreria_crown_movimiento',
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('caja.tesoreria_crown.partials.workbench_th', ['key' => $keyColumna, 'rutaListado' => 'tesoreria_crown_movimiento'])
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($comprobantes as $comprobante)
                            <tr>
                                @foreach ($columnasVisibles as $keyColumna)
                                    <td>
                                        @switch($keyColumna)
                                            @case('numero')
                                                <a class="text-primary" href="{{ route('editar_tesoreria_crown_movimiento', $comprobante->id) }}">
                                                    {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($comprobante->numero) }}
                                                </a>
                                                @break
                                            @case('fecha')
                                                <a class="text-primary" href="{{ route('editar_tesoreria_crown_movimiento', $comprobante->id) }}">
                                                    {{ $comprobante->jornada->fecha->format('d/m/Y') }}
                                                </a>
                                                @break
                                            @case('tipo')
                                                {{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::tipo($comprobante->tipo) }}
                                                @if ($comprobante->revierte_id)
                                                    <span class="text-muted">reversa</span>
                                                @endif
                                                @break
                                            @case('caja')
                                                {{ $comprobante->asignacion->deposito->codigo ?? '' }} — {{ $comprobante->asignacion->deposito->nombre ?? '' }}
                                                @break
                                            @case('cajero')
                                                {{ $comprobante->asignacion->cajero->nombre ?? '' }}
                                                @break
                                            @case('estado')
                                                {{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::estado($comprobante->estado) }}
                                                @break
                                            @case('entrega')
                                                {{ $comprobante->entrega_at?->format('d/m/Y H:i') ?? '—' }}
                                                @break
                                            @case('recibe')
                                                {{ $comprobante->recibe_at?->format('d/m/Y H:i') ?? '—' }}
                                                @break
                                        @endswitch
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columnasVisibles) }}" class="text-center text-muted py-3">
                                    @if ($tieneCriterios)
                                        No hay habilitaciones ni rendiciones para este filtro.
                                    @else
                                        No hay habilitaciones ni rendiciones en esta sala. Se crean desde la jornada, en la caja del cajero.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                @if ($comprobantes->total() > 0)
                    <span class="text-muted">{{ $comprobantes->firstItem() }}–{{ $comprobantes->lastItem() }} de {{ $comprobantes->total() }}</span>
                @endif
                {{ $comprobantes->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@include('caja.tesoreria_crown.partials.workbench_modales', [
    'rutaGuardarColumnas' => 'guardar_columnas_tesoreria_crown_movimiento',
    'rutaGuardarVista' => 'guardar_vista_tesoreria_crown_movimiento',
    'rutaEliminarVista' => 'eliminar_vista_tesoreria_crown_movimiento',
    'rutaPreview' => 'preview_tesoreria_crown_movimiento',
    'rutaGuardarEtiquetas' => 'guardar_etiquetas_tesoreria_crown_movimiento',
])
@endsection
