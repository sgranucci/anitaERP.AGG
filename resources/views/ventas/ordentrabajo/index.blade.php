@extends("theme.$theme.layout")
@section('titulo')
	Ordenes de trabajo
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/ordentrabajo/imprimeOt.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/ordentrabajo/filtro.js")}}" type="text/javascript"></script>

<script>
function limpiaFiltros(){
	$('#estado').val('');

    var token = $("meta[name='csrf-token']").attr("content");
    var data = "_token="+token;

    $.ajax({
        type: "POST",
        url: '{{ url('ventas/ordenestrabajo/limpiafiltro') }}',
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
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data-2">
                    <thead>
                        <tr>
                            <th class="width20">Nro.OT</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Articulo</th>
                            <th>Combinacion</th>
                            <th>Pares</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ordentrabajo_query as $data)
                        <tr>
                            <td>{{str_pad($data->codigo, 4, "0", STR_PAD_LEFT)}}</td>
            				<td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>
                                @php
									$clientes = [];
								@endphp
								@if (isset($data->ordentrabajo_combinacion_talles))
									@foreach ($data->ordentrabajo_combinacion_talles as $item)
										@php
											$nombreCliente = $item->clientes->nombre ?? null;
											if ($nombreCliente !== null && !in_array($nombreCliente, $clientes)) {
												$clientes[] = $nombreCliente;
											}
										@endphp
            						@endforeach
            					@endif
            					{{ count($clientes) > 1 ? "BOLETAS JUNTAS" : $clientes[0] ?? '' }}
                            </td>
                            @php $pedidoCombinacionOt = $data->pedidoCombinacionVigente(); @endphp
                            <td>{{$pedidoCombinacionOt?->articulos->descripcion ?? ''}}</td>
                            <td>{{$pedidoCombinacionOt?->combinaciones->nombre ?? ''}}</td>
        					<td>
								@php
									$pares = 0.;
								@endphp
								@if (isset($data->ordentrabajo_combinacion_talles))
									@foreach ($data->ordentrabajo_combinacion_talles as $item)
										@php
											$pares += $item->pedido_combinacion_talles->cantidad ?? 0;
										@endphp
            						@endforeach
            					@endif
            					{{ $pares ?? '' }}
        					</td>
                            <td>
                                @php $ultimaTarea = ""; @endphp
                                @foreach ($data->ordentrabajo_tareas as $tarea)
									@php
										$ultimaTarea = $tarea->tareas->nombre ?? $ultimaTarea;
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
                       			@if (can('borrar-ordenes-de-trabajo', false))
                                <form action="{{route('eliminar_ordentrabajo', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                       			@if (can('listar-ordenes-de-trabajo', false))
                                	<a href="#" onclick="return imprimeOt('{{$data->codigo}}')" class="btn-accion-tabla tooltipsC" title="Imprimir la OT">
                                   	<i class="fa fa-print"></i>
                                	</a>
                                	<a href="#" onclick="return pdfOt('{{$data->codigo}}')" class="btn-accion-tabla tooltipsC" title="PDF de la OT">
                                   	<i class="fas fa-file-pdf text-danger"></i>
                                	</a>
								@endif
                                <button type="submit" class="btn-accion-tabla borraot tooltipsC" title="Eliminar este registro">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
							</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('includes.filtroordentrabajo')

@endsection
