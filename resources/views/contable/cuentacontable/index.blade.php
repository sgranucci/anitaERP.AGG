@extends("theme.$theme.layout")
@section('titulo')
    Cuentas Contables
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
                <h3 class="card-title">Cuentas Contables</h3>
                <div class="card-tools">
                    <a href="{{route('crear_cuentacontable')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-cuentas-contables', false))
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
                            <th>Empresa</th>
                            <th>Número de cuenta</th>
                            <th>Nombre</th>
                            <th>Nivel</th>
                            <th>Tipo de cuenta</th>
                            <th>Rubro</th>
                            <th>C.Costo</th>
                            <th>Concepto</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cuentacontables as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->empresas->nombre}}</td>
                            <td>{{$data->codigo}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->nivel}}</td>
                            <td>{{($data->tipocuenta==1?'Imputable':
                                  ($data->tipocuenta==2?'No imputable':'Totalizadora'))}}</td>
                            <td>{{$data->rubrocontables->nombre??''}}</td>
                            <td>{{$data->manejaccosto == 'S' ? 'Maneja C.Costo' : 'No maneja C.Costo'}}</td>
                            <td>{{$data->conceptogastos->nombre??''}}</td>
                            <td>
                       			@if (can('editar-cuentas-contables', false))
                                	<a href="{{route('editar_cuentacontable', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-cuentas-contables', false))
                                <form action="{{route('eliminar_cuentacontable', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
