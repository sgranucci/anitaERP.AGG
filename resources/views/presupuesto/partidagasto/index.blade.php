@extends("theme.$theme.layout")
@section('titulo')
    Partidas de Gastos
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
                <h3 class="card-title">Partidas de Gastos</h3>
                <div class="card-tools">
                    <a href="{{route('crear_partidagasto')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-partida-gasto', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('consultar_partidagasto') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_partidagasto', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Presupuesto</th>
                            <th>Escenario</th>
                            <th>Centro de Costo</th>
                            <th>Partida</th>
                            <th>Detalle</th>
                            <th>Articulo</th>
                            <th>Proveedor</th>
                            <th>Cuenta Contable</th>
                            <th>Moneda</th>
                            <th>Monto Total</th>
                            <th>Estado</th>
                            <th style="width: 15%;">Apertura</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($partidagasto as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombreempresa ?? ''}}</td>
                            <td>{{$data->nombrepresupuesto ?? ''}}</td>
                            <td>{{$data->nombreescenario ?? ''}}</td>
                            <td>{{$data->nombrecentrocosto ?? '' }}</td>
                            <td>{{$data->codigopartida ?? ''}}</td>
                            <td>{{$data->detalle ?? ''}}</td>
                            <td>{{$data->descripcionarticulo ?? ''}}</td>
                            <td>{{$data->nombreproveedor ?? ''}}</td>
                            <td>{{$data->codigocuentacontable}}-{{$data->nombrecuentacontable ?? ''}}</td>
                            <td>{{$data->abreviaturamoneda}}</td>
                            <td style="text-align: left;">
                                @php $montoTotal = 0; @endphp
                                @foreach($data->partidagasto_montos as $partida)
                                    @php $montoTotal += $partida->monto; @endphp
                                @endforeach                                
                                {{number_format($montoTotal,2)}}
                            </td>
                            <td>{{$data->estado}}</td>
                            <td>
                                <ul>
                                    @foreach($data->partidagasto_montos as $partida)
                                        <li>{{$partida->periodo}} {{number_format($partida->monto,2)}}</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td>
                       			@if (can('editar-partida-gasto', false))
                                	<a href="{{route('editar_partidagasto', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-partida-gasto', false))
                                <form action="{{route('eliminar_partidagasto', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $partidagasto->appends(['busqueda' => $busqueda])->links() }}
@endsection

