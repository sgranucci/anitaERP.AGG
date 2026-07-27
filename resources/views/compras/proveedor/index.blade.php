@extends("theme.$theme.layout")
@section('titulo')
Proveedores
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Compras\ProveedorListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Proveedores</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.compras.boton-manual')
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-proveedor',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ProveedorListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('proveedor'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-proveedor',
                        'toggleId' => 'btn-toggle-filtros-proveedor',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_proveedor', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-proveedor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('proveedor') }}" id="form-filtros-proveedor" class="mb-0">
                @include('compras.proveedor.partials.filtros_listado', [
                    'limpiarUrl' => route('proveedor'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_proveedor',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width10">ID</th>
                            <th>Nombre</th>
                            <th>Nombre de Fantas&iacute;a</th>
                            <th>C.U.I.T.</th>
                            <th>Domicilio</th>
                            <th>Localidad</th>
                            <th>Provincia</th>
                            <th class="width10">C&oacute;d.</th>
                            <th>Estado</th>
                            <th class="width10">APOC</th>
                            <th class="width100" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($proveedores as $data)
							@if ($data->estado == '1')
                        		<tr class="table-danger">
							@else
                        		<tr>
							@endif
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->fantasia}}</td>
                            <td><small>{{$data->numerodocumento}}</small></td>
                            <td><small>{{$data->domicilio}}</small></td>
                            <td><small>{{$data->nombrelocalidad ?? ''}}</small></td>
                            <td><small>{{$data->nombreprovincia ?? ''}}</small></td>
                            <td><small>{{$data->codigo}}</small></td>
                            <td><small>{{$data->estado}}</small></td>
                            <td class="text-center">
                                @if (!empty($data->facturas_apocrifas))
                                    <span class="badge badge-danger" title="Figura en base ARCA de facturas ap&oacute;crifas">S&iacute;</span>
                                @elseif (!empty($data->facturas_apocrifas_consulta_at))
                                    <span class="badge badge-success" title="Consultado {{ $data->facturas_apocrifas_consulta_at }}">No</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                       			@if (can('editar-proveedor', false))
                                	<a href="{{route('editar_proveedor', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                                @if (can('listar-cuentacorriente-proveedor', false))
                                	<a href="{{route('listar_cuentacorriente_proveedor', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta'])}}" target="_blank" rel="noopener" class="btn-accion-tabla tooltipsC" title="Cuenta Corriente (se abre en modo consulta)">
                                    <i class="fa fa-folder-open"></i>
                                	</a>
								@endif                                
                       			@if (can('borrar-proveedor', false))
                                <form action="{{route('eliminar_proveedor', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $proveedores->appends($filtrosQuery ?? [])->links() }}
@endsection
