@extends("theme.$theme.layout")
@section('titulo')
    Corridas de liquidaci&oacute;n
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/liquidacion/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\LiquidacionSueldosListadoFiltros; use App\Models\Sueldos\Liquidacion_Sueldos; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $estadoBadge = [
        'borrador' => 'secondary', 'calculada' => 'info', 'revisada' => 'primary',
        'cerrada' => 'success', 'contabilizada' => 'dark', 'pagada' => 'success', 'anulada' => 'danger',
    ];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Corridas de liquidaci&oacute;n</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('crear-liquidacion-sueldos', false))
                        <form action="{{ route('sincronizar_liquidacion_sueldos') }}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Sincronizar liquidaciones maeliq + novedades desde Anita?\nEmpresa 1 · mael_fecha_liq &gt;= 20260700');">
                            @csrf
                            <input type="hidden" name="empresa_id" value="1">
                            <input type="hidden" name="fecha_liq_desde" value="20260700">
                            <button type="submit" class="btn btn-outline-secondary btn-sm" title="Trae maeliq y luego novedades">
                                <i class="fa fa-cloud-download-alt"></i> Sync Anita (julio)
                            </button>
                        </form>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-liquidacion-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => LiquidacionSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_liquidacion_sueldos'),
                        'placeholder' => 'Búsqueda rápida (N°, descripción, período)…',
                        'toggleTarget' => '#panel-filtros-liquidacion-sueldos',
                        'toggleId' => 'btn-toggle-filtros-liquidacion-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_liquidacion_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-liquidacion-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_liquidacion_sueldos') }}" id="form-filtros-liquidacion-sueldos" class="mb-0">
                @include('sueldos.liquidacion.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_liquidacion_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_liquidacion_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th style="width:70px">N&deg;</th>
                            <th>Empresa</th>
                            <th>Descripci&oacute;n</th>
                            <th>Tipo</th>
                            <th class="text-center" style="width:90px">Per&iacute;odo</th>
                            <th class="text-center" style="width:100px">Pago</th>
                            <th class="text-center" style="width:110px">Estado</th>
                            <th class="text-right" style="width:120px">Neto</th>
                            <th class="text-nowrap" style="width:120px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->numero }}</td>
                            <td>{{ optional($data->empresa)->nombre }}</td>
                            <td>
                                {{ $data->descripcion }}
                                @if ($data->simulacion)<span class="badge badge-warning ml-1">Simulación</span>@endif
                            </td>
                            <td>{{ $data->tipoLabel() }}</td>
                            <td class="text-center">{{ $data->periodo_mes ? sprintf('%02d/%04d', $data->periodo_mes, $data->periodo_anio) : $data->periodo }}</td>
                            <td class="text-center">{{ optional($data->fecha_pago)->format('d/m/Y') }}</td>
                            <td class="text-center">
                                <span class="badge badge-{{ $estadoBadge[$data->estado] ?? 'secondary' }}">{{ $data->estadoLabel() }}</span>
                            </td>
                            <td class="text-right">{{ number_format((float) $data->total_neto, 2, ',', '.') }}</td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-liquidacion-sueldos', false) && $data->esEditable())
                                    <a href="{{route('editar_liquidacion_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <form action="{{route('calcular_liquidacion_sueldos', ['id' => $data->id])}}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn-accion-tabla tooltipsC" title="{{ $data->estado === 'borrador' ? 'Calcular corrida' : 'Recalcular corrida' }}">
                                            <i class="fa fa-calculator text-info"></i>
                                        </button>
                                    </form>
                                @endif
                                @if (can('listar-liquidacion-sueldos', false) && (int) $data->cantidad_recibos > 0)
                                    <a href="{{route('resultado_liquidacion_sueldos', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Ver recibos calculados">
                                        <i class="fa fa-file-invoice-dollar text-primary"></i>
                                    </a>
                                @endif
                                @if (can('cerrar-liquidacion-sueldos', false) && $data->estado === 'calculada')
                                    <form action="{{route('estado_liquidacion_sueldos', ['id' => $data->id])}}" method="POST" class="d-inline">
                                        @csrf <input type="hidden" name="estado" value="cerrada">
                                        <button type="submit" class="btn-accion-tabla tooltipsC" title="Cerrar corrida"><i class="fa fa-lock text-success"></i></button>
                                    </form>
                                @endif
                                @if (can('cerrar-liquidacion-sueldos', false) && $data->estado === 'cerrada' && ! $data->contabilizado)
                                    <form action="{{route('estado_liquidacion_sueldos', ['id' => $data->id])}}" method="POST" class="d-inline">
                                        @csrf <input type="hidden" name="estado" value="reabrir">
                                        <button type="submit" class="btn-accion-tabla tooltipsC" title="Reabrir corrida"><i class="fa fa-unlock text-warning"></i></button>
                                    </form>
                                @endif
                                @if (can('borrar-liquidacion-sueldos', false) && $data->esEditable())
                                    <form action="{{route('eliminar_liquidacion_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
                                        @csrf @method("delete")
                                        <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
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
