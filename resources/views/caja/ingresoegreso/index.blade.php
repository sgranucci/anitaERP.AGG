@extends("theme.$theme.layout")
@section('titulo')
    Ingresos y Egresos de Caja
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>

<script>
    function eliminarIngresoEgreso(event) {
        var opcion = confirm("Desea eliminar la transacción de caja?");
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
                <h3 class="card-title">Ingresos y Egresos de Caja</h3>
                <div class="card-tools">
                    <a href="{{route('crear_ingresoegreso')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-ingresos-egresos-caja', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('ingresoegreso') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_ingresoegreso', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Tipo de transacción</th>
                            <th>Concepto</th>
                            <th>Detalle</th>
                            @if (config('app.empresa') == 'Iguassu Travel')
                                <th>Orden de servicio</th>
                            @endif
                            <th>Monto en $</th>
                            <th>Movimientos</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($caja_movimiento as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombreempresa}}</td>
                            <td>{{$data->numerotransaccion}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>{{$data->nombretipotransaccion_caja}}</td>
                            <td>{{$data->nombreconceptogasto ?? ''}}</td>
                            <td>{{$data->detalle ?? ''}}</td>
                            @if (config('app.empresa') == 'Iguassu Travel')
                                <td>{{$data->ordenservicio_id}}</td>
                            @endif
                            <td>
                                @php $totalIngreso = 0; $totalEgreso= 0; @endphp
                                @foreach($data->caja_movimiento_cuentacajas as $movimiento)
                                    @if ($movimiento->moneda_id > 1)
                                        @php $coef = $movimiento->cotizacion; @endphp
                                    @else
                                        @php $coef = 1.; @endphp
                                    @endif
                                    @php 
                                        $totalIngreso += ($movimiento->monto > 0 ? $movimiento->monto * $coef : 0);
                                        $totalEgreso += ($movimiento->monto < 0 ? abs($movimiento->monto * $coef) : 0);
                                    @endphp
                                @endforeach
                                @if ($totalIngreso != 0)
                                    {{number_format($totalIngreso,2)}}
                                @else
                                    {{number_format($totalEgreso,2)}}
                                @endif
                            </td>
                            <td>
                                <ul>
                                @foreach($data->caja_movimiento_cuentacajas as $movimiento)
                                    <li>{{ $movimiento->cuentacajas->nombre }} {{ $movimiento->monto > 0 ? number_format($movimiento->monto,2) : '' }} {{ $movimiento->monto < 0 ? number_format($movimiento->monto,2) : ''}}</li>
                                @endforeach
                                </ul>
                            </td>
                            <td>
                       			@if (can('editar-ingresos-egresos-caja', false))
                                	<a href="{{route('editar_ingresoegreso', ['id' => $data->id, 'origen' => 'ingresoegreso'])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-ingresos-egresos-caja', false))
                                <form action="{{route('eliminar_ingresoegreso', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" onclick="eliminarIngresoEgreso(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
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
{{ $caja_movimiento->appends(['busqueda' => $busqueda])->links() }}
@endsection
