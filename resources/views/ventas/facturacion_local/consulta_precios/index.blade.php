@extends("theme.$theme.layout")
@section('titulo')
    Consulta de precios
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
                <h2><i class="fa fa-tags mr-2"></i> Precios y stock del art&iacute;culo</h2>
                <p>
                    Equivalente Anita <strong>c-articulo</strong>: precio de la lista del local,
                    resto de listas ERP y stock (Anita o ERP seg&uacute;n el tilde).
                    Por ahora el tilde deja Anita para probar.
                </p>
            </div>
            <div class="fl-consulta-hero-actions">
                @if (can('consultar-stock-local', false))
                    <a href="{{ route('facturacion_local_stock') }}" class="btn btn-light btn-sm mr-1">
                        <i class="fa fa-boxes"></i> Matriz de stock
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
                    'formId' => 'fl-precios-form',
                    'locales' => $locales,
                    'localId' => $localId,
                ])

                <div id="fl-precios-resultado" style="display:none;">
                    <div class="fl-kpi-row">
                        <div class="fl-kpi fl-kpi-articulo">
                            <div class="fl-kpi-label">Art&iacute;culo</div>
                            <div class="fl-kpi-value" id="fl-precios-sku">—</div>
                            <div class="fl-kpi-sub" id="fl-precios-desc"></div>
                        </div>
                        <div class="fl-kpi">
                            <div class="fl-kpi-label">Precio del local</div>
                            <div class="fl-kpi-value is-precio" id="fl-precios-precio">—</div>
                            <div class="fl-kpi-sub" id="fl-precios-lista"></div>
                        </div>
                        <div class="fl-kpi">
                            <div class="fl-kpi-label">Saldo total</div>
                            <div class="fl-kpi-value" id="fl-precios-saldo">—</div>
                            <div class="fl-kpi-sub" id="fl-precios-saldo-hint">Unidades en Anita Local</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-7 mb-3 mb-lg-0">
                            <div class="fl-panel h-100">
                                <div class="fl-panel-head">
                                    <h3><i class="fa fa-warehouse mr-1"></i> Stock</h3>
                                    <span class="badge badge-light text-dark" id="fl-precios-stock-count"></span>
                                </div>
                                <div class="fl-panel-body">
                                    <div class="fl-matriz-wrap table-responsive">
                                        <table class="table table-sm table-bordered fl-consulta-tabla" id="fl-precios-tabla-stock">
                                            <thead>
                                                <tr>
                                                    <th>N.Dep.</th>
                                                    <th>Color</th>
                                                    <th>Medida</th>
                                                    <th class="text-right">Cantidad</th>
                                                </tr>
                                            </thead>
                                            <tbody id="fl-precios-tbody-stock"></tbody>
                                        </table>
                                    </div>
                                    <p class="fl-consulta-origen mb-0" id="fl-precios-origen"></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="fl-panel h-100">
                                <div class="fl-panel-head">
                                    <h3><i class="fa fa-dollar-sign mr-1"></i> Precios por lista</h3>
                                    <span class="badge badge-success" id="fl-precios-lista-activa-badge" style="display:none;">Lista del local</span>
                                </div>
                                <div class="fl-panel-body">
                                    <div class="fl-matriz-wrap table-responsive">
                                        <table class="table table-sm table-bordered fl-consulta-tabla">
                                            <thead>
                                                <tr>
                                                    <th>Lista</th>
                                                    <th class="text-right">Precio</th>
                                                    <th>Vigencia</th>
                                                </tr>
                                            </thead>
                                            <tbody id="fl-precios-tbody-listas"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="fl-precios-vacio" class="fl-empty-state" style="display:none;">
                    <i class="fa fa-tags"></i>
                    <div id="fl-precios-vacio-txt">Eleg&iacute; un local y un art&iacute;culo para consultar.</div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.stock.modalconsultaarticulo')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'fl-precios-overlay',
    'tituloId' => 'fl-precios-overlay-titulo',
    'subtituloId' => 'fl-precios-overlay-subtitulo',
    'titulo' => 'Consultando precios…',
    'subtitulo' => 'Lee Anita Local y listas ERP. Puede demorar unos segundos.',
])
@endsection

@section('scripts')
<script>
window.flConsultaCfg = {
    modo: 'precios',
    urlConsulta: @json(route('facturacion_local_api_precios')),
    formId: 'fl-precios-form',
    resultadoId: 'fl-precios-resultado',
    vacioId: 'fl-precios-vacio',
    vacioTxtId: 'fl-precios-vacio-txt',
    overlayId: 'fl-precios-overlay'
};
</script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/consulta_stock_precios.js') }}"></script>
@endsection
