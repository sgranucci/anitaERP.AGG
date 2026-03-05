@extends("theme.$theme.layout")
@section('titulo')
    Chequeras
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
                <h3 class="card-title">Chequeras</h3>
                <div class="card-tools">
                    <a href="{{route('crear_chequera')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-chequera', false))
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
                            <th>Tipo de chequera</th>
                            <th>Tipo de cheque</th>
                            <th>Código</th>
                            <th>Cuenta de caja</th>
                            <th>Desde cheque</th>
                            <th>Hasta cheque</th>
                            <th>Fecha de uso</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>@foreach($tipochequera_enum as $tipochequera)
									@if ($tipochequera['valor'] == $data->tipochequera)
										{{ $tipochequera['nombre'] }}
									@endif
								@endforeach
                            </td>
                            <td>@foreach($tipocheque_enum as $tipocheque)
									@if ($tipocheque['valor'] == $data->tipocheque)
										{{ $tipocheque['nombre'] }}
									@endif
								@endforeach
                            </td>
                            <td>{{$data->codigo}}</td>
                            <td>{{$data->cuentacajas->nombre ?? ''}}</td>
                            <td>{{$data->desdenumerocheque}}</td>
                            <td>{{$data->hastanumerocheque}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fechauso ?? ''))}}</td>
                            <td>@foreach($estado_enum as $estado)
									@if ($estado['valor'] == $data->estado)
										{{ $estado['nombre'] }}
									@endif
								@endforeach
                            </td>
                            <td>
                       			@if (can('editar-chequera', false))
                                	<a href="{{route('editar_chequera', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-chequera', false))
                                <form action="{{route('eliminar_chequera', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
