@extends("theme.$theme.layout")
@section('titulo')
    Tablas de antigüedad
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/antiguedad_tabla/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\AntiguedadTablaSueldosListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Tablas de antigüedad</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('crear-antiguedad-tabla-sueldos', false))
                        <form method="post" action="{{ route('sincronizar_antiguedad_tabla_sueldos') }}" class="mr-2 mb-0"
                              onsubmit="return confirm('¿Sincronizar tramos desde Anita (antmov)?\nSe reemplazan los tramos de cada código.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm" title="Trae antmov de Anita">
                                <i class="fa fa-cloud-download-alt"></i> Sync Anita
                            </button>
                        </form>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-antiguedad-tabla-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => AntiguedadTablaSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_antiguedad_tabla_sueldos'),
                        'placeholder' => 'Búsqueda rápida (código, descripción)…',
                        'toggleTarget' => '#panel-filtros-antiguedad-tabla-sueldos',
                        'toggleId' => 'btn-toggle-filtros-antiguedad-tabla-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_antiguedad_tabla_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-antiguedad-tabla-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_antiguedad_tabla_sueldos') }}" id="form-filtros-antiguedad-tabla-sueldos" class="mb-0">
                @include('sueldos.antiguedad_tabla.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_antiguedad_tabla_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_antiguedad_tabla_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th class="text-center" style="width:90px">Tramos</th>
                            <th class="text-center" style="width:70px">Activo</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td class="text-center">{{ $data->tramos_count }}</td>
                            <td class="text-center">
                                @if ($data->activo)
                                    <span class="badge badge-success">S&iacute;</span>
                                @else
                                    <span class="badge badge-secondary">No</span>
                                @endif
                            </td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-antiguedad-tabla-sueldos', false))
                                    <a href="{{route('editar_antiguedad_tabla_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-antiguedad-tabla-sueldos', false))
                                    <a href="#"
                                       data-id="{{ $data->id }}"
                                       data-url="{{ route('eliminar_antiguedad_tabla_sueldos', ['id' => $data->id]) }}"
                                       class="btn-accion-tabla eliminar-fila tooltipsC"
                                       title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </a>
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
