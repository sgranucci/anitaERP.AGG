@extends("theme.$theme.layout")
@section('titulo')
    Fallos de caja
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/fallocaja/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\FallocajaSueldosListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Fallos de caja</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('actualizar-fallocaja-sueldos', false))
                        <form action="{{route('sincronizar_fallocaja_sueldos')}}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Sincronizar los fallos de caja desde Anita? Solo se agregarán los que falten.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                            </button>
                        </form>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-fallocaja-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => FallocajaSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_fallocaja_sueldos'),
                        'placeholder' => 'Búsqueda rápida (tipo, sanción, orden)…',
                        'toggleTarget' => '#panel-filtros-fallocaja-sueldos',
                        'toggleId' => 'btn-toggle-filtros-fallocaja-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_fallocaja_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-fallocaja-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_fallocaja_sueldos') }}" id="form-filtros-fallocaja-sueldos" class="mb-0">
                @include('sueldos.fallocaja.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_fallocaja_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_fallocaja_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">Tipo</th>
                            <th class="width20">Orden</th>
                            <th class="text-right">Desde</th>
                            <th class="text-right">Hasta</th>
                            <th>Sanci&oacute;n</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->tipo }}</td>
                            <td>{{ $data->orden }}</td>
                            <td class="text-right">{{ number_format((float) $data->desde, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->hasta, 2, ',', '.') }}</td>
                            <td>{{ $data->sancion }}</td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-fallocaja-sueldos', false))
                                    <a href="{{route('editar_fallocaja_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-fallocaja-sueldos', false))
                                    <form action="{{route('eliminar_fallocaja_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
