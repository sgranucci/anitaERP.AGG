@extends("theme.$theme.layout")
@section('titulo')
    Obras sociales
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/obrasocial/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\ObrasocialSueldosListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Obras sociales</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('actualizar-obrasocial-sueldos', false))
                        <form action="{{route('sincronizar_obrasocial_sueldos')}}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Sincronizar las obras sociales desde Anita? Solo se agregarán las que falten.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                            </button>
                        </form>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-obrasocial-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ObrasocialSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_obrasocial_sueldos'),
                        'placeholder' => 'Búsqueda rápida (código, descripción, número)…',
                        'toggleTarget' => '#panel-filtros-obrasocial-sueldos',
                        'toggleId' => 'btn-toggle-filtros-obrasocial-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_obrasocial_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-obrasocial-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_obrasocial_sueldos') }}" id="form-filtros-obrasocial-sueldos" class="mb-0">
                @include('sueldos.obrasocial.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_obrasocial_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_obrasocial_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>N&uacute;mero</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td>{{ $data->numero }}</td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-obrasocial-sueldos', false))
                                    <a href="{{route('editar_obrasocial_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-obrasocial-sueldos', false))
                                    <form action="{{route('eliminar_obrasocial_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
