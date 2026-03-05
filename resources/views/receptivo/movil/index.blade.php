@extends("theme.$theme.layout")
@section('titulo')
    Móviles
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
                <h3 class="card-title">Móviles</h3>
                <div class="card-tools">
                    <a href="{{route('crear_movil')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-movil', false))
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
                            <th>Dominio</th>
                            <th>Código Anita</th>
                            <th>Tipo de móvil</th>
                            <th>Vto. Verif. Municipal</th>
                            <th>Vto. Verif. Técnica</th>
                            <th>Vto. Service</th>
                            <th>Vto. Corredor Turístico</th>
                            <th>Vto. Ingreso Parque Nac.</th>
                            <th>Vto. Seguro</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->dominio}}</td>
                            <td>{{$data->codigo}}</td>
                            <td>
                                @php $nombreTipoMovil = ''; @endphp
                                @foreach($tipomovil_enum as $tipoMovil)
                                    @if ($tipoMovil['valor'] == $data->tipomovil)
								        @php $nombreTipoMovil = $tipoMovil['nombre']; @endphp
                                    @endif
            					@endforeach
            					{{ $nombreTipoMovil ?? '' }}
                            </td>
                            <td>{{$data->vencimientoverificacionmunicipal == "0000-00-00" ? '' : date("d/m/Y", strtotime($data->vencimientoverificacionmunicipal ?? ''))}}</td>
                            <td>{{$data->vencimientoverificaciontecnica == "0000-00-00" ? '' : date("d/m/Y", strtotime($data->vencimientoverificaciontecnica ?? ''))}}</td>
                            <td>{{$data->vencimientoservice == "0000-00-00" ? '' : date("d/m/Y", strtotime($data->vencimientoservice ?? ''))}}</td>
                            <td>{{$data->vencimientocorredor == "0000-00-00" ? '' : date("d/m/Y", strtotime($data->vencimientocorredor ?? ''))}}</td>
                            <td>{{$data->vencimientoingresoparque == "0000-00-00" ? '' : date("d/m/Y", strtotime($data->vencimientoingresoparque ?? ''))}}</td>
                            <td>{{$data->vencimientoseguro == "0000-00-00" ? '' : date("d/m/Y", strtotime($data->vencimientoseguro ?? ''))}}</td>
                            <td>
                       			@if (can('editar-movil', false))
                                	<a href="{{route('editar_movil', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-movil', false))
                                <form action="{{route('eliminar_movil', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
