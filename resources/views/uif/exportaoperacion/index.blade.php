@extends("theme.$theme.layout")
@section('titulo')
Exportación de Clientes UIF
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
                <h3 class="card-title">Exportación de Clientes UIF</h3>
                <div class="card-tools">
                    <a href="{{route('exporta_cliente_uif', ['periodo' => $periodo, 'limiteinformeuif' => $limiteinformeuif])}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('exportar-cliente-uif', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Exporta clientes
						@endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width10">ID</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Número de doc.</th>
                            <th>Domicilio</th>
                            <th>Localidad</th>
                            <th>Provincia</th>
                            <th>Pais</th>
                            <th class="width10">Teléfono</th>
                            <th class="width10">Email</th>
                            <th>Monto Premio</th>
                            <th>Fecha Entrega</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cliente_premio_uifs as $data)
                        <tr>
                            <td>{{$data->premioid}}</td>
                            <td>{{$data->nombrecliente}}</td>
                            <td>{{$data->abreviaturatipodocumento}}</td>
                            <td><small>{{$data->numerodocumento}}</small></td>
                            <td><small>{{$data->domicilio}}</small></td>
                            <td><small>{{$data->nombrelocalidad ?? ''}}</small></td>
                            <td><small>{{$data->nombreprovincia ?? ''}}</small></td>
                            <td><small>{{$data->nombrepais ?? ''}}</small></td>
                            <td><small>{{$data->telefono}}</small></td>
                            <td><small>{{$data->email}}</small></td>
                            <td><small>{{number_format($data->monto,2)}}</small></td>
                            <td><small>{{$data->fechaentrega}}</small></td>
                            <td>
                       			@if (can('editar-cliente-uif', false))
                                	<a href="{{route('edita_cliente_premio_uif', ['id' => $data->premioid])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                	</a>
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
