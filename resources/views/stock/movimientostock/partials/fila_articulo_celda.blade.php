@php
    $articuloId = (int) ($articuloId ?? 0);
    $sku = (string) ($sku ?? '');
    $descripcion = (string) ($descripcion ?? '');
    $puedeConsultarArticulo = \App\Support\Stock\ArticuloConsultaDesdeModal::puedeConsultar();
    $puedeVerKardex = \App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar();
@endphp
<div class="celda-articulo-ms-wrapper">
    <div class="celda-articulo-ms d-flex align-items-center flex-nowrap mb-0">
        @if($puedeConsultarArticulo)
        <button type="button" title="Consulta artículos (F1)" class="btn-accion-tabla consultaarticulo flex-shrink-0" style="padding:1px 4px;">
            <i class="fa fa-search text-primary"></i>
        </button>
        @endif
        @if($puedeVerKardex)
        <button type="button" title="Kardex de stock" class="btn-accion-tabla btn-kardex-articulo-linea flex-shrink-0 {{ ($articuloId ?? 0) > 0 ? '' : 'd-none' }}" style="padding:1px 4px;" @if(($articuloId ?? 0) <= 0) disabled @endif>
            <i class="fa fa-list-alt text-info"></i>
        </button>
        @endif
        <a href="{{ $articuloId > 0 ? route('editar_articulo', ['id' => $articuloId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
           class="btn btn-xs btn-link-articulo {{ $articuloId > 0 ? '' : 'd-none' }} flex-shrink-0"
           target="_blank" rel="noopener" title="Consultar artículo">
            <i class="fa fa-external-link text-primary"></i>
        </a>
        <input type="text"
               class="codigoarticulo form-control form-control-sm flex-grow-1"
               value="{{ $sku }}"
               autocomplete="off"
               placeholder="SKU">
    </div>
    <input type="hidden" class="ms-articulo-compra-elegido" value="">
    <div class="ms-conversion-formula small text-primary mt-1 d-none" aria-live="polite"></div>
</div>
