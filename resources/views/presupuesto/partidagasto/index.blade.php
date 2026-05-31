@extends("theme.$theme.layout")
@section('titulo')
    Partidas de Gastos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/presupuesto/partidagasto/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Presupuesto\PartidagastoListadoFiltros; ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Partidas de Gastos</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-partidagasto',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => PartidagastoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_partidagasto'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-partidagasto',
                        'toggleId' => 'btn-toggle-filtros-partidagasto',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_partidagasto'),
                        'nuevoRegistroCan' => 'crear-partida-gasto',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_partidagasto') }}" id="form-filtros-partidagasto" class="mb-0">
                @include('presupuesto.partidagasto.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_partidagasto'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_partidagasto',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Presupuesto</th>
                            <th>Escenario</th>
                            <th>Centro de Costo</th>
                            <th>Partida</th>
                            <th>Detalle</th>
                            <th>Articulo</th>
                            <th>Proveedor</th>
                            <th>Cuenta Contable</th>
                            <th>Moneda</th>
                            <th>Monto Total</th>
                            <th>Estado</th>
                            <th style="width: 15%;">Apertura</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($partidagasto as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombreempresa ?? ''}}</td>
                            <td>{{$data->nombrepresupuesto ?? ''}}</td>
                            <td>{{$data->nombreescenario ?? ''}}</td>
                            <td>{{$data->nombrecentrocosto ?? '' }}</td>
                            <td>{{$data->codigopartida ?? ''}}</td>
                            <td>{{$data->detalle ?? ''}}</td>
                            <td>{{$data->descripcionarticulo ?? ''}}</td>
                            <td>{{$data->nombreproveedor ?? ''}}</td>
                            <td>{{$data->codigocuentacontable}}-{{$data->nombrecuentacontable ?? ''}}</td>
                            <td>{{$data->abreviaturamoneda}}</td>
                            <td style="text-align: left;">
                                @php $montoTotal = 0; @endphp
                                @foreach($data->partidagasto_montos as $partida)
                                    @php $montoTotal += $partida->monto; @endphp
                                @endforeach                                
                                {{number_format($montoTotal,2)}}
                            </td>
                            <td>{{$data->estado}}</td>
                            <td>
                                <ul>
                                    @foreach($data->partidagasto_montos as $partida)
                                        <li>{{$partida->periodo}} {{number_format($partida->monto,2)}}</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td>
                       			@if (can('editar-partida-gasto', false))
                                	<a href="{{route('editar_partidagasto', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-partida-gasto', false))
                                <form action="{{route('eliminar_partidagasto', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $partidagasto->appends($filtrosQuery ?? [])->links() }}
@endsection
