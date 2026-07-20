@extends("theme.$theme.layout")
@section('titulo')
    Par&aacute;metros de liquidaci&oacute;n
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/parametro/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\ParametroSueldosListadoFiltros; use App\Models\Sueldos\Parametro_Sueldos; use App\Exports\Sueldos\ParametroSueldosListadoExport; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Par&aacute;metros de liquidaci&oacute;n</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-parametro-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ParametroSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_parametro_sueldos'),
                        'placeholder' => 'Búsqueda rápida (código, descripción, unidad)…',
                        'toggleTarget' => '#panel-filtros-parametro-sueldos',
                        'toggleId' => 'btn-toggle-filtros-parametro-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_parametro_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-parametro-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_parametro_sueldos') }}" id="form-filtros-parametro-sueldos" class="mb-0">
                @include('sueldos.parametro.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_parametro_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_parametro_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>Tipo</th>
                            <th>Unidad</th>
                            <th class="text-center" style="width:70px">Activo</th>
                            <th>Valor vigente</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td>{{ Parametro_Sueldos::TIPOS[$data->tipo] ?? $data->tipo }}</td>
                            <td>{{ $data->unidad }}</td>
                            <td class="text-center">
                                @if ($data->activo)
                                    <span class="badge badge-success">S&iacute;</span>
                                @else
                                    <span class="badge badge-secondary">No</span>
                                @endif
                            </td>
                            <td>{{ ParametroSueldosListadoExport::valorVigenteEtiqueta($data) }}</td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-parametro-sueldos', false))
                                    <a href="{{route('editar_parametro_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-parametro-sueldos', false))
                                    <form action="{{route('eliminar_parametro_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
