@extends("theme.$theme.layout")
@section('titulo')
    Capex
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>

<script>
    function eliminarCapex(event) {
        var opcion = confirm("Desea eliminar el Capex?");
        if(!opcion) {
            event.preventDefault();
        }
    }
</script>

@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Capex</h3>
                <div class="card-tools">
                    <a href="{{route('crear_capex')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-capex', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('consultar_capex') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_capex', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Presupuesto</th>
                            <th>Centro de Costo</th>
                            <th>Nombre</th>
                            <th>Detalle</th>
                            <th>Codigo de Proyecto</th>
                            <th>Nro. de Proyecto</th>
                            <th>Estado</th>
                            <th style="width: 15%;">Partidas</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($capex as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombreempresa ?? ''}}</td>
                            <td>{{$data->nombrepresupuesto ?? ''}}</td>
                            <td>{{$data->nombrecentrocosto ?? '' }}</td>
                            <td>{{$data->nombre ?? ''}}</td>
                            <td>{{$data->detalle ?? ''}}</td>
                            <td>{{$data->codigoproyecto}}</td>
                            <td>{{$data->codigo}}</td>
                            <td>{{$data->estado}}</td>
                            <td>
                                <ul>
                                    @foreach($data->capex_partidas as $partida)

                                        @php $montoTotal = 0; @endphp
                                        @foreach($partida->capex_partida_montos as $monto)
                                            @php $montoTotal += $monto->monto; @endphp
                                        @endforeach

                                        <li>Nro.{{$partida->codigo}} {{$partida->nombre}} {{ $partida->monedas->abreviatura ?? ''}} {{number_format($montoTotal,2)}}</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td>
                       			@if (can('editar-capex', false))
                                	<a href="{{route('editar_capex', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-capex', false))
                                <form action="{{route('eliminar_capex', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $capex->appends(['busqueda' => $busqueda])->links() }}
@endsection
