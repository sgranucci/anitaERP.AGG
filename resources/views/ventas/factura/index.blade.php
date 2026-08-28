@extends("theme.$theme.layout")
@section('titulo')
Comprobantes de Venta
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/factura/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php
use App\Helpers\biblioteca;
use App\Support\Ventas\FacturaListadoFiltros;
use App\Support\Ventas\FacturaListadoSupport;
use App\Support\Ventas\PedidoListadoSupport;
?>

@section('contenido')
@php
    $periodoTexto = FacturaListadoFiltros::formatearPeriodoTexto($filtros ?? []);
    $repartoTexto = FacturaListadoFiltros::formatearRepartoTexto($filtros ?? []);
    $ordenActual = FacturaListadoFiltros::normalizarOrden($filtros['orden'] ?? null);
    $qsOrdenReparto = FacturaListadoFiltros::paraQueryString(array_merge($filtros ?? [], [
        'orden' => FacturaListadoFiltros::ORDEN_REPARTO,
    ]));
    $qsOrdenId = FacturaListadoFiltros::paraQueryString(array_merge($filtros ?? [], [
        'orden' => FacturaListadoFiltros::ORDEN_ID,
    ]));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Comprobantes de venta</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <div class="btn-group btn-group-sm mr-2 mb-1" role="group" aria-label="Orden del listado">
                        <a href="{{ route('factura', $qsOrdenReparto) }}"
                           class="btn {{ $ordenActual === FacturaListadoFiltros::ORDEN_REPARTO ? 'btn-warning' : 'btn-outline-light' }}"
                           title="Agrupar por código de reparto (mayor a menor)">
                            <i class="fa fa-truck"></i> Por reparto
                        </a>
                        <a href="{{ route('factura', $qsOrdenId) }}"
                           class="btn {{ $ordenActual === FacturaListadoFiltros::ORDEN_ID ? 'btn-warning' : 'btn-outline-light' }}"
                           title="Últimas facturas primero (ID mayor a menor)">
                            <i class="fa fa-sort-numeric-down"></i> Por ID
                        </a>
                    </div>
                    @include('includes.ventas.link_mi_impresora', ['claseBtnMiImpresora' => 'btn btn-outline-secondary btn-sm mr-1'])
                    @if (can('listar-asignacion-remito-factura', false))
                        <a href="{{ route('asignacion_remito_factura') }}" class="btn btn-outline-secondary btn-sm mr-1" style="color:#fff;">
                            <i class="fa fa-link"></i> Asignar remitos
                        </a>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-factura',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => FacturaListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('factura'),
                        'placeholder' => 'Búsqueda rápida (cliente, comprobante, empresa, reparto)…',
                        'toggleTarget' => '#panel-filtros-factura',
                        'toggleId' => 'btn-toggle-filtros-factura',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_factura'),
                        'nuevoRegistroCan' => 'crear-factura',
                        'nuevoRegistroLabel' => 'Nuevo comprobante',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('factura') }}" id="form-filtros-factura" class="mb-0">
                @include('ventas.factura.partials.filtros_listado')
            </form>
            <div class="card-body table-responsive p-0">
                @if ($periodoTexto !== '')
                    <div class="px-3 py-2 text-muted small border-bottom">
                        <i class="fa fa-calendar-alt"></i> Per&iacute;odo: <strong>{{ $periodoTexto }}</strong>
                        @if ($repartoTexto !== '')
                            · <i class="fa fa-truck"></i> {{ $repartoTexto }}
                        @endif
                        · Orden: <strong>{{ FacturaListadoFiltros::formatearOrdenTexto($filtros ?? []) }}</strong>
                    </div>
                @endif
                <style>
                    #tabla-paginada tr.factura-subtotal-reparto,
                    #tabla-paginada tr.factura-subtotal-reparto td {
                        background-color: #F9E79F !important;
                        color: #17202A;
                        font-weight: 700;
                    }
                </style>
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_factura',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover table-sm" id="tabla-paginada" style="font-size: 0.8125rem;">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Fecha</th>
							<th>Comprobante</th>
							<th>Cliente</th>
							<th>Empresa</th>
                            <th class="text-right">Cajas</th>
                            <th class="text-right">Unidades</th>
                            <th class="text-right">Kilos</th>
                            <th>Reparto</th>
							<th class="text-right">Total</th>
                            <th data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
						@forelse($ventas as $comprobante)
                            @php $totales = FacturaListadoSupport::totalesFactura($comprobante); @endphp
    						<tr data-entry-id="{{ $comprobante->id }}">
        						<td>{{ $comprobante->id ?? '' }}</td>
								<td>{{ $comprobante->fecha ? date("d/m/Y", strtotime($comprobante->fecha)) : '' }}</td>
								<td>
									{{ $comprobante->tipotransacciones->nombre ?? '' }}&nbsp;
									{{ $comprobante->clientes->condicionivas->letra ?? '' }}
									{{ $comprobante->puntoventas->codigo ?? '' }}-{{ $comprobante->numerocomprobante }}
        						</td>
        						<td>{{ $comprobante->clientes->nombre ?? '' }}</td>
								<td>{{ $comprobante->puntoventas->empresas->nombre ?? '' }}</td>
                                <td class="text-right">{{ PedidoListadoSupport::formatearTotal($totales['caja']) }}</td>
                                <td class="text-right">{{ PedidoListadoSupport::formatearTotal($totales['pieza']) }}</td>
                                <td class="text-right">{{ PedidoListadoSupport::formatearTotal($totales['kilo']) }}</td>
                                <td>{{ FacturaListadoSupport::etiquetaReparto($comprobante) }}</td>
								<td class="text-right">{{ number_format($comprobante->total, 2, ',', '.') }}</td>
        						<td>
                       			@if (can('editar-factura', false))
                                	<a href="{{route('editar_factura', ['id' => $comprobante->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('generar-nota-de-credito', false))
									@if ($comprobante->total > 0)
                                		<a href="{{route('generar_notadecredito', ['id' => $comprobante->id])}}" class="btn-accion-tabla tooltipsC" title="Generar nota de crédito">
                                   		<i class="fa fa-undo text-danger"></i>
                                		</a>
									@endif
								@endif
                       			@if (can('listar-factura', false))
                                	<a href="{{route('lista_una_factura', ['id' => $comprobante->id])}}" class="btn-accion-tabla tooltipsC" title="Listar el comprobante por impresora">
                                   	<i class="fa fa-print"></i>
                                	</a>
                                	<a href="{{route('lista_una_factura_pdf', ['id' => $comprobante->id])}}" class="btn-accion-tabla tooltipsC" title="Listar el comprobante en PDF">
                                   	<i class="fas fa-file-pdf text-danger"></i>
                                	</a>
                                	<a href="{{route('lista_una_factura_copias', ['id' => $comprobante->id])}}" class="btn-accion-tabla tooltipsC" title="Imprimir eligiendo copias">
                                   	<i class="fa fa-copy"></i>
                                	</a>
								@endif
                            	</td>
                        	</tr>
                            @if (FacturaListadoSupport::esCierreReparto($comprobante, $totalesPorReparto ?? []))
                                @include('ventas.factura.partials.fila_subtotal_reparto', [
                                    'metaReparto' => FacturaListadoSupport::metaReparto($comprobante, $totalesPorReparto ?? []),
                                    'conAcciones' => true,
                                    'filtrosQuery' => $filtrosQuery ?? [],
                                ])
                            @endif
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-3">No se encontraron comprobantes con los filtros aplicados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $ventas->appends($filtrosQuery ?? [])->links() }}
@endsection
