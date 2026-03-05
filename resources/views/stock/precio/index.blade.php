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
        url: '/anitaERP/public/stock/precio/limpiafiltro',
		data: data,
        success: function(response){
			window.location.replace(window.location.pathname);
        }
    });
}
</script>

@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<meta name="csrf-token" content="{{ csrf_token() }}" />
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Precios</h3>
                <div class="card-tools">
					@if (session()->get('filtrosPrecios') == '')
						<a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm" id='btn_advanced_filter' data-url-parameter='' 
							title='Filtros y b£squedas avanzadas' class="btn btn-sm btn-default ">
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
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Articulo</th>
                            <th>Lista de precios</th>
                            <th>Fecha vigencia</th>
                            <th>Moneda</th>
                            <th>Precio</th>
                            <th>Precio anterior</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($datas as $precio)
    						<tr data-entry-id="{{ $precio->id }}">
        						<td>
            						{{ $precio->id ?? '' }}
        						</td>
        						<td>
            						{{ $precio->articulos->sku ?? '' }} {{ $precio->articulos->descripcion ?? '' }}
        						</td>
        						<td>
            						{{ $precio->listaprecios->nombre ?? '' }}
        						</td>
        						<td>
            						{{date("d/m/Y", strtotime($precio->fechavigencia ?? ''))}} 
        						</td>
        						<td>
            						{{ $precio->monedas->nombre ?? '' }}
        						</td>
        						<td style="text-align: right">
            						{{ number_format($precio->precio, 2) }}
        						</td>
        						<td style="text-align: right">
            						{{ number_format($precio->precioanterior, 2) }}
        						</td>
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
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('includes.filtroprecio')

@endsection
