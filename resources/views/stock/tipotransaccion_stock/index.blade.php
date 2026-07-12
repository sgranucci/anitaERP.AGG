@extends("theme.$theme.layout")
@section('titulo')
    Tipos de transacciones de Stock
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Tipos de transacciones de Stock</h3>
                <div class="card-tools">
                    <a href="{{route('crear_tipotransaccion_stock')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-tipos-transaccion-stock', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Operaci&oacute;n</th>
                            <th>Abreviatura</th>
                            <th>Signo</th>
                            <th>Estado</th>
                            <th>Aprobaci&oacute;n</th>
                            <th>Contab.</th>
                            <th>Dest. bien</th>
                            <th>Orig. bien</th>
                            <th>Baja NPU</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$operacionEnum[$data->operacion] ?? $data->operacion}}</td>
                            <td>{{$data->abreviatura}}</td>
                            <td>{{$signoEnum[$data->signo] ?? $data->signo}}</td>
                            <td>{{$estadoEnum[$data->estado] ?? $data->estado}}</td>
                            <td>{{ $data->requiere_aprobacion ? 'Sí' : 'No' }}</td>
                            <td>{{ $data->maneja_contabilidad ? 'Sí' : 'No' }}</td>
                            <td>{{ $data->destino_bien_uso ? 'Sí' : 'No' }}</td>
                            <td>{{ $data->origen_bien_uso ? 'Sí' : 'No' }}</td>
                            <td>{{ $data->baja_npu ? 'Sí' : 'No' }}</td>
                            <td>
                       			@if (can('editar-tipos-transaccion-stock', false))
                                	<a href="{{route('editar_tipotransaccion_stock', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-tipos-transaccion-stock', false))
                                <form action="{{route('eliminar_tipotransaccion_stock', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
@endsection
