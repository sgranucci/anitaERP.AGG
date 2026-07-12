@extends("theme.$theme.layout")
@section('titulo')
    Retenciones Impositivas ARCA
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
                <h3 class="card-title">Retenciones Impositivas ARCA</h3>
                <div class="card-tools">
                    <a href="{{route('crear_importacion_retencion_impositiva_arca')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('importar-retencion-impositiva-arca', false))
                        	<i class="fa fa-fw fa-file-excel"></i> Importa Retenciones ARCA
						@endif
                    </a>  
                    <a href="{{route('conciliar_retencion_impositiva_arca')}}" class="btn btn-success btn-sm">
                       	@if (can('conciliar-retencion-impositiva-arca', false))
                        	<i class="fa fa-fw fa-check"></i> Concilia Retenciones
						@endif
                    </a>                      
                    <a href="{{route('crear_retencion_impositiva_arca')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-retencion-impositiva-arca', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('retencion_impositiva_arca') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_retencion_impositiva_arca', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Nombre</th>
                            <th>CUIT</th>
                            <th>Impuesto</th>
                            <th>Fecha de Retención</th>
                            <th>Certificado</th>
                            <th>Monto Retención</th>
                            <th>Fecha de Comprobante</th>
                            <th>Nro. de Comprobante</th>
                            <th>Descripcion Comprobante</th>
                            <th>Fecha de Registración</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($retencionimpositiva_arca as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombreempresa}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->cuit}}</td>
                            <td>{{$data->descripcionimpuesto}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fecharetencion ?? ''))}}</td>
                            <td>{{$data->numerocertificado}}</td>
                            <td>{{$data->montoretencion}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fechacomprobante ?? ''))}}</td>
                            <td>{{$data->numerocomprobante}}</td>
                            <td>{{$data->descripcioncomprobante}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fecharegistracion ?? ''))}}</td>
                            <td>
                       			@if (can('editar-retencion-impositiva-arca', false))
                                	<a href="{{route('editar_retencion_impositiva_arca', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-retencionimpositiva_arca', false))
                                <form action="{{route('eliminar_retencion_impositiva_arca', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $retencionimpositiva_arca->appends(['busqueda' => $busqueda])->links() }}
@endsection
