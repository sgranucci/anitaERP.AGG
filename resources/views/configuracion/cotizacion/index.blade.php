@extends("theme.$theme.layout")
@section('titulo')
    Cotizaciones
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>

<script>
    function eliminarCotizacion(event) {
        var opcion = confirm("Desea eliminar el cotizacion?");
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
                <h3 class="card-title">Cotizaciones</h3>
                <div class="card-tools">
                    <a href="{{route('crear_cotizacion')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-cotizacion', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('cotizacion') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_cotizacion', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Fecha</th>
                            <th>Cotizaciones</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cotizaciones as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>
                                <ul>
                                @foreach($data->cotizacion_monedas as $moneda)
                                    <li>{{ $moneda->monedas->nombre }} Venta {{ number_format($moneda->cotizacionventa,4) }} Compra {{ number_format($moneda->cotizacioncompra,4) }}</li>
                                @endforeach
                                </ul>
                            </td>
                            <td>
                       			@if (can('editar-cotizacion', false))
                                	<a href="{{route('editar_cotizacion', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-cotizacion', false))
                                <form action="{{route('eliminar_cotizacion', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" onclick="eliminarCotizacion(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
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
{{ $cotizaciones->appends(['busqueda' => $busqueda])->links() }}
@endsection
