@extends("theme.$theme.layout")
@section('titulo')
    Clases de material
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/clasematerial/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Stock\ClasematerialListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Clases de material</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-clasematerial',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ClasematerialListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('clasematerial'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-clasematerial',
                        'toggleId' => 'btn-toggle-filtros-clasematerial',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_clasematerial', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-clases-material',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('clasematerial') }}" id="form-filtros-clasematerial" class="mb-0">
                @include('stock.clasematerial.partials.filtros_listado', [
                    'limpiarUrl' => route('clasematerial'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_clasematerial',
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
                       			@if (can('editar-clases-material', false))
                                	<a href="{{ route('editar_clasematerial', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-clases-material', false))
                                <form action="{{ route('eliminar_clasematerial', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
