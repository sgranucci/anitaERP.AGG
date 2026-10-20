@extends("theme.$theme.layout")
@section('titulo')
    Areas de Destino
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
                <h3 class="card-title">Areas de destino</h3>
                <div class="card-tools">
                    <a href="{{route('crea_areadestino')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-area-destino', false))
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
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>
                                @if ($data->suspendido)
                                    <span class="badge badge-warning">Suspendida</span>
                                @else
                                    <span class="badge badge-success">Activa</span>
                                @endif
                            </td>
                            <td>
                       			@if (can('editar-area-destino', false))
                                	<a href="{{route('edita_areadestino', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                                @if (can('actualizar-area-destino', false))
                                <form action="{{ route('suspende_areadestino', ['id' => $data->id]) }}" class="d-inline" method="POST">
                                    @csrf @method('put')
                                    <button type="submit" class="btn-accion-tabla tooltipsC" title="{{ $data->suspendido ? 'Reactivar esta área' : 'Suspender esta área' }}">
                                        <i class="fa {{ $data->suspendido ? 'fa-play-circle text-success' : 'fa-pause-circle text-warning' }}"></i>
                                    </button>
                                </form>
                                @endif
                       			@if (can('borrar-area-destino', false))
                                <form action="{{route('elimina_areadestino', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
