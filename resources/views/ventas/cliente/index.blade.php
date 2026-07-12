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
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
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
                        'nuevoRegistroUrl' => route('crear_cliente', $retornoListadoQuery),
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
                <table class="table table-striped table-bordered table-hover table-sm" id="tabla-paginada" style="font-size: 0.8125rem;">
                    <thead>
                        <tr>
                            <th class="width10">ID</th>
                            <th style="min-width: 120px;">Nombre</th>
                            <th style="min-width: 90px;">Vendedor</th>
                            @if (config('app.empresa') == 'EL BIERZO')
                                <th style="min-width: 90px;">Reparto</th>
                            @endif
                            <th style="min-width: 95px;">C.U.I.T.</th>
                            <th style="min-width: 120px;">Domicilio</th>
                            <th style="min-width: 85px;">Localidad</th>
                            <th style="min-width: 85px;">Provincia</th>
                            <th class="width10">C&oacute;d.</th>
                            <th class="text-center" style="width: 2.25rem;" title="Estado">St.</th>
                            <th class="width10">APOC</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clientes as $data)
							@if ($data->estado == '1')
                        		<tr class="table-danger">
							@elseif ($data->estado == 'R')
                        		<tr class="table-warning">
							@else
                        		<tr>
							@endif
                            <td>{{$data->id}}</td>
                            <td class="text-truncate" style="max-width: 160px;" title="{{ $data->nombre }}">{{$data->nombre}}</td>
                            <td class="text-truncate" style="max-width: 110px;" title="{{ trim(($data->cvendedor ?? '').($data->nombrevendedor ? ' - '.$data->nombrevendedor : '')) }}">
                                <small>
                                    {{ $data->cvendedor ?? '' }}
                                    @if (!empty($data->nombrevendedor))
                                        -{{ $data->nombrevendedor }}
                                    @endif
                                </small>
                            </td>
                            @if (config('app.empresa') == 'EL BIERZO')
                                <td class="text-truncate" style="max-width: 110px;" title="{{ trim(($data->ctransporte ?? '').($data->nombretransporte ? '-'.$data->nombretransporte : '')) }}">
                                    <small>{{$data->ctransporte}}-{{$data->nombretransporte}}</small>
                                </td>
                            @endif
                            <td><small>{{$data->numerodocumento}}</small></td>
                            <td class="text-truncate" style="max-width: 160px;" title="{{ $data->domicilio }}"><small>{{$data->domicilio}}</small></td>
                            <td class="text-truncate" style="max-width: 110px;" title="{{ $data->nombrelocalidad ?? '' }}"><small>{{$data->nombrelocalidad ?? ''}}</small></td>
                            <td class="text-truncate" style="max-width: 110px;" title="{{ $data->nombreprovincia ?? '' }}"><small>{{$data->nombreprovincia ?? ''}}</small></td>
                            <td><small>{{$data->codigo}}</small></td>
                            <td class="text-center p-1">
                                @if ($data->estado === '1')
                                    <span class="badge badge-danger" title="Suspendido">S</span>
                                @elseif ($data->estado === 'R')
                                    <span class="badge badge-warning text-dark" title="Regularizado: problemas ARCA, facturaci&oacute;n permitida">R</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if (!empty($data->facturas_apocrifas))
                                    <span class="badge badge-danger" title="Facturas apócrifas ARCA">Sí</span>
                                @elseif (!empty($data->facturas_apocrifas_consulta_at))
                                    <span class="badge badge-success" title="Consultado {{ $data->facturas_apocrifas_consulta_at }}">No</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                	<a href="{{route('editar_cliente', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
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
