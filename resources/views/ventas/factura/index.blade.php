@extends("theme.$theme.layout")
@section('titulo')
Comprobantes de Venta
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/factura/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Ventas\FacturaListadoFiltros; ?>

@section('contenido')
@php
    $periodoTexto = FacturaListadoFiltros::formatearPeriodoTexto($filtros ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Comprobantes de venta</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-factura',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => FacturaListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('factura'),
                        'placeholder' => 'Búsqueda rápida (cliente, comprobante, empresa)…',
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
                    </div>
                @endif
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_factura',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover table-sm" id="tabla-paginada" style="font-size: 0.8125rem;">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Fecha</th>
							<th>Comprobante</th>
							<th>Cliente</th>
							<th>Empresa</th>
							<th class="text-right">Total</th>
                            <th data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
						@forelse($ventas as $comprobante)
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
                                	<a href="{{route('lista_una_factura', ['id' => $comprobante->id])}}" class="btn-accion-tabla tooltipsC" title="Listar el Comprobante de Venta">
                                   	<i class="fa fa-print"></i>
                                	</a>
								@endif
                            	</td>
                        	</tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">No se encontraron comprobantes con los filtros aplicados.</td>
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
