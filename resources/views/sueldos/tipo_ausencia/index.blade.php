@extends("theme.$theme.layout")
@section('titulo')
    Tipos de ausencia
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/tipo_ausencia/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\TipoAusenciaSueldosListadoFiltros; use App\Models\Sueldos\Tipo_Ausencia_Sueldos; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Tipos de ausencia</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-tipo-ausencia-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => TipoAusenciaSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_tipo_ausencia_sueldos'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-tipo-ausencia-sueldos',
                        'toggleId' => 'btn-toggle-filtros-tipo-ausencia-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_tipo_ausencia_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-tipo-ausencia-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_tipo_ausencia_sueldos') }}" id="form-filtros-tipo-ausencia-sueldos" class="mb-0">
                @include('sueldos.tipo_ausencia.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_tipo_ausencia_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_tipo_ausencia_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">C&oacute;digo</th>
                            <th>Nombre</th>
                            <th>Categor&iacute;a</th>
                            <th class="text-center" style="width:90px">Vacaci.</th>
                            <th class="text-center" style="width:90px">Paga</th>
                            <th class="text-center" style="width:90px">D&iacute;as</th>
                            <th class="text-center" style="width:80px">Tope/a&ntilde;o</th>
                            <th class="text-center" style="width:80px">Activo</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->codigo }}</td>
                            <td>
                                @if ($data->color)
                                    <span class="d-inline-block mr-1" style="width:10px;height:10px;border-radius:2px;background:{{ $data->color }}"></span>
                                @endif
                                {{ $data->nombre }}
                            </td>
                            <td>{{ Tipo_Ausencia_Sueldos::etiquetaCategoria($data->categoria) }}</td>
                            <td class="text-center">
                                @if ($data->afecta_saldo_vacaciones)
                                    <span class="badge badge-primary">S&iacute;</span>
                                @else
                                    <span class="badge badge-secondary">No</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($data->goza_sueldo)
                                    <span class="badge badge-success">S&iacute;</span>
                                @else
                                    <span class="badge badge-warning">No</span>
                                @endif
                            </td>
                            <td class="text-center">{{ Tipo_Ausencia_Sueldos::etiquetaTipoDias($data->tipo_dias) }}</td>
                            <td class="text-center">{{ $data->tope_dias_anio ?? '—' }}</td>
                            <td class="text-center">
                                @if ($data->activo)
                                    <span class="badge badge-success">S&iacute;</span>
                                @else
                                    <span class="badge badge-secondary">No</span>
                                @endif
                            </td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-tipo-ausencia-sueldos', false))
                                    <a href="{{route('editar_tipo_ausencia_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-tipo-ausencia-sueldos', false))
                                    <form action="{{route('eliminar_tipo_ausencia_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
