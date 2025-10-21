@extends("theme.$theme.layout")
@section('titulo')
Pedidos de Clientes
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/pedido/filtro.js")}}" type="text/javascript"></script>

<script>

function limpiaFiltros(){
	$('#estado').val('');

    var token = $("meta[name='csrf-token']").attr("content");
    var data = "_token="+token;

    $.ajax({
        type: "POST",
        url: '/anitaERP/public/ventas/pedido/limpiafiltro',
		data: data,
        success: function(response){
			window.location.replace(window.location.pathname);
        }
    });
}

function eliminarPedido(event) {
  var opcion = confirm("Desea borrar el pedido?");
  if(!opcion) {
    event.preventDefault();
  }
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
                <h3 class="card-title">Pedidos de clientes</h3>
                <div class="card-tools">
					@if (session()->get('filtrosPedidos') == '')
						<a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm" id='btn_advanced_filter' data-url-parameter='' 
							title='Filtros y búsquedas avanzadas' class="btn btn-sm btn-default ">
								<i class="fa fa-filter"></i> Filtros
						</a>
					@endif
					@if (session()->get('filtrosPedidos') != '') 
                    	<span id="container-button-state">
                            <button class="btn btn-outline-secondary btn-sm" style="color:white" onclick="limpiaFiltros()">Limpiar filtros</button>
                    	</span>
					@endif
                    <a href="{{route('cerrar_pedido')}}" class="btn btn-danger btn-sm">
                       	@if (can('cierre-de-pedidos', false))
                        	<i class="fa fa-fw fa-times-circle"></i> Cierre de pedidos
						@endif
                    </a>
                    <a href="{{route('crear_pedido')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-pedidos', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data-2">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Fecha</th>
                            <th class="width50">Cliente</th>
                            <th>Marca</th>
                            <th>Pares</th>
							<th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($datas as $pedido)
    						<tr data-entry-id="{{ $pedido['id'] }}">
        						<td>
            						{{ $pedido['id'] ?? '' }}
        						</td>
        						<td>
            						{{date("Y/m/d", strtotime($pedido['fecha'] ?? ''))}} 
        						</td>
        						<td>
            						<b>{{ $pedido['nombrecliente'] ?? '' }}</b>
        						</td>
        						<td>
            						{{ $pedido['nombremarca'] ?? '' }}
        						</td>
        						<td>
									{{ $pedido['pares'] }}
								</td>
								<td>
									{{ $pedido['estado'] }}
								</td>
        						<td>
                       			@if (can('editar-pedidos', false))
                                	<a href="{{route('editar_pedido', ['id' => $pedido['id']])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-pedidos', false) && $pedido['estado'] == 'Pendiente')
                                	<form action="{{route('eliminar_pedido', ['id' => $pedido['id']])}}" class="d-inline form-eliminar" method="POST">
                                   		@csrf @method("delete")
                                   		<button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                       	<i class="fa fa-times-circle text-danger"></i>
                                   	</button>
                                	</form>
								@endif
                       			@if (can('listar-pedidos', false))
                                	<a href="{{route('listar_pedido', ['id' => $pedido['id']])}}" class="btn-accion-tabla tooltipsC" title="Listar el pedido">
                                   	<i class="fa fa-print"></i>
                                	</a>
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

@include('includes.filtropedido')

@endsection
