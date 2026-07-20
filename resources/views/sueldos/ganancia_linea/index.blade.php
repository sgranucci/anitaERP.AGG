@extends("theme.$theme.layout")
@section('titulo')
    Plan de l&iacute;neas Ganancias
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/ganancia_linea/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\GananciaLineaSueldosListadoFiltros; ?>
<?php use App\Models\Sueldos\Ganancia_Linea_Sueldos; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Plan de l&iacute;neas Ganancias</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-ganancia-linea-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => GananciaLineaSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_ganancia_linea_sueldos'),
                        'placeholder' => 'Búsqueda rápida (código, descripción)…',
                        'toggleTarget' => '#panel-filtros-ganancia-linea-sueldos',
                        'toggleId' => 'btn-toggle-filtros-ganancia-linea-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_ganancia_linea_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-ganancia-linea-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_ganancia_linea_sueldos') }}" id="form-filtros-ganancia-linea-sueldos" class="mb-0">
                @include('sueldos.ganancia_linea.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_ganancia_linea_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="width:70px">Orden</th>
                            <th class="width20">C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>Origen</th>
                            <th class="text-center" style="width:70px">Activo</th>
                            <th class="text-center" style="width:90px">Planilla</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td class="text-center">{{ $data->orden }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td>{{ Ganancia_Linea_Sueldos::ORIGENES[$data->origen] ?? $data->origen }}</td>
                            <td class="text-center">{{ $data->activo ? 'Sí' : 'No' }}</td>
                            <td class="text-center">{{ $data->va_planilla ? 'Sí' : 'No' }}</td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-ganancia-linea-sueldos', false))
                                    <a href="{{route('editar_ganancia_linea_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-ganancia-linea-sueldos', false))
                                    <form action="{{route('eliminar_ganancia_linea_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
