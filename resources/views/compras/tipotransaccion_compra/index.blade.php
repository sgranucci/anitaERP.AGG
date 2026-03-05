@extends("theme.$theme.layout")
@section('titulo')
    Tipos de Transacciones de Compras
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
                <h3 class="card-title">Tipos de Transacciones de Compras</h3>
                <div class="card-tools">
                    <a href="{{route('crear_tipotransaccion_compra')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-tipo-transaccion-compra', false))
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
                            <th>Tipo AFIP</th>
                            <th>Signo</th>
                            <th>Subdiario Iva</th>
                            <th>Asiento Contable</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->desc_operacion}}</td>
                            <td>{{$data->abreviatura}}</td>
                            <td>{{$data->codigoafip}}</td>
                            <td>{{$data->desc_signo}}</td>
                            <td>{{$data->desc_subdiario}}</td>
                            <td>{{$data->desc_asientocontable}}</td>
                            <td>{{$data->desc_estado}}</td>
                            <td>
                       			@if (can('editar-tipo-transaccion-compra', false))
                                	<a href="{{route('editar_tipotransaccion_compra', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-tipo-transaccion-compra', false))
                                <form action="{{route('eliminar_tipotransaccion_compra', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
