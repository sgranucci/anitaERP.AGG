@extends("theme.$theme.layout")
@section('titulo') Grupos de conceptos @endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/grupo_concepto/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\GrupoConceptoSueldosListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('consultar_grupo_concepto_sueldos', GrupoConceptoSueldosListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Grupos de conceptos</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('crear-grupo-concepto-sueldos', false))
                        <form action="{{ route('sincronizar_grupo_concepto_sueldos') }}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Sincronizar grupos desde Anita (tabla grupo) y vincular emp_grp* del padrón?');">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-refresh"></i> Sincronizar desde Anita
                            </button>
                        </form>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-grupo-concepto-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => GrupoConceptoSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-grupo-concepto-sueldos',
                        'toggleId' => 'btn-toggle-filtros-grupo-concepto-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_grupo_concepto_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-grupo-concepto-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_grupo_concepto_sueldos') }}" id="form-filtros-grupo-concepto-sueldos" class="mb-0">
                @include('sueldos.grupo_concepto.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('sueldos.grupo_concepto.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_grupo_concepto_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>Empresa</th>
                            <th class="text-center">Conceptos</th>
                            <th>Origen</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td>{{ optional($data->empresa)->nombre ?? 'Todas' }}</td>
                            <td class="text-center">{{ $data->items_count }}</td>
                            <td>{{ $data->origen }}</td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-grupo-concepto-sueldos', false))
                                    <a href="{{ route('editar_grupo_concepto_sueldos', ['id' => $data->id] + $retornoListadoQuery) }}"
                                       class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-grupo-concepto-sueldos', false))
                                    <form action="{{ route('eliminar_grupo_concepto_sueldos', $data->id) }}" method="POST" class="d-inline form-eliminar">
                                        @csrf @method('DELETE')
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
