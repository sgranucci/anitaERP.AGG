@extends("theme.$theme.layout")
@section('titulo')
    Puntos de Venta
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
                <h3 class="card-title">Puntos de Venta</h3>
                <div class="card-tools">
                    <a href="{{route('crear_puntoventa')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-puntos-de-venta', false))
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
                            <th>C&oacute;digo</th>
                            <th>Empresa</th>
                            <th>Domicilio</th>
                            <th>Localidad</th>
                            <th>Provincia</th>
                            <th>Modo Facturaci&oacute;n</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                    
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->codigo}}</td>
                            <td>{{$data->empresas->nombre}}</td>
                            <td>{{$data->domicilio}}</td>
                            <td>{{$data->localidades->nombre}}</td>
                            <td>{{$data->provincias->nombre}}</td>
                            <td>{{$modofacturacionEnum[$data->modofacturacion]}}</td>
                            <td>{{$estadoEnum[$data->estado]}}</td>
                            <td>
                       			@if (can('editar-puntos-de-venta', false))
                                	<a href="{{route('editar_puntoventa', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-puntos-de-venta', false))
                                <form action="{{route('eliminar_puntoventa', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
