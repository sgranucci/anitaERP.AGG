@extends("theme.$theme.layout")
@section('titulo')
    Lista de precios
@endsection

@section('contenido')
@php
    $estadoSeleccionado = ($filtros['estado'] ?? 'ACTIVO') === '' ? 'TODOS' : ($filtros['estado'] ?? 'ACTIVO');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Lista de precios</h3>
                <div class="card-tools ml-auto">
                    <a href="{{ route('precio_lista_ferli') }}" class="btn btn-outline-light btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                    <a href="{{ route('precio') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a precios
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('precio_lista_ferli') }}" id="form-lista-ferli" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Un artículo por fila. Las listas que agregás salen como columnas, con el precio vigente a la fecha.
                    </p>
                    @if (! empty($error))
                        <div class="alert alert-warning">{{ $error }}</div>
                    @endif

                    <div class="form-group row">
                        <label for="fecha_vigencia" class="col-lg-3 control-label text-right pr-2">Vigente al</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_vigencia" id="fecha_vigencia" class="form-control"
                                   value="{{ $filtros['fecha_vigencia'] ?? date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Marca</label>
                        <div class="col-lg-9 pt-1">
                            @forelse ($marcas as $marca)
                                <div class="custom-control custom-checkbox custom-control-inline">
                                    <input type="checkbox" class="custom-control-input" id="marca_{{ $marca['id'] }}"
                                           name="mventa_id[]" value="{{ $marca['id'] }}"
                                           @checked(in_array($marca['id'], $filtros['mventa_ids'] ?? [], true))>
                                    <label class="custom-control-label" for="marca_{{ $marca['id'] }}">{{ $marca['nombre'] }}</label>
                                </div>
                            @empty
                                <span class="text-muted">No hay marcas Ferli, Fragola, Tomahawk ni Boaonda cargadas.</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="canal" class="col-lg-3 control-label text-right pr-2">Canal</label>
                        <div class="col-lg-3">
                            <select name="canal" id="canal" class="form-control">
                                @foreach (\App\Support\Stock\PrecioListaFerliFiltros::CANALES as $cod => $label)
                                    <option value="{{ $cod }}" @selected(($filtros['canal'] ?? '') === $cod)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="estado" class="col-lg-2 control-label text-right pr-2">Estado</label>
                        <div class="col-lg-3">
                            <select name="estado" id="estado" class="form-control">
                                @foreach (\App\Support\Stock\PrecioListaFerliFiltros::ESTADOS as $cod => $label)
                                    <option value="{{ $cod }}" @selected($estadoSeleccionado === $cod)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="facturable" class="col-lg-3 control-label text-right pr-2">Facturables</label>
                        <div class="col-lg-3">
                            <select name="facturable" id="facturable" class="form-control">
                                @foreach (\App\Support\Stock\PrecioListaFerliFiltros::FACTURABLES as $cod => $label)
                                    <option value="{{ $cod }}" @selected((string) ($filtros['facturable'] ?? '0') === (string) $cod)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @include('stock.partials.campo_consulta_listaprecio', [
                        'prefix' => 'listaferli',
                        'label' => 'Incluye listas',
                        'layout' => 'form_row',
                        'inputName' => 'lista_borrador_id',
                        'inputId' => 'listaferli_listaprecio_id',
                        'listaprecioId' => '',
                        'codigo' => '',
                        'nombre' => '',
                        'required' => false,
                        'mostrar_editar' => false,
                        'col_label' => 'col-lg-3 control-label text-right pr-2',
                        'col_input' => 'col-lg-6',
                    ])

                    <div class="form-group row">
                        <div class="col-lg-9 offset-lg-3">
                            <button type="button" class="btn btn-outline-primary btn-sm mb-2" id="btn-agregar-lista">
                                <i class="fa fa-plus"></i> Agregar lista
                            </button>
                            <div id="listas-elegidas">
                                @foreach ($listas as $lista)
                                    <span class="badge badge-info mr-1 mb-1 lista-ferli-chip">
                                        {{ $lista['codigo'] }}{{ $lista['nombre'] !== '' ? ' — '.$lista['nombre'] : '' }}
                                        <button type="button" class="btn btn-link btn-sm text-white p-0 ml-1 quitar-lista-ferli" title="Quitar">&times;</button>
                                        <input type="hidden" name="listaprecio_id[]" value="{{ $lista['id'] }}">
                                    </span>
                                @endforeach
                            </div>
                            <small class="form-text text-muted">Código y Enter, o la lupa. Podés pedir solo la 11, o la 11, 12 y 13.</small>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-9 offset-lg-3">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado && $filas)
                <div class="card-body pt-0">
                    @if (! empty($logosCabecera))
                        <div class="mb-2">
                            @foreach ($logosCabecera as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] ?? '' }}" style="max-height: 40px; max-width: 140px;" class="mr-2">
                            @endforeach
                        </div>
                    @endif
                    <p class="small text-muted mb-2">{{ $subtitulo }}</p>
                    <div id="export-lista-ferli">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_precio_lista_ferli',
                            'queryparams' => $filtrosQuery ?? [],
                            'variant' => 'compact',
                        ])
                    </div>
                    <div class="table-responsive mt-2">
                        @include('stock.precio.lista_ferli.partials.tabla_datos', [
                            'filas' => $filas,
                            'listas' => $listas,
                            'puede_ver_articulo' => $puede_ver_articulo ?? false,
                        ])
                    </div>
                    @if ($filas->total() > 0)
                        <div class="mt-2">
                            Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'lista-ferli-overlay',
    'tituloId' => 'lista-ferli-overlay-titulo',
    'subtituloId' => 'lista-ferli-overlay-subtitulo',
    'titulo' => 'Armando la lista…',
    'subtitulo' => 'Puede demorar según la cantidad de artículos.',
])
@include('includes.stock.modalconsultalistaprecio')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/stock/listaprecio/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/stock/listaprecio/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/precio/lista-ferli.js') }}?v={{ filemtime(public_path('assets/pages/scripts/stock/precio/lista-ferli.js')) }}"></script>
@endsection
