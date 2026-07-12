@extends("theme.$theme.layout")
@section('titulo')
    Capex
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/presupuesto/capex/filtro.js")}}" type="text/javascript"></script>

<script>
    function eliminarCapex(event) {
        var opcion = confirm("Desea eliminar el Capex?");
        if(!opcion) {
            event.preventDefault();
        }
    }
</script>

@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Presupuesto\CapexListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Capex</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-capex',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => CapexListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_capex'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-capex',
                        'toggleId' => 'btn-toggle-filtros-capex',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_capex', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-capex',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_capex') }}" id="form-filtros-capex" class="mb-0">
                @include('presupuesto.capex.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_capex'),
                ])
            </form>
            <div class="card-body p-0">
                <div class="px-3 py-2 border-bottom">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'lista_capex',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                        <thead>
                            <tr>
                                <th class="width20">ID</th>
                                <th>Empresa</th>
                                <th>Presupuesto</th>
                                <th>Centro de Costo</th>
                                <th>Nombre</th>
                                <th>Detalle</th>
                                <th>Codigo de Proyecto</th>
                                <th>Nro. de Proyecto</th>
                                <th>Estado</th>
                                <th style="width: 15%;">Partidas</th>
                                <th class="width40" data-orderable="false"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($capex as $data)
                            @php
                                $esAnuladoFila = ($data->estado ?? '') === 'ANULADO';
                            @endphp
                            <tr @if($esAnuladoFila) class="table-secondary" @endif>
                                <td>{{ $data->id }}</td>
                                <td>
                                    @include('presupuesto.partials.celda_link_consulta', [
                                        'mostrarLinks' => true,
                                        'puede' => $puede_ver_empresa ?? false,
                                        'id' => $data->empresa_id ?? 0,
                                        'routeName' => 'editar_empresa',
                                        'texto' => $data->nombreempresa ?? '',
                                    ])
                                </td>
                                <td>
                                    @include('presupuesto.partials.celda_link_consulta', [
                                        'mostrarLinks' => true,
                                        'puede' => $puede_ver_presupuesto ?? false,
                                        'id' => $data->presupuesto_id ?? 0,
                                        'routeName' => 'editar_presupuesto',
                                        'texto' => $data->nombrepresupuesto ?? '',
                                    ])
                                </td>
                                <td>
                                    @include('presupuesto.partials.celda_link_consulta', [
                                        'mostrarLinks' => true,
                                        'puede' => $puede_ver_centrocosto ?? false,
                                        'id' => $data->centrocosto_id ?? 0,
                                        'routeName' => 'editar_centrocosto',
                                        'texto' => trim(($data->codigocentrocosto ?? '').' '.($data->nombrecentrocosto ?? '')),
                                    ])
                                </td>
                                <td>{{ $data->nombre ?? '' }}</td>
                                <td>{{ $data->detalle ?? '' }}</td>
                                <td>{{ $data->codigoproyecto }}</td>
                                <td>{{ $data->codigo }}</td>
                                <td>
                                    @include('presupuesto.capex.partials.estado_badge', ['estado' => $data->estado ?? ''])
                                </td>
                                <td>
                                    <ul class="mb-0 pl-3">
                                        @foreach($data->capex_partidas as $partida)

                                            @php $montoTotal = 0; @endphp
                                            @foreach($partida->capex_partida_montos as $monto)
                                                @php $montoTotal += $monto->monto; @endphp
                                            @endforeach

                                            <li>Nro.{{ $partida->codigo }} {{ $partida->nombre }} {{ $partida->monedas->abreviatura ?? '' }} {{ number_format($montoTotal, 2) }}</li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="text-nowrap">
                                    @if (can('editar-capex', false))
                                        <a href="{{ route('editar_capex', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-capex', false))
                                        <form action="{{ route('eliminar_capex', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                            @csrf @method("delete")
                                            <button type="submit" onclick="eliminarCapex(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">
                                        No hay registros CAPEX con los filtros seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($capex instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                    <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                        <span class="small text-muted mb-2 mb-md-0">
                            @if ($capex->total() > 0)
                                Mostrando {{ $capex->firstItem() }}–{{ $capex->lastItem() }} de {{ $capex->total() }} registros
                            @else
                                Sin registros
                            @endif
                        </span>
                        {{ $capex->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
