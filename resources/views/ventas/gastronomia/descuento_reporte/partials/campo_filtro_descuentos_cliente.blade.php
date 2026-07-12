@php
    $valorFiltro = $filtros['codigos_descuento_cliente'] ?? '';
    $modoSecundario = in_array($filtros['agrupar_por'] ?? 'codigo_descuento', ['cliente_descuento', 'mozo_descuento', 'cliente_vip'], true);
@endphp
<div id="wrap-filtro-descuento-cliente" class="form-group row mb-2" @if(! $modoSecundario) style="display: none;" @endif>
    <label class="col-lg-2 control-label text-right pr-2" for="codigos_descuento_cliente">Filtro por cód. descuento</label>
    <div class="col-lg-8">
        <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
            <button type="button"
                title="Consultar códigos de descuento"
                class="btn btn-outline-secondary btn-sm consultadescuento-filtro-cliente">
                <i class="fa fa-search"></i>
            </button>
            <input type="text"
                name="codigos_descuento_cliente"
                id="codigos_descuento_cliente"
                class="form-control form-control-sm flex-grow-1"
                value="{{ $valorFiltro }}"
                placeholder="Opcional: 10, 500, 1500/1502, 1500-1505"
                autocomplete="off">
        </div>
        <p class="text-muted small mb-0 mt-1">
            Solo en modo <strong>cliente interno</strong>, <strong>mozo</strong> o <strong>cliente VIP</strong>: limita las ventas agrupadas a estos códigos de descuento de cabecera
            (comas o rangos con / o -). Use la lupa para agregar códigos al campo.
        </p>
        @if ($valorFiltro !== '' && ! empty($filtros['codigos_descuento_cliente_resueltos'] ?? []))
            <p class="text-muted small mb-0 mt-1">
                Códigos aplicados: {{ implode(', ', $filtros['codigos_descuento_cliente_resueltos']) }}
            </p>
        @endif
    </div>
</div>
