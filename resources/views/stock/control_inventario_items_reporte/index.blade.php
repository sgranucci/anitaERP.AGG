@extends("theme.$theme.layout")
@section('titulo')
    Control de inventario de items
@endsection

@section('contenido')
@php
    $F = \App\Support\Stock\ControlInventarioItemsListadoFiltros::class;
    $colLabel = 'col-lg-2 control-label text-right pr-2';
    $colInput = 'col-lg-4';
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Control de inventario de items</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('reporte_control_inventario_items') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('reporte_control_inventario_items') }}" class="mb-0" id="form-control-inventario-items">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Existencia inicial al <strong>día anterior</strong> del rango.
                        En el período: recepciones de proveedores (RCING), devoluciones (RCDEV), ajustes (recuento y manuales) y consumos (AJCON, viandas, etc.).
                        La columna <strong>Otros</strong> acumula transferencias, préstamos y tipos no clasificados, para que la ecuación cierre:
                        inicial + recepciones + devoluciones + ajustes + consumos + otros = existencia final.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => false,
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                    ])

                    @include('stock.partials.campo_consulta_deposito', [
                        'prefix' => 'ctrl_inv_desde',
                        'layout' => 'form_row',
                        'label' => 'Desde depósito',
                        'inputName' => 'desde_deposito_id',
                        'codigoName' => 'desde_deposito',
                        'depositoId' => '',
                        'codigo' => $filtros['desde_deposito'] ?? '',
                        'descripcion' => $descripcion_desde_deposito ?? '',
                        'required' => false,
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                        'ayuda_tooltip' => 'Vacío = desde el primero. Lupa o F1 abre el modal de depósitos.',
                    ])
                    @include('stock.partials.campo_consulta_deposito', [
                        'prefix' => 'ctrl_inv_hasta',
                        'layout' => 'form_row',
                        'label' => 'Hasta depósito',
                        'inputName' => 'hasta_deposito_id',
                        'codigoName' => 'hasta_deposito',
                        'depositoId' => '',
                        'codigo' => $filtros['hasta_deposito'] ?? '',
                        'descripcion' => $descripcion_hasta_deposito ?? '',
                        'required' => false,
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                        'ayuda_tooltip' => 'Vacío = hasta el último. Solo depósitos autorizados de la empresa elegida.',
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }}">Desde fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }}">Hasta fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="desdearticulo_id" class="{{ $colLabel }}">Desde artículo</label>
                        <div class="{{ $colInput }}">
                            <select name="desdearticulo_id" id="desdearticulo_id" class="form-control">
                                @foreach ($articulo_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['desdearticulo_id'] ?? $F::ID_PRIMERO) === (int) $item->id)>
                                        @if (! empty($item->sku)){{ $item->sku }} — @endif{{ $item->descripcion }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="hastaarticulo_id" class="{{ $colLabel }}">Hasta artículo</label>
                        <div class="{{ $colInput }}">
                            <select name="hastaarticulo_id" id="hastaarticulo_id" class="form-control">
                                @foreach ($articulo_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['hastaarticulo_id'] ?? $F::ID_ULTIMO) === (int) $item->id)>
                                        @if (! empty($item->sku)){{ $item->sku }} — @endif{{ $item->descripcion }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="desdecategoria_id" class="{{ $colLabel }}">Desde categoría</label>
                        <div class="{{ $colInput }}">
                            <select name="desdecategoria_id" id="desdecategoria_id" class="form-control">
                                @foreach ($categoria_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['desdecategoria_id'] ?? $F::ID_PRIMERO) === (int) $item->id)>{{ $item->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="hastacategoria_id" class="{{ $colLabel }}">Hasta categoría</label>
                        <div class="{{ $colInput }}">
                            <select name="hastacategoria_id" id="hastacategoria_id" class="form-control">
                                @foreach ($categoria_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['hastacategoria_id'] ?? $F::ID_ULTIMO) === (int) $item->id)>{{ $item->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="desdesubcategoria_id" class="{{ $colLabel }}">Desde subcategoría</label>
                        <div class="{{ $colInput }}">
                            <select name="desdesubcategoria_id" id="desdesubcategoria_id" class="form-control">
                                @foreach ($subcategoria_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['desdesubcategoria_id'] ?? $F::ID_PRIMERO) === (int) $item->id)>{{ $item->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="hastasubcategoria_id" class="{{ $colLabel }}">Hasta subcategoría</label>
                        <div class="{{ $colInput }}">
                            <select name="hastasubcategoria_id" id="hastasubcategoria_id" class="form-control">
                                @foreach ($subcategoria_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['hastasubcategoria_id'] ?? $F::ID_ULTIMO) === (int) $item->id)>{{ $item->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="desdeusoarticulo_id" class="{{ $colLabel }}">Desde uso</label>
                        <div class="{{ $colInput }}">
                            <select name="desdeusoarticulo_id" id="desdeusoarticulo_id" class="form-control">
                                @foreach ($usoarticulo_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['desdeusoarticulo_id'] ?? $F::ID_PRIMERO) === (int) $item->id)>{{ $item->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="hastausoarticulo_id" class="{{ $colLabel }}">Hasta uso</label>
                        <div class="{{ $colInput }}">
                            <select name="hastausoarticulo_id" id="hastausoarticulo_id" class="form-control">
                                @foreach ($usoarticulo_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['hastausoarticulo_id'] ?? $F::ID_ULTIMO) === (int) $item->id)>{{ $item->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="desdetipoarticulo_id" class="{{ $colLabel }}">Desde tipo</label>
                        <div class="{{ $colInput }}">
                            <select name="desdetipoarticulo_id" id="desdetipoarticulo_id" class="form-control">
                                @foreach ($tipoarticulo_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['desdetipoarticulo_id'] ?? $F::ID_PRIMERO) === (int) $item->id)>{{ $item->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="hastatipoarticulo_id" class="{{ $colLabel }}">Hasta tipo</label>
                        <div class="{{ $colInput }}">
                            <select name="hastatipoarticulo_id" id="hastatipoarticulo_id" class="form-control">
                                @foreach ($tipoarticulo_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['hastatipoarticulo_id'] ?? $F::ID_ULTIMO) === (int) $item->id)>{{ $item->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="{{ $colLabel }}"></div>
                        <div class="{{ $colInput }}">
                            <input type="hidden" name="solo_con_movimiento" value="0">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="solo_con_movimiento" id="solo_con_movimiento" value="1"
                                    @if ($filtros['solo_con_movimiento'] ?? true) checked @endif>
                                <label class="form-check-label" for="solo_con_movimiento">
                                    Solo artículos con movimiento en el período
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="{{ $colLabel }}"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado)
                <div class="card-body pt-0">
                    @foreach ($avisos ?? [] as $aviso)
                        <div class="alert alert-warning py-2">{{ $aviso }}</div>
                    @endforeach

                    @if ($totales)
                        <div class="mb-2 small text-muted">
                            Artículos: {{ $totales['total_articulos'] ?? 0 }}
                            @if (($totales['filas_no_cierran'] ?? 0) > 0)
                                · <span class="text-danger">No cierran: {{ $totales['filas_no_cierran'] }}</span>
                            @endif
                        </div>
                    @endif

                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_reporte_control_inventario_items',
                        'queryparams' => $filtrosQuery ?? [],
                    ])

                    <div class="table-responsive">
                        <table id="tabla-paginada" class="table table-bordered table-hover table-sm mb-0" style="font-size: 0.8rem;">
                            @include('stock.control_inventario_items_reporte.partials.tabla_datos', [
                                'filas' => $filas,
                                'totales' => $totales,
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                                'solo_thead_tbody' => true,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-2">{{ $filas->withQueryString()->links() }}</div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.stock.modalconsultadeposito')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/stock/depmae/consulta.js')) }}"></script>
@endsection
