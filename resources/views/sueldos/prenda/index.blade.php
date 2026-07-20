@extends("theme.$theme.layout")
@section('titulo')
    Prendas (indumentaria)
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/prenda/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\PrendaSueldosListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Prendas (indumentaria)</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('ver-configuracion-indumentaria', false) || can('editar-configuracion-indumentaria', false))
                        <a href="{{ route('config_indumentaria') }}" class="btn btn-outline-secondary btn-sm mr-1" title="Depósito de origen y tipo de transacción de las entregas">
                            <i class="fa fa-fw fa-cogs"></i> Configuración de entrega
                        </a>
                    @endif
                    @if (can('crear-prenda-sueldos', false))
                        <form action="{{route('sincronizar_prenda_sueldos')}}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Sincronizar prendas y variantes desde Anita? Solo se agregarán las que falten.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                            </button>
                        </form>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-prenda-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => PrendaSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_prenda_sueldos'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-prenda-sueldos',
                        'toggleId' => 'btn-toggle-filtros-prenda-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_prenda_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-prenda-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_prenda_sueldos') }}" id="form-filtros-prenda-sueldos" class="mb-0">
                @include('sueldos.prenda.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_prenda_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_prenda_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>Marca</th>
                            <th class="text-center" style="width:90px">EPP</th>
                            <th class="text-center" style="width:110px">Variantes</th>
                            <th class="text-center" style="width:80px">Activo</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td>{{ $data->marca }}</td>
                            <td class="text-center">
                                @if ($data->es_seguridad)
                                    <span class="badge badge-danger">EPP</span>
                                @else
                                    <span class="badge badge-secondary">No</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge badge-info">{{ $data->variantes_count ?? 0 }}</span>
                            </td>
                            <td class="text-center">
                                @if ($data->activo)
                                    <span class="badge badge-success">S&iacute;</span>
                                @else
                                    <span class="badge badge-secondary">No</span>
                                @endif
                            </td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-prenda-sueldos', false))
                                    <a href="{{route('editar_prenda_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-prenda-sueldos', false))
                                    <form action="{{route('eliminar_prenda_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
