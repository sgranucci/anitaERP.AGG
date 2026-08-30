@extends("theme.$theme.layout")
@section('titulo')
Impuestos
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
                <h3 class="card-title">Impuestos</h3>
                <div class="card-tools">
                    <a href="{{route('crear_impuesto')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-impuestos', false))
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
                            <th>Valor</th>
                            <th>Fecha vigencia</th>
                            <th>C&oacute;digo ARCA</th>
                            <th>Código ANITA</th>
                            <th>Cuentas Contables</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->valor}}</td>
        					<td>
            					{{date("d/m/Y", strtotime($data->fechavigencia ?? ''))}} 
        					</td>
                            <td>{{$data->codigoarca}}</td>
                            <td>{{$data->codigo}}</td>
                            <td>                                
                                <ul>
                                    @foreach($data->impuesto_cuentacontables as $cuentacontable)
                                        <li>{{$cuentacontable->empresas->nombre}} {{$cuentacontable->cuentacontables->codigo}}-{{ $cuentacontable->cuentacontables->nombre }}</li>
                                    @endforeach
                                </ul>
                            </td>                            
                            <td>
                       			@if (can('editar-impuestos', false))
                                	<a href="{{route('editar_impuesto', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-impuestos', false))
                                <form action="{{route('eliminar_impuesto', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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

        <div class="card card-outline card-info mt-3">
            <div class="card-body py-3">
                Percepción IVA (RG 5329) y no categorizado (RG 2126):
                @if (can('listar-regimen-percepcion', false))
                    <a href="{{ route('regimen_percepcion') }}" class="text-primary">Regímenes de percepción</a>.
                @else
                    menú Configuración → Regímenes de percepción.
                @endif
                Alícuota, mínimos y cuentas del asiento viven ahí, no en este ABM.
            </div>
        </div>
    </div>
</div>
@endsection
