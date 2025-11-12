@extends("theme.$theme.layout")
@section('titulo')
Vendedores
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
                <h3 class="card-title">Vendedores</h3>
                <div class="card-tools">
                    <a href="{{route('crear_vendedor')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-vendedores', false))
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
                            <th>Comis.venta</th>
                            <th>Comis.cobranza</th>
                            <th>Aplica sobre</th>
                            <th>Email</th>
                            <th>Empresa</th>
                            <th>Legajo</th>
                            <th>Código Anita</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->comisionventa}}</td>
                            <td>{{$data->comisioncobranza}}</td>
                            <td>{{$data->aplicasobre}}</td>
                            <td>{{$data->email}}</td>
                            <td>{{$data->empresas->nombre??''}}</td>
                            <td>{{$data->legajo_id}}</td>
                            <td>{{$data->codigo}}</td>
                            <td>{{$data->estado}}</td>
                            <td>
                       			@if (can('editar-vendedores', false))
                                	<a href="{{route('editar_vendedor', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-vendedores', false))
                                <form action="{{route('eliminar_vendedor', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
