@extends("theme.$theme.layout")
@section('titulo')
    Montos a Operar UIF
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
                <h3 class="card-title">Montos a Operar UIF</h3>
                <div class="card-tools">
                    <a href="{{route('crea_monto_uif')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-monto-uif', false))
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
                            <th>Desde monto</th>
                            <th>Hasta monto</th>
                            <th>Riesgo</th>
                            <th>Puntaje</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{number_format($data->desdemonto,2)}}</td>
                            <td>{{number_format($data->hastamonto,2)}}</td>
                            <td>{{$data->riesgo}}</td>
                            <td>{{$data->puntaje}}</td>
                            <td>
                       			@if (can('editar-monto-uif', false))
                                	<a href="{{route('edita_monto_uif', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-monto-uif', false))
                                <form action="{{route('elimina_monto_uif', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
