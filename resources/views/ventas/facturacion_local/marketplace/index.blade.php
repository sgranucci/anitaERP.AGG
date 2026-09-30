@extends("theme.$theme.layout")
@section('titulo')
    Marketplaces
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/marketplace/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Ventas\FacturacionLocal\MarketplaceListadoFiltros;
    $limpiarUrl = route('facturacion_local_marketplaces');
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Marketplaces</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-marketplace',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => MarketplaceListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-marketplace',
                        'toggleId' => 'btn-toggle-filtros-marketplace',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_marketplace'),
                        'nuevoRegistroCan' => 'crear-marketplace-facturacion-local',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('facturacion_local_marketplaces') }}" id="form-filtros-marketplace" class="mb-0">
                @include('ventas.facturacion_local.marketplace.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_marketplace',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    @include('ventas.facturacion_local.marketplace.partials.tabla_datos', [
                        'presentacion' => 'pantalla',
                        'datas' => $datas,
                    ])
                </table>
            </div>
            <div class="card-footer">
                {{ $datas->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
