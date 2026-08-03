@extends("theme.$theme.layout")
@section('titulo')
Clientes UIF
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/uif/cliente_uif/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Uif\ClienteUifListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('consulta_cliente_uif', ClienteUifListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @php $uifCtx = \App\Support\Uif\ClienteUifOrigenPcSupport::contexto(); @endphp
        <div class="alert alert-info py-2 mb-2">
            PC <strong>{{ $uifCtx['identificador_pc'] }}</strong>
            @if ($uifCtx['origen_fijo'])
                — caja fija <strong>{{ $uifCtx['label'] }}</strong>
                ({{ $uifCtx['empresa_nombre'] ?: ('empresa #'.$uifCtx['empresa_id']) }})
            @elseif (\App\Support\Uif\ClienteUifOrigenPcSupport::debeVerTodasLasEmpresasUif())
                — acceso a <strong>BSA / KSA / RSA</strong> (consulta, reportes y exportaciones).
                Al cargar un cliente o premio nuevo indique la empresa.
            @else
                — empresas asignadas:
                <strong>{{ $uifCtx['empresas_uif']->pluck('nombre')->implode(', ') ?: 'ninguna' }}</strong>
            @endif
        </div>
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Clientes UIF</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cliente-uif',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ClienteUifListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida…',
                        'toggleTarget' => '#panel-filtros-cliente-uif',
                        'toggleId' => 'btn-toggle-filtros-cliente-uif',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crea_cliente_uif', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-cliente-uif',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consulta_cliente_uif') }}" id="form-filtros-cliente-uif" class="mb-0">
                @include('uif.cliente_uif.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('uif.cliente_uif.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_cliente_uif',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width10">ID</th>
                            <th>Origen</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Número de doc.</th>
                            <th>Domicilio</th>
                            <th>Localidad</th>
                            <th>Provincia</th>
                            <th>Pais</th>
                            <th class="width10">Teléfono</th>
                            <th class="width10">Email</th>
                            <th data-orderable="false">Último premio</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cliente_uifs as $data)
							@if ($data->estado == '1')
                        		<tr class="table-danger">
							@else
                        		<tr>
							@endif
                            <td>{{$data->id}}</td>
                            <td><small>{{ \App\Support\Uif\ClienteUifOrigenPcSupport::labelOrigen((string) ($data->anita_origen ?? '')) }}</small></td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->abreviaturatipodocumento}}</td>
                            <td><small>{{$data->numerodocumento}}</small></td>
                            <td><small>{{$data->domicilio}}</small></td>
                            <td><small>{{$data->nombrelocalidad ?? ''}}</small></td>
                            <td><small>{{$data->nombreprovincia ?? ''}}</small></td>
                            <td><small>{{$data->nombrepais ?? ''}}</small></td>
                            <td><small>{{$data->telefono}}</small></td>
                            <td><small>{{$data->email}}</small></td>
                            <td>
                                @if (!empty($data->ultimo_premio_fecha))
                                    <small>
                                        {{\Carbon\Carbon::parse($data->ultimo_premio_fecha)->format('d/m/Y H:i')}}<br>
                                        {{ number_format((float) ($data->ultimo_premio_monto ?? 0), 2, ',', '.') }}<br>
                                        {{ $data->ultimo_premio_juego ?? '' }}
                                    </small>
                                @endif
                            </td>
                            <td>
                       			@if (can('editar-cliente-uif', false))
                                	<a href="{{route('edita_cliente_uif', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@elseif (can('listar-cliente-uif', false))
                                	<a href="{{route('edita_cliente_uif', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Ver registro">
                                    <i class="fa fa-eye"></i>
                                	</a>
								@endif
                       			@if (!empty($data->ultimo_premio_fecha) && (can('editar-cliente-uif', false) || can('listar-cliente-uif', false)))
                                <a href="{{ route('edita_cliente_uif', ['id' => $data->id, 'uif_tab' => 3, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                   class="btn-accion-tabla tooltipsC"
                                   title="{{ esSoloVisualizacionClienteUif() ? 'Ver premios del cliente' : 'Premios del cliente' }}"
                                   target="_blank"
                                   rel="noopener">
                                    <i class="fa fa-trophy text-warning"></i>
                                </a>
                       			@endif
                       			@if (can('borrar-cliente-uif', false))
                                <form action="{{route('elimina_cliente_uif', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $cliente_uifs->appends($filtrosQuery ?? [])->links() }}
@endsection
