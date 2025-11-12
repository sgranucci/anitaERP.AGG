@extends("theme.$theme.layout")
@section('titulo')
    Padrón Exclusión Percepción de Iva
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
                <h3 class="card-title">Padrón Exclusión Percepción de Iva</h3>
                <div class="card-tools">
                    <a href="{{route('crear_importacion_padron_exclusionpercepcioniva')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('importar-padron-exclusion-percepcion-iva', false))
                        	<i class="fa fa-fw fa-file-excel"></i> Importa Padron Exclusión Percepción Iva
						@endif
                    </a>  
                    <a href="{{route('crear_padron_exclusionpercepcioniva')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-padron-exclusion-percepcion-ivas', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('padron_exclusionpercepcioniva') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_padron_exclusionpercepcioniva', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>CUIT</th>
                            <th>Desde Fecha Exclusión</th>
                            <th>Hasta Fecha Exclusión</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($padron_exclusionpercepcionivas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->cuit}}</td>
                            <td>{{date("d/m/Y", strtotime($data->desdefecha ?? ''))}}</td>
                            @if ($data->hastafecha == NULL)
                                <td></td>
                            @else
                                <td>{{date("d/m/Y", strtotime($data->hastafecha ?? ''))}}</td>
                            @endif
                            <td>
                       			@if (can('editar-padron-exclusion-percepcion-iva', false))
                                	<a href="{{route('editar_padron_exclusionpercepcioniva', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-padron_exclusion-percepcion-iva', false))
                                <form action="{{route('eliminar_padron_exclusionpercepcioniva', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $padron_exclusionpercepcionivas->appends(['busqueda' => $busqueda])->links() }}
@endsection
