@extends("theme.$theme.layout")
@section('titulo')
	Precios
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/precio/filtro.js")}}" type="text/javascript"></script>

<script>
function limpiaFiltros(){
	$('#estado').val('');

    var token = $("meta[name='csrf-token']").attr("content");
    var data = "_token="+token;

    $.ajax({
        type: "POST",
        url: '{{ route('precio.limpiafiltro') }}',
		data: data,
        success: function(response){
			window.location.replace(window.location.pathname);
        }
    });
}
</script>

@endsection

@section('contenido')
<meta name="csrf-token" content="{{ csrf_token() }}" />
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Precios</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <form method="get" action="{{ route('precio') }}" class="form-inline mr-2 mb-1 mb-sm-0">
                        <label class="mr-1 mb-0 small text-white-50" for="fecha_vigencia">Vigentes al</label>
                        <input type="date" id="fecha_vigencia" name="fecha_vigencia" value="{{ $fechaVigenciaFiltro }}" class="form-control form-control-sm mr-2" title="Fecha de vigencia de referencia">
                        <label class="mr-1 mb-0 small text-white-50" for="listaprecio_id">Lista</label>
                        <select id="listaprecio_id" name="listaprecio_id" class="form-control form-control-sm mr-2" title="Lista de precios">
                            <option value="">Todas las listas</option>
                            @foreach($listasPrecio as $lista)
                                <option value="{{ $lista->id }}" {{ $listaprecioIdFiltro !== null && (int) $listaprecioIdFiltro === (int) $lista->id ? 'selected' : '' }}>{{ $lista->nombre }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="busqueda" class="form-control form-control-sm mr-2" placeholder="Búsqueda …" value="{{ $busqueda ?? '' }}" title="SKU, artículo, lista o moneda">
                        @if(!empty($filtrosParaVista['filter_column']) && is_array($filtrosParaVista['filter_column']))
                            @foreach($filtrosParaVista['filter_column'] as $i => $fc)
                                @if(is_array($fc))
                                    @foreach($fc as $k => $v)
                                        @if(is_array($v))
                                            @foreach($v as $j => $vv)
                                                <input type="hidden" name="filter_column[{{ $i }}][{{ $k }}][{{ $j }}]" value="{{ $vv }}">
                                            @endforeach
                                        @else
                                            <input type="hidden" name="filter_column[{{ $i }}][{{ $k }}]" value="{{ $v }}">
                                        @endif
                                    @endforeach
                                @endif
                            @endforeach
                        @endif
                        @if(!empty($filtrosParaVista['lasturl']))
                            <input type="hidden" name="lasturl" value="{{ $filtrosParaVista['lasturl'] }}">
                        @endif
                        <button type="submit" class="btn btn-light btn-sm"><i class="fa fa-search"></i></button>
                    </form>
					@if (session()->get('filtrosPrecios') == '')
						<a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm" id='btn_advanced_filter' data-url-parameter='' 
							title='Filtros y búsquedas avanzadas' class="btn btn-sm btn-default ">
								<i class="fa fa-filter"></i> Filtros
						</a>
					@endif
					@if (session()->get('filtrosPrecios') != '') 
                    	<span id="container-button-state">
                            <button class="btn btn-outline-secondary btn-sm" style="color:white" onclick="limpiaFiltros()">Limpiar filtros</button>
                    	</span>
					@endif
                    <a href="{{route('crear_importacion_precio')}}" class="btn btn-outline-secondary btn-sm">
						@if (can('crear-precios', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Sube precios de excel
						@endif
                    </a>
					<a href="{{route('crear_precio')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-precios', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @php
                    $exportQueryParams = array_filter([
                        'fecha_vigencia' => $fechaVigenciaFiltro,
                        'listaprecio_id' => $listaprecioIdFiltro,
                        'busqueda' => $busqueda ?? '',
                    ], fn ($v) => $v !== null && $v !== '');
                @endphp
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_precio',
                    'queryparams' => $exportQueryParams,
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>SKU</th>
                            <th>Descripción</th>
                            <th>Lista de precios</th>
                            <th>Fecha vigencia</th>
                            <th>Moneda</th>
                            <th class="text-right">Precio</th>
                            <th class="text-right">Precio anterior</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@forelse($datas as $precio)
    						<tr data-entry-id="{{ $precio->id }}">
        						<td>{{ $precio->id }}</td>
        						<td><small>{{ $precio->sku }}</small></td>
        						<td><small>{{ $precio->articulo_descripcion }}</small></td>
        						<td><small>{{ $precio->listaprecio_nombre }}</small></td>
        						<td><small>{{ $precio->fechavigencia ? date('d/m/Y', strtotime($precio->fechavigencia)) : '' }}</small></td>
        						<td><small>{{ $precio->moneda_nombre }}</small></td>
        						<td class="text-right"><small>{{ number_format((float) $precio->precio, 2, ',', '.') }}</small></td>
        						<td class="text-right"><small>{{ number_format((float) $precio->precioanterior, 2, ',', '.') }}</small></td>
        						<td>
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
                            <td colspan="9" class="text-center text-muted">No hay precios para los filtros seleccionados.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($datas, 'links'))
            <div class="card-footer">
                {{ $datas->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

@include('includes.filtroprecio')

@endsection
