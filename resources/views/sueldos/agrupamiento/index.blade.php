@extends("theme.$theme.layout")
@section('titulo')
    Agrupamientos de sueldos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/agrupamiento/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\AgrupamientoSueldosListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $fallos = $fallosPorTipo ?? [];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Agrupamientos de sueldos</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('actualizar-agrupamiento-sueldos', false))
                        <form action="{{route('sincronizar_agrupamiento_sueldos')}}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Sincronizar los agrupamientos desde Anita? Solo se agregarán los que falten.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                            </button>
                        </form>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-agrupamiento-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => AgrupamientoSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_agrupamiento_sueldos'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-agrupamiento-sueldos',
                        'toggleId' => 'btn-toggle-filtros-agrupamiento-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_agrupamiento_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-agrupamiento-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_agrupamiento_sueldos') }}" id="form-filtros-agrupamiento-sueldos" class="mb-0">
                @include('sueldos.agrupamiento.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_agrupamiento_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_agrupamiento_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>Fallo aplicado</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        @php
                            $tramos = $data->fallo_tipo ? ($fallos[$data->fallo_tipo] ?? []) : [];
                            $tooltip = collect($tramos)->map(fn ($t) => $t['linea'])->implode(' | ');
                        @endphp
                        <tr>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td>
                                @if ($data->fallo_tipo)
                                    <span class="badge badge-light border text-body tooltipsC"
                                          @if ($tooltip !== '') title="{{ $tooltip }}" @endif>
                                        <i class="fa fa-gavel text-muted"></i> {{ $data->fallo_tipo }}
                                    </span>
                                    @if (count($tramos))
                                        <span class="small text-muted d-block">{{ count($tramos) }} tramo(s) de sanción</span>
                                    @endif
                                @else
                                    <span class="text-muted font-italic small">Sin fallo</span>
                                @endif
                            </td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-agrupamiento-sueldos', false))
                                    <a href="{{route('editar_agrupamiento_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-agrupamiento-sueldos', false))
                                    <form action="{{route('eliminar_agrupamiento_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
