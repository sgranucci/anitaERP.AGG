@extends("theme.$theme.layout")
@section('titulo')
    Stock de locales
@endsection

@section('styles')
@include('ventas.facturacion_local.partials.consulta_estilos')
@endsection

@section('contenido')
<div class="row fl-consulta-shell">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="fl-consulta-hero d-flex flex-wrap align-items-start justify-content-between">
            <div class="mb-2 mb-md-0" style="max-width: 46rem;">
                <h2><i class="fa fa-boxes mr-2"></i> Stock y precio del local</h2>
                <p>
                    Equivalente Anita <strong>c-stocklocal</strong>: stock por dep&oacute;sito / color / medida
                    y precio de venta seg&uacute;n la lista del local.
                    Por ahora el tilde deja Anita para probar; sin tilde lee solo el ERP.
                </p>
            </div>
            <div class="fl-consulta-hero-actions">
                @if (can('consultar-precios-local', false))
                    <a href="{{ route('facturacion_local_consulta_precios') }}" class="btn btn-light btn-sm mr-1">
                        <i class="fa fa-tags"></i> Todas las listas
                    </a>
                @endif
                <a href="{{ route('facturacion_local_locales') }}" class="btn btn-outline-light btn-sm">
                    <i class="fa fa-reply-all"></i> Locales
                </a>
            </div>
        </div>

        <div class="card card-info border-0 shadow-none" style="background: transparent;">
            <div class="card-body p-0">
                @include('ventas.facturacion_local.partials.consulta_articulo_form', [
                    'formId' => 'fl-stock-form',
                    'locales' => $locales,
                    'localId' => $localId,
                ])

                <div id="fl-stock-resultado" style="display:none;">
                    <div class="fl-kpi-row">
                        <div class="fl-kpi fl-kpi-articulo">
                            <div class="fl-kpi-label">Art&iacute;culo</div>
                            <div class="fl-kpi-value" id="fl-stock-sku">—</div>
                            <div class="fl-kpi-sub" id="fl-stock-desc"></div>
                        </div>
                        <div class="fl-kpi">
                            <div class="fl-kpi-label">Precio de venta</div>
                            <div class="fl-kpi-value is-precio" id="fl-stock-precio">—</div>
                            <div class="fl-kpi-sub" id="fl-stock-lista"></div>
                        </div>
                        <div class="fl-kpi">
                            <div class="fl-kpi-label">Saldo total</div>
                            <div class="fl-kpi-value" id="fl-stock-saldo">—</div>
                            <div class="fl-kpi-sub" id="fl-stock-saldo-hint">Unidades en Anita Local</div>
                        </div>
                    </div>

                    <div class="fl-panel">
                        <div class="fl-panel-head">
                            <h3><i class="fa fa-th mr-1"></i> Matriz de stock</h3>
                            <span class="badge badge-light text-dark" id="fl-stock-filas-count"></span>
                        </div>
                        <div class="fl-panel-body">
                            <div class="fl-matriz-wrap table-responsive">
                                <table class="table table-sm table-bordered fl-consulta-tabla" id="fl-stock-tabla">
                                    <thead id="fl-stock-thead"></thead>
                                    <tbody id="fl-stock-tbody"></tbody>
                                </table>
                            </div>
                            <p class="fl-consulta-origen mb-0" id="fl-stock-origen"></p>
                        </div>
                    </div>
                </div>

                <div id="fl-stock-vacio" class="fl-empty-state" style="display:none;">
                    <i class="fa fa-search"></i>
                    <div id="fl-stock-vacio-txt">Eleg&iacute; un local y un art&iacute;culo para consultar.</div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.stock.modalconsultaarticulo')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'fl-stock-overlay',
    'tituloId' => 'fl-stock-overlay-titulo',
    'subtituloId' => 'fl-stock-overlay-subtitulo',
    'titulo' => 'Consultando stock…',
    'subtitulo' => 'Lee Anita Local. Puede demorar unos segundos.',
])
@endsection

@section('scripts')
<script>
window.flConsultaCfg = {
    modo: 'stock',
    urlConsulta: @json(route('facturacion_local_api_stock')),
    formId: 'fl-stock-form',
    resultadoId: 'fl-stock-resultado',
    vacioId: 'fl-stock-vacio',
    vacioTxtId: 'fl-stock-vacio-txt',
    overlayId: 'fl-stock-overlay'
};
</script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/consulta_stock_precios.js') }}"></script>
@endsection
