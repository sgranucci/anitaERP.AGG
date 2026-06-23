@extends("theme.$theme.layout")
@section('titulo')
	Precios
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/precio/filtro.js")}}" type="text/javascript"></script>
<style>
    .precio-index-toolbar {
        flex-wrap: nowrap;
        gap: 0.25rem;
        max-width: 100%;
    }
    .precio-index-toolbar .precio-toolbar-label {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.75);
        white-space: nowrap;
        margin: 0;
    }
    .precio-index-toolbar .precio-toolbar-fecha {
        width: 6.25rem;
        min-width: 6.25rem;
        padding-left: 0.25rem;
        padding-right: 0.25rem;
        font-size: 0.8rem;
    }
    .precio-index-toolbar .precio-toolbar-lista {
        width: auto;
        min-width: 6.5rem;
        max-width: 11rem;
    }
    .precio-index-toolbar #filtro_valor {
        width: 8.5rem;
        max-width: 8.5rem;
    }
    .precio-index-toolbar .btn,
    .precio-index-toolbar .form-control {
        vertical-align: middle;
    }
    @media (max-width: 1400px) {
        .precio-index-toolbar {
            flex-wrap: wrap;
            justify-content: flex-end;
        }
    }
    #tabla-paginada .col-fecha-vigencia {
        width: 5rem;
        max-width: 5rem;
        white-space: nowrap;
        padding-left: 0.35rem;
        padding-right: 0.35rem;
        font-size: 0.8rem;
    }
    #tabla-paginada th.col-acciones-precio,
    #tabla-paginada td.col-acciones-precio {
        width: 4.5rem;
        min-width: 4.5rem;
        white-space: nowrap;
        text-align: center;
        padding-left: 0.25rem;
        padding-right: 0.25rem;
    }
</style>
@endsection

@php
    use App\Support\Stock\PrecioListadoFiltros;
    $fechaVigenciaFiltro = $filtros['fecha_vigencia'] ?? date('Y-m-d');
    $listaprecioIdFiltro = $filtros['listaprecio_id'] ?? null;
    $ocultarPrecioCero = (bool) ($filtros['ocultar_precio_cero'] ?? true);
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title mb-0">Precios de venta</h3>
                <div class="card-tools">
                    @php
                        $queryMostrarCero = array_merge($filtrosQuery ?? [], ['ocultar_precio_cero' => 0]);
                        $queryOcultarCero = array_merge($filtrosQuery ?? [], ['ocultar_precio_cero' => 1]);
                    @endphp
                    <div class="d-flex align-items-center justify-content-end precio-index-toolbar">
                        <label class="precio-toolbar-label mr-1" for="fecha_vigencia_toolbar">Vig. al</label>
                        <input type="date" id="fecha_vigencia_toolbar" name="fecha_vigencia" form="form-filtros-precio"
                            value="{{ $fechaVigenciaFiltro }}" class="form-control form-control-sm precio-toolbar-fecha"
                            title="Fecha de vigencia de referencia">
                        <label class="precio-toolbar-label ml-1 mr-1" for="listaprecio_id_toolbar">Lista</label>
                        <select id="listaprecio_id_toolbar" name="listaprecio_id" form="form-filtros-precio"
                            class="form-control form-control-sm precio-toolbar-lista" title="Lista de precios">
                            <option value="">Todas</option>
                            @foreach($listasPrecio as $lista)
                                <option value="{{ $lista->id }}"
                                    {{ $listaprecioIdFiltro !== null && (int) $listaprecioIdFiltro === (int) $lista->id ? 'selected' : '' }}>
                                    {{ $lista->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @include('includes.listado.filtros_toolbar', [
                            'formId' => 'form-filtros-precio',
                            'filtroValor' => $filtros['valor'] ?? '',
                            'tieneCriterios' => PrecioListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                            'limpiarUrl' => route('precio'),
                            'placeholder' => 'Búsqueda …',
                            'toggleTarget' => '#panel-filtros-precio',
                            'toggleId' => 'btn-toggle-filtros-precio',
                            'inputId' => 'filtro_valor',
                            'nuevoRegistroUrl' => route('crear_precio'),
                            'nuevoRegistroCan' => 'crear-precios',
                            'nuevoRegistroLabel' => 'Nuevo',
                        ])
                        @if ($ocultarPrecioCero)
                            <a href="{{ route('precio', $queryMostrarCero) }}" class="btn btn-warning btn-sm" title="Mostrar también registros con precio 0">
                                <i class="fa fa-filter"></i> Sin $0
                            </a>
                        @else
                            <a href="{{ route('precio', $queryOcultarCero) }}" class="btn btn-outline-light btn-sm" title="Ocultar registros con precio 0">
                                <i class="fa fa-filter"></i> + $0
                            </a>
                        @endif
                        @if (can('actualizar-precios', false))
                            <a href="{{ route('precio_actualizar_categoria', ['fecha_referencia' => $fechaVigenciaFiltro]) }}" class="btn btn-outline-warning btn-sm" title="Actualizar precios por categoría">
                                <i class="fa fa-percent"></i>
                            </a>
                        @endif
                        @if (can('crear-precios', false))
                            <a href="{{ route('crear_importacion_precio') }}" class="btn btn-outline-secondary btn-sm" title="Importar Excel">
                                <i class="fa fa-upload"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
            <form method="get" action="{{ route('precio') }}" id="form-filtros-precio" class="mb-0">
                <input type="hidden" name="ocultar_precio_cero" value="{{ $ocultarPrecioCero ? 1 : 0 }}">
                @include('stock.precio.partials.filtros_listado', [
                    'limpiarUrl' => route('precio'),
                ])
            </form>
            <div class="card-body p-2">
                <div class="alert alert-light border small mb-0 py-2">
                    Solo se administran precios de <strong>artículos facturables</strong>.
                    @if ($ocultarPrecioCero)
                        Por defecto se ocultan los registros con <strong>precio en 0</strong> (use el botón del encabezado para incluirlos).
                    @else
                        Se están mostrando también los registros con <strong>precio en 0</strong>.
                    @endif
                    Use <strong>Filtros</strong> para criterios avanzados por campo (opcional).
                </div>
            </div>
            <div class="card-body table-responsive p-0 border-top-0 pt-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_precio',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color: #85C1E9; color: #17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>SKU</th>
                            <th>Descripción</th>
                            <th>Categoría</th>
                            <th>Lista</th>
                            <th class="col-fecha-vigencia">Vigencia</th>
                            <th>Moneda</th>
                            <th class="text-right">Precio</th>
                            <th class="text-right">Prec. ant.</th>
                            <th class="col-acciones-precio" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@forelse($datas as $precio)
    						<tr data-entry-id="{{ $precio->id }}">
        						<td>{{ $precio->id }}</td>
        						<td><small>{{ $precio->sku }}</small></td>
        						<td><small>{{ $precio->articulo_descripcion }}</small></td>
        						<td><small>{{ $precio->categoria_nombre }}</small></td>
        						<td><small>{{ $precio->listaprecio_nombre }}</small></td>
        						<td class="col-fecha-vigencia"><small>{{ $precio->fechavigencia ? date('d/m/Y', strtotime($precio->fechavigencia)) : '' }}</small></td>
        						<td><small>{{ $precio->moneda_nombre }}</small></td>
        						<td class="text-right"><small>{{ number_format((float) $precio->precio, 2, ',', '.') }}</small></td>
        						<td class="text-right"><small>{{ number_format((float) $precio->precioanterior, 2, ',', '.') }}</small></td>
        						<td class="col-acciones-precio">
                       			@if (can('editar-precios', false))
                                	<a href="{{route('editar_precio', ['id' => $precio->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-precios', false))
                                	<form action="{{route('eliminar_precio', ['id' => $precio->id])}}" class="d-inline form-eliminar" method="POST">
                                   		@csrf @method("delete")
                                   		<button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                       	<i class="fa fa-times-circle text-danger"></i>
                                   	</button>
                                	</form>
								@endif
                            	</td>
                        	</tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted">No hay precios para los filtros seleccionados.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($datas, 'links'))
            <div class="card-footer">
                {{ $datas->appends($filtrosQuery ?? [])->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
