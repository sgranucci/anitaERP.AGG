@extends("theme.$theme.layout")
@section('titulo')
    Talonarios de Vouchers
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
                <h3 class="card-title">Talonarios de Vouchers</h3>
                <div class="card-tools">
                    <a href="{{route('crear_talonariovoucher')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-talonario-de-voucher', false))
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
                            <th>Serie</th>
                            <th>ID Orígen</th>
                            <th>Orígen</th>
                            <th>Fecha inicio</th>
                            <th>Fecha cierre</th>
                            <th>Desde número</th>
                            <th>Hasta número</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->serie}}</td>
                            <td>{{$data->origenvoucher_id}}</td>
                            <td>{{$data->origenesvoucher->nombre}}</td>
                            <td>@if($data->fechainicio !== null)
                                    {{date("Y/m/d", strtotime($data->fechainicio))}}
                                @endif
                            </td>
                            <td>@if($data->fechainicio !== null)
                                    {{date("Y/m/d", strtotime($data->fechacierre))}}
                                @endif
                            </td>
                            <td>{{$data->desdenumero}}</td>
                            <td>{{$data->hastanumero}}</td>
                            <td>{{$data->estado}}</td>
                            <td>
                       			@if (can('editar-talonario-de-voucher', false))
                                	<a href="{{route('editar_talonariovoucher', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-talonario-de-voucher', false))
                                <form action="{{route('eliminar_talonariovoucher', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
