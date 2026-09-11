@extends("theme.$theme.layout")
@section('titulo')
Requisiciones
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/compras/ordencompra-ui.css') }}?v={{ @filemtime(public_path('assets/css/compras/ordencompra-ui.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/compras/requisicion-ui.css') }}?v={{ @filemtime(public_path('assets/css/compras/requisicion-ui.css')) ?: time() }}">
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/centrocosto-arbol-modal.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/centrocosto-arbol-modal.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/centrocosto/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/enviar-arbol.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/volver-compras.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/marcar-cumplida.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/confirmar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/confirmar.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/erp-workspace-panel.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/includes/erp-workspace-panel.js')) ?: time() }}" type="text/javascript"></script>
@include('compras.requisicion.partials.banner_confirmando_styles')
@include('compras.requisicion.partials.banner_enviando_arbol_styles')
@include('compras.requisicion.partials.comprobantes_asociados_script')
@endsection

<?php use App\Support\Compras\RequisicionListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
@include('compras.requisicion.partials.comprobantes_asociados_modal')
@include('compras.requisicion.partials.modal_firmante_retome_arbol')
@include('compras.requisicion.partials.modal_confirmar_envio_arbol')
@include('compras.requisicion.partials.modal_centrocosto_retome_arbol')

<div class="row oc-ui rq-ui erp-ws-host">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="oc-header">
            <h1><i class="fa fa-file-text-o"></i> Requisiciones</h1>
            <div class="oc-header-acciones">
                @include('includes.compras.boton-manual')
                @if (can('seguimiento-aprobacion-requisicion', false))
                    <a href="{{ route('seguimiento_aprobacion_requisicion') }}"
                       class="btn btn-outline-warning btn-sm"
                       title="Tablero de requisiciones pendientes de aprobación">
                        <i class="fas fa-tasks"></i> Seguimiento aprobación
                    </a>
                @endif
                @if (can('listar-kpi-compras', false))
                    <a href="{{ route('consultar_kpi_compras', ['origen' => 'requisicion']) }}"
                       class="btn btn-outline-success btn-sm"
                       title="Tablero de KPIs de proceso y productividad">
                        <i class="fas fa-chart-line"></i> KPIs
                    </a>
                @endif
                @include('includes.listado.filtros_toolbar', [
                    'formId' => 'form-filtros-requisicion',
                    'filtroValor' => $filtros['valor'] ?? '',
                    'tieneCriterios' => RequisicionListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                    'limpiarUrl' => route('consultar_requisicion', array_merge(
                        RequisicionListadoFiltros::paraQueryStringEmpresa($filtros ?? []),
                        ['limpiar_filtros' => 1]
                    )),
                    'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                    'toggleTarget' => '#panel-filtros-requisicion',
                    'toggleId' => 'btn-toggle-filtros-requisicion',
                    'inputId' => 'filtro_valor',
                    'nuevoRegistroUrl' => route('crear_requisicion', $retornoListadoQuery),
                    'nuevoRegistroCan' => 'crear-requisicion',
                    'nuevoRegistroLabel' => 'Nueva requisición',
                ])
            </div>
        </div>

        <div class="oc-panel">
            <p class="oc-intro">
                Circuito de requisición: del pedido a la aprobación, la orden de compra y el cumplimiento. Filtre por estado, empresa o texto; abra la RQ en solapa sin salir del listado.
            </p>

            @include('compras.requisicion.partials.resumen_index')
            @include('compras.requisicion.partials.segmentos_estado')
            @include('compras.requisicion.partials.filtros_externos', [
                'exportRuta' => 'listar_requisicion',
                'exportQueryparams' => $filtrosQuery ?? [],
            ])

            <form method="get" action="{{ route('consultar_requisicion') }}" id="form-filtros-requisicion" class="mb-0">
                @include('compras.requisicion.partials.filtros_listado')
            </form>

            <div class="table-responsive p-0">
                <table class="table table-hover oc-grilla mb-0" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Solicitante</th>
                            <th>Fecha</th>
                            <th>Empresa</th>
                            <th>Centro costo</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th class="text-right">Total</th>
                            <th>Ítems</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requisicion as $data)
                            @php
                                $esProvisorioFila = ($data->estado ?? '') === ($estado_provisorio ?? 'PROVISORIO');
                                $items = $data->requisicion_articulos ?? collect();
                                $itemsCount = $items->count();
                                $primerItem = $items->first();
                                $primerDesc = trim(implode(' ', array_filter([
                                    optional(optional($primerItem)->articulos)->sku,
                                    optional(optional($primerItem)->articulos)->descripcion,
                                ])));
                            @endphp
                            <tr data-ws-id="{{ $data->id }}" @if($esProvisorioFila) class="rq-fila-provisorio" @endif>
                                <td>
                                    <span class="oc-numero">{{ $data->numerorequisicion }}</span>
                                    <span class="oc-meta">ID {{ $data->id }}</span>
                                </td>
                                <td>{{ $data->nombreusuario ?? '—' }}</td>
                                <td class="text-nowrap">{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '—' }}</td>
                                <td>{{ $data->nombreempresa }}</td>
                                <td>{{ $data->nombrecentrocosto }}</td>
                                <td class="oc-proveedor">{{ $data->nombreproveedor ?: '—' }}</td>
                                <td>
                                    @include('compras.requisicion.partials.estado_badge', ['estado' => $data->estado ?? ''])
                                </td>
                                <td class="oc-num">
                                    {{ number_format((float) ($data->monto ?? 0), 2, ',', '.') }}
                                    <span class="oc-meta">{{ $data->monedacabecera_abreviatura ?? '' }}</span>
                                </td>
                                <td class="rq-items">
                                    @if ($itemsCount > 0)
                                        <strong>{{ $itemsCount }}</strong>
                                        @if ($primerDesc !== '')
                                            <span class="oc-meta">{{ $primerDesc }}{{ $itemsCount > 1 ? '…' : '' }}</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @include('compras.requisicion.partials.acciones_grilla')
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="oc-vacio">
                                    <i class="fa fa-inbox"></i>
                                    <div class="oc-vacio-titulo">No hay requisiciones para este filtro</div>
                                    Ajuste el estado, la empresa o el texto de búsqueda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (method_exists($requisicion, 'links'))
            <div class="oc-footer">
                {{ $requisicion->appends($filtrosQuery ?? [])->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
