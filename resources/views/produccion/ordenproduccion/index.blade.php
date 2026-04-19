@extends("theme.$theme.layout")
@section('titulo')
    Ordenes de Producción
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Ordenes de Producción</h3>
                <div class="card-tools">
                    <a href="{{route('crear_ordenproduccion')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-orden-produccion', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('consultar_ordenproduccion') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_ordenproduccion', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Inicio</th>
                            <th>Finalización</th>
                            <th>Responsable</th>
                            <th>Linea de Llenado</th>
                            <th>Nro.Orden Prod.</th>
                            <th>Tipo de Producto</th>
                            <th>Líquido de Freno Tipo</th>
                            <th>Capacidad</th>
                            <th>Marca</th>
                            <th>Tipo de Color</th>
                            <th>Cantidad</th>
                            <th>Proviene de Bins</th>
                            <th>Lote</th>
                            <th>Observaciones</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ordenesproduccion as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td><small>{{\Carbon\Carbon::parse($data->fechainicio)->format('d-m-Y H:i')}}</small></td>
                            <td><small>{{\Carbon\Carbon::parse($data->fechafinalizacion)->format('d-m-Y H:i')}}</small></td>
                            <td><small>{{$data->nombreusuario}}</small></td>
                            <td><small>{{$data->nombrelineallenado??''}}</small></td>
                            <td><small>{{$data->numeroordenproduccion}}</small></td>
                            <td><small>{{$data->nombretipoproducto??''}}</small></td>
                            <td><small>{{$data->nombretipoliquidofreno??''}}</small></td>
                            <td><small>{{$data->nombrecapacidad??''}}</small></td>
                            <td><small>{{$data->nombremarca??''}}</small></td>
                            <td><small>{{$data->nombrecolor??''}}</small></td>
                            <td><small>{{$data->cantidad}}</small></td>
                            <td><small>{{$data->nombreprovienebin??''}}</small></td>
                            <td><small>{{$data->lote}}</small></td>
                            <td><small>{{$data->observacion}}</small></td>
                            <td>
                       			@if (can('editar-orden-produccion', false))
                                	<a href="{{route('editar_ordenproduccion', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-orden-produccion', false))
                                <form action="{{route('eliminar_ordenproduccion', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $ordenesproduccion->appends(['busqueda' => $busqueda])->links() }}
@endsection
