@extends("theme.$theme.layout")
@section('titulo')
    Existencias por dep&oacute;sito
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Existencias por dep&oacute;sito y empresa</h3>
                <a href="{{ route('reporte_existencias_deposito') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-eraser"></i> Limpiar
                </a>
            </div>
            <form method="get" action="{{ route('reporte_existencias_deposito') }}" class="mb-0" id="form-existencias-deposito">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Saldo por dep&oacute;sito a la <strong>fecha hasta</strong>.
                        Con fecha hasta de hoy usa <code>articulo_saldo_deposito</code> (igual que movimientos de stock y recuento).
                        Fechas anteriores recalculan desde <code>articulo_movimiento</code>.
                        Solo columnas de dep&oacute;sitos con existencia &ne; 0.
                        El filtro de empresa restringe los <strong>dep&oacute;sitos</strong> (<code>depmae.empresa_id</code>), no el maestro de art&iacute;culos.
                        Un art&iacute;culo se lista si tiene saldo en al menos un dep&oacute;sito visible (no se netea entre dep&oacute;sitos).
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                    @endphp

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => false,
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                    ])

                    <div class="form-group row">
                        <label for="depositos_filtro" class="{{ $colLabel }}">Dep&oacute;sitos</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="depositos_filtro" id="depositos_filtro" class="form-control"
                                placeholder="Vac&iacute;o = todos autorizados; 965,100 o 100/200"
                                autocomplete="off"
                                value="{{ $filtros['depositos_filtro'] ?? '' }}">
                            <small class="form-text text-muted">
                                C&oacute;digo de dep&oacute;sito. Lista: <strong>965,100,105</strong>.
                                Rango: <strong>100/200</strong> (barra). Solo dep&oacute;sitos autorizados y de la empresa elegida.
                            </small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }}">Desde fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}">
                            <small class="form-text text-muted">Movimientos en el per&iacute;odo (default: primer movimiento).</small>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }}">Hasta fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                            <small class="form-text text-muted">Existencias calculadas a esta fecha (default: hoy).</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="desdearticulo_id" class="{{ $colLabel }}">Desde art&iacute;culo</label>
                        <div class="{{ $colInput }}">
                            <select name="desdearticulo_id" id="desdearticulo_id" class="form-control">
                                @foreach ($articulo_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['desdearticulo_id'] ?? \App\Support\Stock\ExistenciasDepositoListadoFiltros::ID_PRIMERO) === (int) $item->id)>
                                        @if (! empty($item->sku))
                                            {{ $item->sku }} —
                                        @endif
                                        {{ $item->descripcion }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="hastaarticulo_id" class="{{ $colLabel }}">Hasta art&iacute;culo</label>
                        <div class="{{ $colInput }}">
                            <select name="hastaarticulo_id" id="hastaarticulo_id" class="form-control">
                                @foreach ($articulo_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['hastaarticulo_id'] ?? \App\Support\Stock\ExistenciasDepositoListadoFiltros::ID_ULTIMO) === (int) $item->id)>
                                        @if (! empty($item->sku))
                                            {{ $item->sku }} —
                                        @endif
                                        {{ $item->descripcion }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="desdecategoria_id" class="{{ $colLabel }}">Desde categor&iacute;a</label>
                        <div class="{{ $colInput }}">
                            <select name="desdecategoria_id" id="desdecategoria_id" class="form-control">
                                @foreach ($categoria_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['desdecategoria_id'] ?? \App\Support\Stock\ExistenciasDepositoListadoFiltros::ID_PRIMERO) === (int) $item->id)>
                                        {{ $item->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="hastacategoria_id" class="{{ $colLabel }}">Hasta categor&iacute;a</label>
                        <div class="{{ $colInput }}">
                            <select name="hastacategoria_id" id="hastacategoria_id" class="form-control">
                                @foreach ($categoria_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['hastacategoria_id'] ?? \App\Support\Stock\ExistenciasDepositoListadoFiltros::ID_ULTIMO) === (int) $item->id)>
                                        {{ $item->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="desdeusoarticulo_id" class="{{ $colLabel }}">Desde uso</label>
                        <div class="{{ $colInput }}">
                            <select name="desdeusoarticulo_id" id="desdeusoarticulo_id" class="form-control">
                                @foreach ($usoarticulo_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['desdeusoarticulo_id'] ?? \App\Support\Stock\ExistenciasDepositoListadoFiltros::ID_PRIMERO) === (int) $item->id)>
                                        {{ $item->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="hastausoarticulo_id" class="{{ $colLabel }}">Hasta uso</label>
                        <div class="{{ $colInput }}">
                            <select name="hastausoarticulo_id" id="hastausoarticulo_id" class="form-control">
                                @foreach ($usoarticulo_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['hastausoarticulo_id'] ?? \App\Support\Stock\ExistenciasDepositoListadoFiltros::ID_ULTIMO) === (int) $item->id)>
                                        {{ $item->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="desdetipoarticulo_id" class="{{ $colLabel }}">Desde tipo</label>
                        <div class="{{ $colInput }}">
                            <select name="desdetipoarticulo_id" id="desdetipoarticulo_id" class="form-control">
                                @foreach ($tipoarticulo_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['desdetipoarticulo_id'] ?? \App\Support\Stock\ExistenciasDepositoListadoFiltros::ID_PRIMERO) === (int) $item->id)>
                                        {{ $item->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="hastatipoarticulo_id" class="{{ $colLabel }}">Hasta tipo</label>
                        <div class="{{ $colInput }}">
                            <select name="hastatipoarticulo_id" id="hastatipoarticulo_id" class="form-control">
                                @foreach ($tipoarticulo_query as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($filtros['hastatipoarticulo_id'] ?? \App\Support\Stock\ExistenciasDepositoListadoFiltros::ID_ULTIMO) === (int) $item->id)>
                                        {{ $item->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="{{ $colLabel }}"></div>
                        <div class="{{ $colInput }}">
                            <input type="hidden" name="solo_con_saldo" value="0">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="solo_con_saldo" id="solo_con_saldo" value="1"
                                    @if ($filtros['solo_con_saldo'] ?? true) checked @endif>
                                <label class="form-check-label" for="solo_con_saldo">
                                    Solo art&iacute;culos con saldo distinto de cero
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
                    @if ($totales)
                        <div class="mb-2">
                            <span class="badge badge-info mr-1">Art&iacute;culos: {{ $totales['total_articulos'] ?? 0 }}</span>
                            <span class="badge badge-secondary mr-1">Dep&oacute;sitos: {{ ($depositos ?? collect())->count() }}</span>
                            <span class="badge badge-success">
                                Total general: {{ \App\Support\Stock\ArticuloSaldosDepositoSupport::formatSaldo((float) ($totales['total_general'] ?? 0)) }}
                            </span>
                        </div>
                    @endif

                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_reporte_existencias_deposito',
                        'queryparams' => $filtrosQuery ?? [],
                    ])

                    @php
                        $filasVista = $filas;
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                            $filas instanceof \Illuminate\Pagination\LengthAwarePaginator
                                ? $filas->getCollection()
                                : collect($filas ?? [])
                        );
                    @endphp
                    @if (! empty($logosVista))
                        <div class="mb-2 d-flex align-items-center flex-wrap">
                            @foreach ($logosVista as $logo)
                                @if (! empty($logo['url']))
                                    <img src="{{ $logo['url'] }}" alt="" style="max-height:42px;margin-right:8px;">
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <div class="table-responsive">
                        @include('stock.existencias_deposito_reporte.partials.tabla_datos', [
                            'depositos' => $depositos,
                            'filas' => $filas,
                            'totales' => $totales,
                            'puede_ver_articulo' => $puede_ver_articulo ?? false,
                        ])
                    </div>

                    @if ($filas instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-2">
                            Mostrando {{ $filas->firstItem() }}&ndash;{{ $filas->lastItem() }} de {{ $filas->total() }}
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
