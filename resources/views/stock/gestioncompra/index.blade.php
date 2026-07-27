@extends("theme.$theme.layout")
@section('titulo')
    Gestiones de compra
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/gestioncompra/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Stock\GestioncompraListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Gestiones de compra</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-gestioncompra',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => GestioncompraListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('gestioncompra'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-gestioncompra',
                        'toggleId' => 'btn-toggle-filtros-gestioncompra',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_gestioncompra', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-gestiones-compra',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('gestioncompra') }}" id="form-filtros-gestioncompra" class="mb-0">
                @include('stock.gestioncompra.partials.filtros_listado', [
                    'limpiarUrl' => route('gestioncompra'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_gestioncompra',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Cód. interno SIFAB</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Habilitado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo_interno_sifab }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->habilitado ? 'Sí' : 'No' }}</td>
                            <td>
                       			@if (can('editar-gestiones-compra', false))
                                	<a href="{{ route('editar_gestioncompra', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-gestiones-compra', false))
                                <form action="{{ route('eliminar_gestioncompra', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
            <div class="card-footer clearfix">
                {{ $datas->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
