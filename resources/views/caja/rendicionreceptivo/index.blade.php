@extends("theme.$theme.layout")
@section('titulo')
    Rendiciones
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>

<script>
    function eliminarRendicionReceptivo(event) {
        var opcion = confirm("Desea eliminar el pedido?");
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
                <h3 class="card-title">Rendiciones</h3>
                <div class="card-tools">
                    <a href="{{route('crear_rendicionreceptivo')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-rendicion-receptivo', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('rendicionreceptivo') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_rendicionreceptivo', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Orden de servicio</th>
                            <th>Guía</th>
                            <th>Movil</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rendicionreceptivos as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->numerotalonario}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>{{$data->ordenservicio_id}}</td>
                            <td>{{$data->nombreguia}}</td>
                            <td>{{$data->nombremovil ?? ''}}</td>
                            <td>
                       			@if (can('editar-rendicion-receptivo', false))
                                	<a href="{{route('editar_rendicionreceptivo', ['id' => $data->id, 'origen' => 'rendicionreceptivo'])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-rendicion-receptivo', false))
                                <form action="{{route('eliminar_rendicionreceptivo', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" onclick="eliminarRendicionReceptivo(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
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
{{ $rendicionreceptivos->appends(['busqueda' => $busqueda])->links() }}
@endsection
