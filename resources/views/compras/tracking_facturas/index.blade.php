@extends("theme.$theme.layout")

@section('titulo')
    Tracking de Facturas
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tracking-facturas.css') }}?v=20260906-workspace2">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/tracking_facturas/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/erp-workspace-panel.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/includes/erp-workspace-panel.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Compras\Tracking\TrackingFacturasListadoFiltros;

    $rutaIndex = route('tracking_facturas');
    $limpiarUrl = route('tracking_facturas', TrackingFacturasListadoFiltros::paraQueryStringExternos($filtros ?? []));
@endphp

@section('contenido')
<div class="row tracking-facturas">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="tf-header">
            <h1><i class="fa fa-search-plus"></i> Tracking de Facturas</h1>
            <div class="tf-header-acciones">
                @include('includes.listado.filtros_toolbar', [
                    'formId' => 'form-filtros-tracking-facturas',
                    'filtroValor' => $filtros['valor'] ?? '',
                    'tieneCriterios' => TrackingFacturasListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                    'limpiarUrl' => $limpiarUrl,
                    'placeholder' => 'Número, proveedor, CUIT…',
                    'toggleTarget' => '#panel-filtros-tracking-facturas',
                    'toggleId' => 'btn-toggle-filtros-tracking-facturas',
                    'inputId' => 'filtro_valor',
                ])
                <form action="{{ route('tracking_facturas_sincronizar_pagina') }}" method="POST" class="d-inline">
                    @csrf
                    @foreach ($filtrosQuery ?? [] as $clave => $valor)
                        <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                    @endforeach
                    <input type="hidden" name="page" value="{{ $datas->currentPage() }}">
                    <button type="submit" class="tf-ghost-btn"
                            title="Vuelve a resolver PDF y saldo de los comprobantes de esta página consultando el Anita">
                        <i class="fa fa-refresh"></i> Actualizar esta página
                    </button>
                </form>
            </div>
        </div>

        <div class="tf-panel">
            <p class="tf-intro">
                Seguimiento de facturas, notas de crédito, notas de débito y recibos cargados, estén o no
                atados a una orden de compra. El PDF de cada comprobante queda disponible para verlo desde acá.
            </p>

            @include('compras.tracking_facturas.partials.resumen')
            @include('compras.tracking_facturas.partials.filtros_externos')
            @include('compras.tracking_facturas.partials.segmentos')

            <form method="get" action="{{ $rutaIndex }}" id="form-filtros-tracking-facturas" class="mb-0">
                @include('compras.tracking_facturas.partials.filtros_contexto')
                @include('compras.tracking_facturas.partials.filtros_listado', ['limpiarUrl' => $limpiarUrl])
            </form>

            <div class="px-3 pt-2">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_tracking_facturas',
                    'queryparams' => $filtrosQuery ?? [],
                ])
            </div>

            <div class="table-responsive p-0">
                @include('compras.tracking_facturas.partials.tabla')
            </div>
        </div>
    </div>
</div>

{{ $datas->appends($filtrosQuery ?? [])->links() }}
@endsection
