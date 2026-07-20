@extends("theme.$theme.layout")
@section('titulo')
    Categorías de sueldos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/categoria/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\CategoriaSueldosListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Categorías de sueldos</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('actualizar-categoria-sueldos', false))
                        <form action="{{route('sincronizar_categoria_sueldos')}}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Sincronizar las categorías desde Anita? Solo se agregarán las que falten y sus bases iniciales.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                            </button>
                        </form>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-categoria-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => CategoriaSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_categoria_sueldos'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-categoria-sueldos',
                        'toggleId' => 'btn-toggle-filtros-categoria-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_categoria_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-categoria-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_categoria_sueldos') }}" id="form-filtros-categoria-sueldos" class="mb-0">
                @include('sueldos.categoria.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_categoria_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_categoria_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>Origen de las bases</th>
                            <th>Bases vigentes</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        @php $bases = $data->bases_vigentes ?? []; @endphp
                        <tr>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td>{{ $origenLabels[$data->origen_bases] ?? $data->origen_bases }}</td>
                            <td>
                                @if (count($bases))
                                    @foreach ($bases as $b)
                                        <div class="small">
                                            <span class="text-muted">{{ $b['nombrebase_codigo'] }} {{ $b['nombrebase_descripcion'] }}:</span>
                                            <strong>{{ $b['valor_fmt'] }}</strong>
                                            <span class="text-muted">(desde {{ $b['fecha_vigencia_fmt'] }})</span>
                                        </div>
                                    @endforeach
                                @else
                                    <span class="text-muted font-italic small">Sin bases vigentes</span>
                                @endif
                            </td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-categoria-sueldos', false))
                                    <a href="{{route('editar_categoria_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-categoria-sueldos', false))
                                    <form action="{{route('eliminar_categoria_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@endsection
