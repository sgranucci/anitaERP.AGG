@extends("theme.$theme.layout")
@section('titulo')
    Motivos de sanción
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/motivo_sancion/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\MotivoSancionSueldosListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Motivos de sanción</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.sueldos.boton-manual')
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-motivo-sancion-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => MotivoSancionSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_motivo_sancion_sueldos'),
                        'placeholder' => 'Búsqueda rápida (código, nombre)…',
                        'toggleTarget' => '#panel-filtros-motivo-sancion-sueldos',
                        'toggleId' => 'btn-toggle-filtros-motivo-sancion-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_motivo_sancion_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-motivo-sancion-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_motivo_sancion_sueldos') }}" id="form-filtros-motivo-sancion-sueldos" class="mb-0">
                @include('sueldos.motivo_sancion.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_motivo_sancion_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_motivo_sancion_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">Código</th>
                            <th>Nombre</th>
                            <th class="text-center">Activo</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td class="text-center">{{ $data->activo ? 'Sí' : 'No' }}</td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-motivo-sancion-sueldos', false))
                                    <a href="{{route('editar_motivo_sancion_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-motivo-sancion-sueldos', false))
                                    <form action="{{route('eliminar_motivo_sancion_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
