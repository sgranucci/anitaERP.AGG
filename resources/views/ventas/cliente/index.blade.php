@extends("theme.$theme.layout")
@section('titulo')
Clientes
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Ventas\ClienteListadoFiltros; ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Clientes</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cliente',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ClienteListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('cliente'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-cliente',
                        'toggleId' => 'btn-toggle-filtros-cliente',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_cliente'),
                        'nuevoRegistroCan' => 'crear-clientes',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('cliente') }}" id="form-filtros-cliente" class="mb-0">
                @include('ventas.cliente.partials.filtros_listado')
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_cliente',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width10">ID</th>
                            <th>Nombre</th>
                            @if (config('app.empresa') == 'EL BIERZO')
                                <th>Reparto</th>
                            @endif
                            <th>C.U.I.T.</th>
                            <th>Domicilio</th>
                            <th>Localidad</th>
                            <th>Provincia</th>
                            <th class="width10">C&oacute;d.</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clientes as $data)
							@if ($data->estado == '1')
                        		<tr class="table-danger">
							@else
                        		<tr>
							@endif
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            @if (config('app.empresa') == 'EL BIERZO')
                                <td>{{$data->ctransporte}}-{{$data->nombretransporte}}</td>
                            @endif
                            <td><small>{{$data->numerodocumento}}</small></td>
                            <td><small>{{$data->domicilio}}</small></td>
                            <td><small>{{$data->nombrelocalidad ?? ''}}</small></td>
                            <td><small>{{$data->nombreprovincia ?? ''}}</small></td>
                            <td><small>{{$data->codigo}}</small></td>
                            <td>
                       			@if (can('editar-clientes', false))
                                	<a href="{{route('editar_cliente', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                                @if (can('listar-cuentacorriente-cliente', false))
                                	<a href="{{route('listar_cuentacorriente_cliente', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Cuenta Corriente">
                                    <i class="fa fa-folder-open"></i>
                                	</a>
								@endif
                       			@if (can('borrar-clientes', false))
                                    <form action="{{route('eliminar_cliente', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $clientes->appends($filtrosQuery ?? [])->links() }}
@endsection
