@extends("theme.$theme.layout")
@section('titulo')
Zonas de venta
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
                <h3 class="card-title">Zonas de venta</h3>
                <div class="card-tools">
                    <a href="{{route('crear_zonavta')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-zonas-de-venta', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead @if(\App\Support\Ventas\ZonavtaDestinoElBierzoSupport::activo()) style="background:#85C1E9;color:#17202A;" @endif>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Código Anita</th>
                            @if(\App\Support\Ventas\ZonavtaDestinoElBierzoSupport::activo())
                                <th>Localidad destino</th>
                                <th>Cód. localidad SENASA</th>
                            @endif
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->codigo}}</td>
                            @if(\App\Support\Ventas\ZonavtaDestinoElBierzoSupport::activo())
                                <td>{{ $data->destino->localidad ?? '' }}</td>
                                <td>{{ $data->destino->codigo_localidad_senasa ?? '' }}</td>
                            @endif
                            <td>
                       			@if (can('editar-zonas-de-venta', false))
                                	<a href="{{route('editar_zonavta', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-zonas-de-venta', false))
                                <form action="{{route('eliminar_zonavta', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
