@extends("theme.$theme.layout")

@section('titulo')
    Recetas
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $vista = $filtros['vista'] ?? 'recetas';
    $queryBase = $filtrosQuery ?? [];
    $linkVista = function (string $nombre) use ($queryBase) {
        $params = $queryBase;
        $params['vista'] = $nombre;
        unset($params['page']);

        return route('gastronomia_recetas_reporte', $params);
    };
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Recetas</h3>
                <div class="card-tools">
                    <a href="{{ route('gastronomia_recetas_reporte') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('gastronomia_recetas_reporte') }}" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Costo por última compra de cada insumo y de las subrecetas.
                        El precio es el vigente del artículo de venta en la lista {{ (int) config('precio.listaprecio_default_id', 1) }}.
                        La receta entra si el artículo de venta asignado está activo o inactivo, según lo que marques.
                        Las que no tienen artículo de venta salen solo con el tercer criterio, también si filtrás por empresa.
                        En los opcionales, el costo toma el primero de cada grupo; el resto se lista y no suma.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => (int) ($filtros['empresa_id'] ?? 0) > 0 ? (int) $filtros['empresa_id'] : '',
                        'required' => false,
                        'permite_vacio' => true,
                        'opcion_vacia' => 'Todas',
                        'col_label' => 'col-lg-2 control-label text-right pr-2',
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row">
                        <label for="categoria_id" class="col-lg-2 control-label text-right pr-2">Categoría</label>
                        <div class="col-lg-4">
                            <select name="categoria_id" id="categoria_id" class="form-control">
                                <option value="0">Todas</option>
                                @foreach ($categorias as $categoria)
                                    <option value="{{ $categoria->id }}" @selected((int) ($filtros['categoria_id'] ?? 0) === (int) $categoria->id)>{{ $categoria->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="subcategoria_id" class="col-lg-2 control-label text-right pr-2">Subcategoría</label>
                        <div class="col-lg-4">
                            <select name="subcategoria_id" id="subcategoria_id" class="form-control">
                                <option value="0">Todas</option>
                                @foreach ($subcategorias as $subcategoria)
                                    <option value="{{ $subcategoria->id }}" @selected((int) ($filtros['subcategoria_id'] ?? 0) === (int) $subcategoria->id)>{{ $subcategoria->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2">Incluir</label>
                        <div class="col-lg-10 pt-1">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="activos" id="activos" value="1" @checked(! empty($filtros['activos']))>
                                <label class="form-check-label" for="activos">Artículos de venta activos</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="inactivos" id="inactivos" value="1" @checked(! empty($filtros['inactivos']))>
                                <label class="form-check-label" for="inactivos">Artículos de venta inactivos</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="sin_asignar" id="sin_asignar" value="1" @checked(! empty($filtros['sin_asignar']))>
                                <label class="form-check-label" for="sin_asignar">Recetas no asignadas a artículos</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <input type="hidden" name="vista" value="{{ $vista }}">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado ?? false)
                <div class="card-body p-0 border-top">
                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-0 small">
                            <strong>Empresa:</strong> {{ $empresa_texto ?? 'Todas' }}
                            · <strong>Incluye:</strong> {{ $inclusion_texto ?? '' }}
                            @if (! empty($resultado))
                                · <strong>Recetas:</strong> {{ (int) ($resultado['totales']['recetas'] ?? 0) }}
                                · Alta {{ (int) ($resultado['totales']['alta'] ?? 0) }}
                                · Media {{ (int) ($resultado['totales']['media'] ?? 0) }}
                                · Baja {{ (int) ($resultado['totales']['baja'] ?? 0) }}
                            @endif
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_gastronomia_recetas_reporte',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                        <div class="btn-group btn-group-sm mb-1" role="group">
                            <a href="{{ $linkVista('recetas') }}" class="btn btn-outline-primary @if($vista === 'recetas') active @endif">Recetas</a>
                            <a href="{{ $linkVista('lineas') }}" class="btn btn-outline-primary @if($vista === 'lineas') active @endif">Líneas</a>
                            <a href="{{ $linkVista('resumen') }}" class="btn btn-outline-primary @if($vista === 'resumen') active @endif">Resumen</a>
                            <a href="{{ $linkVista('alertas') }}" class="btn btn-outline-primary @if($vista === 'alertas') active @endif">Alertas</a>
                        </div>
                    </div>

                    <style>
                        .tabla-recetas-reporte { font-size: 1rem; }
                        .tabla-recetas-reporte thead tr { background-color: #85C1E9; color: #17202A; }
                        .tabla-recetas-reporte thead th {
                            font-weight: 600;
                            border-color: #7fb3d5;
                            white-space: nowrap;
                            font-size: 0.95rem;
                            padding: 0.55rem 0.75rem;
                        }
                        .tabla-recetas-reporte td {
                            font-size: 0.95rem;
                            padding: 0.45rem 0.75rem;
                            white-space: nowrap;
                            vertical-align: middle;
                        }
                        .tabla-recetas-reporte td.celda-amplia {
                            white-space: normal;
                            min-width: 22rem;
                            max-width: 36rem;
                        }
                    </style>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover mb-0 tabla-recetas-reporte">
                            @if ($vista === 'lineas')
                                @include('ventas.gastronomia.recetas_reporte.partials.tabla_lineas', ['filas' => $paginador ? $paginador->items() : []])
                            @elseif ($vista === 'resumen')
                                @include('ventas.gastronomia.recetas_reporte.partials.tabla_resumen', ['filas' => $resultado['resumen'] ?? [], 'totales' => $resultado['totales'] ?? []])
                            @elseif ($vista === 'alertas')
                                @include('ventas.gastronomia.recetas_reporte.partials.tabla_alertas', ['filas' => $paginador ? $paginador->items() : []])
                            @else
                                @include('ventas.gastronomia.recetas_reporte.partials.tabla_recetas', [
                                    'filas' => $paginador ? $paginador->items() : [],
                                    'puede_editar_formula' => $puede_editar_formula ?? false,
                                ])
                            @endif
                        </table>
                    </div>

                    @if ($paginador instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $paginador->total() > 0)
                        <div class="card-footer py-2">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <small class="text-muted">
                                    {{ $paginador->firstItem() }}–{{ $paginador->lastItem() }} de {{ $paginador->total() }}
                                </small>
                                {{ $paginador->links() }}
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
