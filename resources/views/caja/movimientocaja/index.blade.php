@extends("theme.$theme.layout")
@section('titulo')
    Movimientos de Caja
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>

<script>
    $(function () {
        let caja_id = $('#caja_id').val();

        if (caja_id == -1)
        {
            alert('No tiene caja asignada');
            window.close();
        }
    });

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
@if (isset($mensaje))
    <div class="alert alert-danger">
        <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
             {{ $mensaje }}
    </div>
@else
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Movimientos de Caja</h3>
                @if (isset($caja_asignacion))
                    <h3 class="card-title">&nbsp&nbsp&nbsp&nbsp&nbsp Caja Asignada: {{$caja_asignacion->caja_id ?? ''}} - {{$caja_asignacion->caja_id > 0 ? $caja_asignacion->cajas->nombre : ''}}</h3>
                    <input type="hidden" class="caja_id" id='caja_id' name="caja_id" value="{{$caja_asignacion->caja_id ?? ''}}" >
                @endif
                <div class="card-tools">
                    <a href="{{route('crear_ingresoegreso', ['caja' => $caja_asignacion->caja_id ?? ''])}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-movimientos-caja', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Ingresos y egresos
						@endif
                    </a>
                    <a href="{{route('crear_rendicionreceptivo', ['caja' => $caja_asignacion->caja_id ?? ''])}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-rendicion-receptivo', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Rendiciones
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
                            <th class="width20">Número</th>
                            <th>Fecha</th>
                            <th>Tipo de transacción</th>
                            <th>Detalle</th>
                            <th class="width30">Ingreso</th>
                            <th class="width30">Egreso</th>
                            @foreach ($monedaQuery as $moneda)
                                <th class="width30">Saldo {{$moneda->nombre}}</th>
                            @endforeach
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($caja_movimiento as $data)
                        <tr>
                            <td>{{$data->caja_movimiento_id}}</td>
                            <td>{{$data->numerotransaccion}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>{{$data->nombretipotransaccion_caja}}</td>
                            <td>{{$data->detalle ?? ''}}</td>
                            <td>
                                @if ($data->monto > 0)
                                    {{$monedaQuery[$data->moneda_id-1]->abreviatura}}
                                    {{number_format($data->monto,2)}}
                                @endif
                            </td>
                            <td>
                                @if ($data->monto < 0)
                                    {{$monedaQuery[$data->moneda_id-1]->abreviatura}}
                                    {{number_format(abs($data->monto),2)}}
                                @endif                                
                            </td>
                            @foreach ($monedaQuery as $moneda)
                                <td>
                                    @if ($moneda->id == $data->moneda_id)
                                        @php $saldo = 'saldo'.$moneda['id']; @endphp
                                        {{number_format($data->{$saldo},2)}}
                                    @endif
                                </td>
                            @endforeach 
                            <td>
                       			@if (can('editar-ingresos-egresos-caja', false))
                                	<a href="{{route('editar_ingresoegreso', ['id' => $data->caja_movimiento_id, 'origen' => 'movimientocaja'])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                                @if (\Carbon\Carbon::now()->format('Y-m-d') == date("Y-m-d", strtotime($data->fecha ?? '')) || can('supervisor-movimiento-caja', false))
                                    @if (can('borrar-ingresos-egresos-caja', false))
                                    <form action="{{route('eliminar_ingresoegreso', ['id' => $data->caja_movimiento_id, 'origen' => 'movimientocaja'])}}" class="d-inline form-eliminar" method="POST">
                                        @csrf @method("delete")
                                        <button type="submit" onclick="eliminarIngresoEgreso(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </form>
                                    @endif
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
@endif
@endsection
