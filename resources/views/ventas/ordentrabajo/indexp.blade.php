@extends("theme.$theme.layout")
@section('titulo')
	Ordenes de trabajo
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/ordentrabajo/imprimeOt.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/ordentrabajo/filtro.js")}}" type="text/javascript"></script>

<script>
$(function () {
    $(document).on('click', '.borraot', borraOt);
});

function limpiaFiltros(){
	$('#estado').val('');

    var token = $("meta[name='csrf-token']").attr("content");
    var data = "_token="+token;

    $.ajax({
        type: "POST",
        url: '/anitaERP/public/ventas/ordenestrabajo/limpiafiltro',
		data: data,
        success: function(response){
			window.location.replace(window.location.pathname);
        }
    });
}

function borraOt(){
    let ordentrabajo_id = $(this).parents('tr').find('.codigo').html();
    let ptr = this;

    let texto = 'Esta por borrar la OT '+ordentrabajo_id;
    resultado = confirm (texto);
    
    if (resultado)
    {
        var token = $("meta[name='csrf-token']").attr("content");

        $.post("/anitaERP/public/ventas/ordenestrabajo/borrarOt",
            {
                ordentrabajo_id: ordentrabajo_id,
                _token: token
            },
            function(data, status){
                if (data.mensaje != 'ok')
                    alert(data.mensaje);
                else
                {
                    alert("Orden de trabajo Número: " + ordentrabajo_id + "\nBorrada: " + data.mensaje);
                    $(ptr).parents('tr').remove();
                }
            });        
    }
}
</script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<meta name="csrf-token" content="{{ csrf_token() }}" />
<input type="hidden" id="csrf_token" value="{{ csrf_token() }}" />
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Ordenes de trabajo</h3>
                <div class="card-tools">
                    @if (session()->get('filtrosOrdentrabajo') == '')
						<a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm" id='btn_advanced_filter' data-url-parameter='' 
							title='Filtros y b£squedas avanzadas' class="btn btn-sm btn-default ">
								<i class="fa fa-filter"></i> Filtros
						</a>
					@endif
					@if (session()->get('filtrosOrdentrabajo') != '') 
                    	<span id="container-button-state">
                            <button class="btn btn-outline-secondary btn-sm" style="color:white" onclick="limpiaFiltros()">Limpiar filtros</button>
                    	</span>
					@endif
                    <a href="{{route('crear_ordentrabajo')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-ordenes-de-trabajo', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('ordentrabajo') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_ordentrabajo', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">Nro.OT</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Articulo</th>
                            <th>Combinacion</th>
                            <th>Pares</th>
                            <th class="width30">Estado</th>
                            <th class="width40"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ordentrabajo as $data)
                        <tr>
                            <td class="codigo" >{{str_pad($data->codigo, 4, "0", STR_PAD_LEFT)}}</td>
            				<td class="fecha" >{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>
                                @php
									$clientes = [];
								@endphp
								@if (isset($data->ordentrabajo_combinacion_talles))
									@foreach ($data->ordentrabajo_combinacion_talles as $item)
										@php
                                            if (!in_array($item->clientes->nombre, $clientes))
                                                $clientes[] = $item->clientes->nombre;
										@endphp
            						@endforeach
            					@endif
            					{{ count($clientes) > 1 ? "BOLETAS JUNTAS" : $clientes[0] ?? '' }}
                            </td>
                            <td>{{$data->ordentrabajo_combinacion_talles[0]->pedido_combinacion_talles->pedidos_combinacion->articulos->descripcion ?? ''}}</td>
                            <td>{{$data->ordentrabajo_combinacion_talles[0]->pedido_combinacion_talles->pedidos_combinacion->combinaciones->nombre ?? ''}}</td>
        					<td>
								@php
									$pares = 0.;
								@endphp
								@if (isset($data->ordentrabajo_combinacion_talles))
									@foreach ($data->ordentrabajo_combinacion_talles as $item)
										@php
											$pares += $item->pedido_combinacion_talles->cantidad;
										@endphp
            						@endforeach
            					@endif
            					{{ $pares ?? '' }}
        					</td>
                            <td>
                                @php $ultimaTarea = ""; @endphp
                                @foreach ($data->ordentrabajo_tareas as $tarea)
									@php
										$ultimaTarea = $tarea->tareas->nombre;
									@endphp
            					@endforeach
                                {{$ultimaTarea}}
                            </td>
                            <td>
                       			@if (can('editar-ordenes-de-trabajo', false))
                                	<a href="{{route('editar_ordentrabajo', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('listar-ordenes-de-trabajo', false))
                                	<a href="#" onclick="imprimeOt('{{$data->codigo}}')" class="btn-accion-tabla tooltipsC" title="Listar la OT">
                                   	<i class="fa fa-print"></i>
                                	</a>
								@endif
                                @if (can('borrar-ordenes-de-trabajo', false))
                                <button type="submit" class="btn-accion-tabla borraot tooltipsC" title="Eliminar este registro">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
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
{{ $ordentrabajo->appends(['busqueda' => $busqueda])->links() }}
@include('includes.filtroordentrabajo')

@endsection
