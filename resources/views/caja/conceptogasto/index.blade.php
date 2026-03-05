@extends("theme.$theme.layout")
@section('titulo')
    Conceptos de Gastos
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
                <h3 class="card-title">Conceptos de Gastos</h3>
                <div class="card-tools">
                    <a href="{{route('crear_conceptogasto')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-conceptos-de-gastos', false))
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
                            <th>Cuentas Contables</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>
                                <ul>
                                @foreach($data->conceptogasto_cuentacontables as $item)
                                    <li>{{ $item->cuentacontables->codigo }} ({{ $item->cuentacontables->nombre }} Empresa:{{ $item->cuentacontables->empresa_id }})</li>
                                @endforeach
                                </ul>
                            </td>
                            <td>
                       			@if (can('editar-conceptos-de-gastos', false))
                                	<a href="{{route('editar_conceptogasto', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-conceptos-de-gastos', false))
                                <form action="{{route('eliminar_conceptogasto', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
