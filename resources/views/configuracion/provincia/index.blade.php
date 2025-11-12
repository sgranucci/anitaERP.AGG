@extends("theme.$theme.layout")
@section('titulo')
Provincias
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
                <h3 class="card-title">Provincias</h3>
                <div class="card-tools">
                    <a href="{{route('crear_provincia')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-provincias', false))
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
                            <th>Abreviatura</th>
                            <th>Juris.</th>
                            <th>C&oacute;digo</th>
                            <th>Pa&iacute;s</th>
                            <th>Tasas por Condición IIBB</th>
                            <th>Cuentas Contables</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->abreviatura}}</td>
                            <td>{{$data->jurisdiccion}}</td>
                            <td>{{$data->codigo}}</td>
                            <td>{{$data->paises->nombre}}</td>
                            <td>                                
                                <ul>
                                    @foreach($data->provincia_tasaiibbs as $tasa)
                                        <li>{{ $tasa->condicioniibbs->nombre }} {{ number_format($tasa->tasa,2) }} % Min.Neto {{ number_format($tasa->minimoneto,2)}} Min.Perc.{{ number_format($tasa->minimopercepcion,2)}}</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td>                                
                                <ul>
                                    @foreach($data->provincia_cuentacontableiibbs as $cuentacontable)
                                        <li>{{$cuentacontable->empresas->nombre}} {{$cuentacontable->cuentacontables->codigo}}-{{ $cuentacontable->cuentacontables->nombre }}</li>
                                    @endforeach
                                </ul>
                            </td>                            
                            <td>
                       			@if (can('editar-provincias', false))
                                	<a href="{{route('editar_provincia', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-provincias', false))
                                <form action="{{route('eliminar_provincia', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
