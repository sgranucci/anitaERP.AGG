@extends("theme.$theme.layout")
@section('titulo')
Premios UIF
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/uif/cliente_premio_uif/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Uif\ClientePremioUifListadoFiltros; ?>

@section('styles')
    @include('uif.cliente_premio_uif.partials.foto_estilos')
@endsection

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('consulta_cliente_premio_uif', ClientePremioUifListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
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
            @else
                — empresas asignadas:
                <strong>{{ $uifCtx['empresas_uif']->pluck('nombre')->implode(', ') ?: 'ninguna' }}</strong>
            @endif
        </div>
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Premios UIF</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cliente-premio-uif',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ClientePremioUifListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida…',
                        'toggleTarget' => '#panel-filtros-cliente-premio-uif',
                        'toggleId' => 'btn-toggle-filtros-cliente-premio-uif',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consulta_cliente_premio_uif') }}" id="form-filtros-cliente-premio-uif" class="mb-0">
                @include('uif.cliente_premio_uif.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('uif.partials.filtros_externos', [
                'rutaIndex' => 'consulta_cliente_premio_uif',
            ])
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_cliente_premio_uif',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width10">ID</th>
                            <th>Origen</th>
                            <th>Nombre</th>
                            <th>Sala</th>
                            <th>Juego</th>
                            <th>Fecha Entrega</th>
                            <th style="text-align: right;">Monto</th>
                            <th>Posición</th>
                            <th>Número TITO</th>
                            <th>Forma de Pago</th>
                            <th style="text-align: center; width: 72px;">Foto</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cliente_premio_uifs as $data)
                       		<tr>
                            <td>{{$data->id}}</td>
                            <td><small>{{ \App\Support\Uif\ClienteUifOrigenPcSupport::labelOrigen((string) ($data->anita_origen ?? '')) }}</small></td>
                            <td>{{$data->nombrecliente}}</td>
                            <td>{{$data->nombresala}}</td>
                            <td>{{$data->nombrejuego}}</td>
                            <td>@if(!empty($data->fechaentrega)){{\Carbon\Carbon::parse($data->fechaentrega)->format('d/m/Y H:i')}}@else<span class="text-muted">—</span>@endif</td>
                            <td style="text-align: right;">{{ number_format((float) ($data->monto ?? 0), 2, ',', '.') }}</td>
                            <td>{{$data->posicion ?? ''}}</td>
                            <td>{{$data->numerotito ?? ''}}</td>
                            <td>{{$data->nombreformapago}}</td>
                            <td class="text-center align-middle premio-foto-preview">
                                @include('uif.cliente_premio_uif.partials.foto_celda', [
                                    'foto' => $data->foto ?? null,
                                    'premioId' => $data->id,
                                ])
                            </td>
                            <td>
                       			@if (can('editar-cliente-premio-uif', false))
                                	<a href="{{route('edita_cliente_premio_uif', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-cliente-premio-uif', false))
                                <form action="{{route('elimina_cliente_premio_uif', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $cliente_premio_uifs->appends($filtrosQuery ?? [])->links() }}
@endsection
