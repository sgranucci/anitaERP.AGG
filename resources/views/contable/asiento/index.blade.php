@extends("theme.$theme.layout")
@section('titulo')
    Asientos contables
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>

<script>
    function eliminarAsiento(event) {
        var opcion = confirm("Desea eliminar el asiento?");
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
                <h3 class="card-title">Asientos Contables</h3>
                <div class="card-tools">
                    <a href="{{route('crear_asiento')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-asiento', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('asiento') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_asiento', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Tipo de asiento</th>
                            <th>Observaciones</th>
                            <th>Monto</th>
                            <th>Movimientos</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($asientos as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombreempresa}}</td>
                            <td>{{$data->numeroasiento}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>{{$data->nombretipoasiento}}</td>
                            <td>{{$data->observacion ?? ''}}</td>
                            <td>
                                @php $totalAsiento = 0; @endphp
                                @foreach($data->asiento_movimientos as $movimiento)
                                    @php $totalAsiento += ($movimiento->monto > 0 ? $movimiento->monto : 0); @endphp
                                @endforeach
                                {{number_format($totalAsiento,2)}}
                            </td>
                            <td>
                                <ul>
                                @foreach($data->asiento_movimientos as $movimiento)
                                    <li>{{ $movimiento->cuentacontables->nombre }} {{ $movimiento->monto > 0 ? number_format($movimiento->monto,2) : '' }} {{ $movimiento->monto < 0 ? number_format($movimiento->monto,2) : ''}}</li>
                                @endforeach
                                </ul>
                            </td>
                            <td>
                       			@if (can('editar-asiento', false))
                                	<a href="{{route('editar_asiento', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-asiento', false))
                                <form action="{{route('eliminar_asiento', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" onclick="eliminarAsiento(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
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
{{ $asientos->appends(['busqueda' => $busqueda])->links() }}
@endsection
