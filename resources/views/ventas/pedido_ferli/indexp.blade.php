@extends("theme.$theme.layout")
@section('titulo')
Pedidos de Clientes
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/pedido/filtroferli.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/pedido/importar_l8.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/pedido/importar_l8.js')) ?: time() }}" type="text/javascript"></script>

<script>
function limpiaFiltros(){
	$('#estado').val('');

    var token = $("meta[name='csrf-token']").attr("content");
    var data = "_token="+token;

    $.ajax({
        type: "POST",
        url: '{{ route("pedido.limpiafiltro") }}',
		data: data,
        success: function(response){
			window.location.replace(window.location.pathname);
        }
    });
}

function eliminarPedido(event) {
  var opcion = confirm("Desea eliminar el pedido?");
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
                    @if (can('importar-pedido-l8', false))
                        <button type="button" class="btn btn-outline-warning btn-sm" data-toggle="modal" data-target="#modalImportarPedidoL8" title="Importar pedidos que existen en L8 y faltan en L12">
                            <i class="fa fa-download"></i> Importar pedidos L8
                        </button>
                    @endif
                    @if (\App\Support\Ventas\PedidoPickingFerliSupport::habilitado() && can('listar-reporte-picking-pedido', false))
                        <a href="{{ route('picking_pedido', ['consultar' => 1]) }}" class="btn btn-outline-warning btn-sm" title="Workbench picking pendientes de facturar">
                            <i class="fa fa-dolly"></i> Picking / facturar
                        </a>
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
				<div class="d-md-flex justify-content-md-end">
					<form action="{{ route('pedido') }}" method="GET">
						<div class="btn-group">
							<input type="text" name="busqueda" class="form-control" placeholder="Busqueda ..."> 
							<button type="submit" class="btn btn-default">
								<span class="fa fa-search"></span>
							</button>
						</div>
					</form>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
				@include('includes.exportar-tabla', ['ruta' => 'lista_pedido', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Fecha</th>
                            <th class="width50">Cliente</th>
                            <th>Marca</th>
                            <th>Pares</th>
							<th class="width60">Estado</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($pedidos as $pedido)
    						<tr data-entry-id="{{ $pedido['id'] }}">
        						<td>
            						{{ $pedido['id'] ?? '' }}
        						</td>
        						<td>
            						{{date("Y-m-d", strtotime($pedido['fecha'] ?? ''))}} 
        						</td>
        						<td>
            						<b>{{ $pedido['nombrecliente'] ?? '' }}</b>
        						</td>
        						<td>
            						{{ $pedido->pedido_combinaciones[0]->articulos->mventas->nombre ?? '' }}
        						</td>
        						<td>
									@php
										$pares = 0.;
									@endphp
									@foreach($pedido->pedido_combinaciones as $item)
										@php
											$pares += ($item->cantidad);
										@endphp
            						@endforeach
            						{{ $pares ?? '' }}
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
                                   		<button type="submit" onclick="eliminarPedido(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
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
{{ $pedidos->appends(['busqueda' => $busqueda])->links() }}

@if (can('importar-pedido-l8', false))
    @include('ventas.pedido_ferli.partials.modal_importar_l8')
@endif

@endsection
