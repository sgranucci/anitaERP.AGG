{{--
    Búsqueda de factura origen por artículo del cliente (NC parcial mostrador).
    Vive dentro de #fce-nc-mostrador-wrap. F1/lupa = modal artículo; Buscar facturas lista ventas ERP.
--}}
@php
    $puedeGenerarNc = can('generar-nota-de-credito', false);
    $articuloNcFiltro = (int) ($articuloNcFiltro ?? 0);
    $articuloNcCodigo = $articuloNcCodigo ?? '';
    $articuloNcDescripcion = $articuloNcDescripcion ?? '';
    $puedeConsultarArticulo = \App\Support\Stock\ArticuloConsultaDesdeModal::puedeConsultar();
    $editUrlArticuloNc = ($articuloNcFiltro > 0 && $puedeConsultarArticulo)
        ? \App\Support\Stock\ArticuloConsultaDesdeModal::urlEditar($articuloNcFiltro)
        : '#';
    $urlGenerarNcBase = url('ventas/factura/generanotadecredito');
@endphp
@if ($puedeGenerarNc)
<div class="form-group row tm-articulo-campo mb-2" id="tm_articulo_nc_devolucion">
    <label for="nc_articulo_id_codigo" class="col-lg-3 control-label text-right pr-2">Art&iacute;culo</label>
    <div class="col-lg-8">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" id="nc_articulo_id" class="articulo_id" value="{{ $articuloNcFiltro }}">
            <button type="button" title="Consulta art&iacute;culos (F1)" class="btn-accion-tabla consultaarticulo flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeConsultarArticulo)
                <a href="{{ $editUrlArticuloNc }}" target="_blank" rel="noopener"
                    class="btn-accion-tabla btn-link-articulo tooltipsC flex-shrink-0 {{ $articuloNcFiltro > 0 ? '' : 'd-none' }}"
                    title="Consultar art&iacute;culo en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigoarticulo" id="nc_articulo_id_codigo"
                value="{{ $articuloNcCodigo }}" placeholder="SKU" autocomplete="off"
                title="SKU. F1 consulta; Enter resuelve. Luego Buscar facturas."
                style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control descripcionarticulo text-truncate" id="nc_articulo_id_descripcion"
                value="{{ $articuloNcDescripcion }}" placeholder="Descripci&oacute;n" readonly
                style="min-width: 0; flex: 1 1 auto;">
            <button type="button" id="btn-buscar-facturas-articulo" class="btn btn-outline-primary btn-sm flex-shrink-0"
                title="Lista facturas del cliente con este art&iacute;culo">
                <i class="fa fa-file-invoice"></i> Buscar facturas
            </button>
        </div>
        <small class="form-text text-muted">
            Si no sabe el comprobante, busque por art&iacute;culo del cliente. Al elegir una factura se cargan los &iacute;tems para devolver (parcial o total).
        </small>
    </div>
</div>
<script>
    window.NC_DEVOLUCION_URL_GENERAR = @json($urlGenerarNcBase);
</script>
@endif
