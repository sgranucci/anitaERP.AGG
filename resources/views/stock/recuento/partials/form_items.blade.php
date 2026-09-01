@php
    use App\Support\Stock\RecuentoItemsRequestSupport;

    $soloLectura = $soloLectura ?? false;
    $importLineas = session('recuento_import_lineas', []);
    $lineasOld = RecuentoItemsRequestSupport::lineasDesdeOldInput(old('items_json', ''), old() ?? []);
    $lineas = ($lineasOld !== null && $lineasOld !== [])
        ? RecuentoItemsRequestSupport::enriquecerLineasConArticulos($lineasOld)
        : (count($importLineas) ? $importLineas : null);
    if ($lineas === null && isset($recuento)) {
        $lineas = $recuento->items->map(fn ($i) => [
            'recuento_item_id' => $i->id,
            'articulo_id' => $i->articulo_id,
            'sku' => optional($i->articulos)->sku,
            'descripcion' => optional($i->articulos)->descripcion,
            'detalle' => $i->detalle,
            'unidadmedida_id' => $i->unidadmedida_id,
            'unidadmedida' => optional($i->unidadmedida)->abreviatura ?? optional($i->articulos?->unidadesdemedidas)->abreviatura,
            'saldo_sistema' => $i->saldo_sistema,
            'cantidad_contada' => $i->cantidad_contada,
            'color_id' => (int) ($i->color_id ?? 0),
            'talle_id' => (int) ($i->talle_id ?? 0),
            'maneja_stock_color_talle' => (bool) (optional($i->articulos)->maneja_stock_color_talle ?? false)
                || ((int) ($i->color_id ?? 0) > 0) || ((int) ($i->talle_id ?? 0) > 0),
        ])->all();
    }
    if (empty($lineas)) {
        $lineas = [[
            'recuento_item_id' => '',
            'articulo_id' => '',
            'sku' => '',
            'descripcion' => '',
            'detalle' => '',
            'unidadmedida_id' => '',
            'unidadmedida' => '',
            'saldo_sistema' => '',
            'cantidad_contada' => '',
            'color_id' => 0,
            'talle_id' => 0,
            'maneja_stock_color_talle' => false,
        ]];
    }

    $modoStockColorTalleInicial = old('modo_stock_color_talle', '');
    if ($modoStockColorTalleInicial === '') {
        foreach ($lineas as $lnModo) {
            if (! empty($lnModo['articulo_id']) && ! empty($lnModo['maneja_stock_color_talle'])) {
                $modoStockColorTalleInicial = '1';
                break;
            }
            if (! empty($lnModo['articulo_id'])) {
                $modoStockColorTalleInicial = '0';
                break;
            }
        }
    }
@endphp

<style>
#tabla-recuento-items tr.recuento-linea-duplicada-aviso {
    outline: 2px solid #ffc107;
    background-color: #fff8e1;
}
</style>

@if (! $soloLectura)
<div class="row mb-2">
    <div class="col-md-12">
        <div class="btn-group btn-group-sm" role="group">
            @if (can('recuento-aleatorio', false))
            <button type="button" class="btn btn-outline-info" id="btn-recuento-aleatorio"
                title="Sortea artículos del depósito de entrega del artículo; si no hay, usa saldo o movimientos del depósito">
                <i class="fa fa-random"></i> Recuento aleatorio
            </button>
            @endif
            <button type="button" class="btn btn-outline-secondary" id="btn-abrir-modal-importar-excel" data-toggle="modal" data-target="#modal-importar-recuento-excel">
                <i class="fa fa-file-excel-o"></i> Importar Excel
            </button>
        </div>
        <span class="text-muted small ml-2">El saldo del depósito se actualiza al elegir cada artículo.</span>
    </div>
</div>
@endif

<div id="ms-ayuda-color-talle" class="alert alert-info py-2 small mb-2" style="display:none;">
    Este recuento usa stock por color y talle: todas las líneas deben tener color y talle.
</div>
<input type="hidden" name="modo_stock_color_talle" id="modo_stock_color_talle" value="{{ $modoStockColorTalleInicial }}">
{{-- Un solo campo JSON: con 125+ líneas el POST clásico supera max_input_vars y el último ítem llega en 0. --}}
<textarea name="items_json" id="recuento-items-json" class="d-none" autocomplete="off" aria-hidden="true" tabindex="-1" rows="1" cols="20">{{ old('items_json', '') }}</textarea>

<div class="card">
    <div class="card-header py-2">
        <h4 class="card-title mb-0"><i class="fa fa-cubes"></i> Líneas de conteo</h4>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0" id="tabla-recuento-items">
            <thead>
                <tr>
                    <th style="width:12%">Artículo</th>
                    <th style="width:18%">Descripción</th>
                    <th class="ms-col-color-talle" style="width:10%; display:none;">Color</th>
                    <th class="ms-col-color-talle" style="width:8%; display:none;">Talle</th>
                    <th style="width:6%">UM</th>
                    <th style="width:9%">Saldo dep.</th>
                    <th style="width:9%">Contado</th>
                    <th style="width:9%">Diferencia</th>
                    <th style="width:12%" class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-recuento-items">
                @foreach ($lineas as $idx => $linea)
                    @php
                        $articuloId = old('articulo_ids.'.$idx, $linea['articulo_id'] ?? '');
                        $saldo = old('saldos_sistema.'.$idx, $linea['saldo_sistema'] ?? '');
                        $contado = old('cantidades_contadas.'.$idx, $linea['cantidad_contada'] ?? '');
                        $dif = is_numeric($contado) && is_numeric($saldo) ? (float) $contado - (float) $saldo : null;
                        $colorIdLin = (int) old('colores_id.'.$idx, $linea['color_id'] ?? 0);
                        $talleIdLin = (int) old('talles_id.'.$idx, $linea['talle_id'] ?? 0);
                        $manejaCt = (bool) ($linea['maneja_stock_color_talle'] ?? ($colorIdLin > 0 || $talleIdLin > 0));
                    @endphp
                    <tr class="recuento-item-row" data-maneja-stock-color-talle="{{ $manejaCt ? '1' : '0' }}">
                        <td>
                            <input type="hidden" class="recuento_item_id" name="recuento_item_ids[]" value="{{ old('recuento_item_ids.'.$idx, $linea['recuento_item_id'] ?? '') }}">
                            <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{ $articuloId }}">
                            <input type="hidden" class="unidadmedida_id" name="unidadmedida_ids[]" value="{{ old('unidadmedida_ids.'.$idx, $linea['unidadmedida_id'] ?? '') }}">
                            <input type="hidden" class="saldo_sistema_input" name="saldos_sistema[]" value="{{ $saldo }}">
                            <div class="d-flex align-items-center flex-nowrap">
                                @if (! $soloLectura)
                                <button type="button" title="Consulta art&iacute;culos (F1)" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                @endif
                                <input type="text" class="codigoarticulo form-control form-control-sm ml-1" style="width:150px; min-width:150px;" name="codigoarticulos[]" value="{{ old('codigoarticulos.'.$idx, $linea['sku'] ?? '') }}" @if ($soloLectura) readonly @endif>
                            </div>
                        </td>
                        <td>
                            <input type="text" class="descripcionarticulo form-control form-control-sm" name="detalle_articulos[]" value="{{ old('detalle_articulos.'.$idx, $linea['detalle'] ?? ($linea['descripcion'] ?? '')) }}" readonly>
                        </td>
                        @include('stock.movimientostock.partials.fila_color_talle', [
                            'colorId' => $colorIdLin,
                            'talleId' => $talleIdLin,
                            'manejaColorTalle' => $manejaCt,
                        ])
                        <td><span class="unidad-medida-label text-monospace">{{ old('unidadmedida_labels.'.$idx, $linea['unidadmedida'] ?? '—') }}</span></td>
                        <td><span class="saldo-deposito text-monospace">{{ $saldo !== '' ? rtrim(rtrim(number_format((float) $saldo, 6, '.', ''), '0'), '.') : '—' }}</span></td>
                        <td>
                            <input type="number" step="0.000001" min="0" name="cantidades_contadas[]" class="form-control form-control-sm input-cantidad-contada @error('cantidades_contadas.'.$idx) is-invalid @enderror" value="{{ $contado }}" @if ($soloLectura) readonly @endif>
                        </td>
                        <td><span class="diferencia-linea text-monospace @if ($dif !== null && abs($dif) > 1e-9) text-danger @endif">{{ $dif !== null ? rtrim(rtrim(number_format($dif, 6, '.', ''), '0'), '.') : '—' }}</span></td>
                        <td class="text-nowrap text-right">
                            <div class="d-inline-flex align-items-center justify-content-end flex-nowrap">
                                <button type="button" title="Movimientos de stock en el dep&oacute;sito del recuento"
                                    class="btn-accion-tabla btn-movimientos-articulo-deposito tooltipsC @if (! $articuloId) d-none @endif"
                                    @if (! $articuloId) disabled @endif>
                                    <i class="fa fa-list text-secondary"></i>
                                </button>
                                @if ($articuloId && \App\Support\Stock\ArticuloConsultaDesdeModal::puedeConsultar())
                                <a href="{{ \App\Support\Stock\ArticuloConsultaDesdeModal::urlEditar((int) $articuloId) }}" target="_blank" rel="noopener" class="btn-accion-tabla btn-link-articulo tooltipsC" title="Consultar art&iacute;culo">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @else
                                <a href="#" target="_blank" rel="noopener" class="btn-accion-tabla btn-link-articulo tooltipsC d-none" title="Consultar art&iacute;culo">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @endif
                                @if (! $soloLectura)
                                <button type="button" class="btn btn-link text-danger btn-eliminar-item px-1" title="Eliminar l&iacute;nea">
                                    <i class="fa fa-trash"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if (! $soloLectura)
    <div class="card-footer py-2">
        <button type="button" id="btn-agregar-item-recuento" class="btn btn-outline-primary btn-sm">
            <i class="fa fa-plus"></i> Agregar línea
        </button>
    </div>
    @endif
</div>

<template id="template-recuento-item-row">
    <tr class="recuento-item-row" data-maneja-stock-color-talle="0">
        <td>
            <input type="hidden" class="recuento_item_id" name="recuento_item_ids[]" value="">
            <input type="hidden" class="articulo_id" name="articulo_ids[]" value="">
            <input type="hidden" class="unidadmedida_id" name="unidadmedida_ids[]" value="">
            <input type="hidden" class="saldo_sistema_input" name="saldos_sistema[]" value="">
            <div class="d-flex align-items-center flex-nowrap">
                <button type="button" title="Consulta art&iacute;culos (F1)" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="codigoarticulo form-control form-control-sm ml-1" style="width:150px; min-width:150px;" name="codigoarticulos[]">
            </div>
        </td>
        <td><input type="text" class="descripcionarticulo form-control form-control-sm" name="detalle_articulos[]" readonly></td>
        @include('stock.movimientostock.partials.fila_color_talle', [
            'colorId' => 0,
            'talleId' => 0,
            'manejaColorTalle' => false,
        ])
        <td><span class="unidad-medida-label text-monospace">—</span></td>
        <td><span class="saldo-deposito text-monospace">—</span></td>
        <td><input type="number" step="0.000001" min="0" name="cantidades_contadas[]" class="form-control form-control-sm input-cantidad-contada" value="0"></td>
        <td><span class="diferencia-linea text-monospace">—</span></td>
        <td class="text-nowrap text-right">
            <div class="d-inline-flex align-items-center justify-content-end flex-nowrap">
                <button type="button" title="Movimientos de stock en el dep&oacute;sito del recuento"
                    class="btn-accion-tabla btn-movimientos-articulo-deposito tooltipsC d-none" disabled>
                    <i class="fa fa-list text-secondary"></i>
                </button>
                <a href="#" target="_blank" rel="noopener" class="btn-accion-tabla btn-link-articulo tooltipsC d-none" title="Consultar art&iacute;culo">
                    <i class="fa fa-edit"></i>
                </a>
                <button type="button" class="btn btn-link text-danger btn-eliminar-item px-1" title="Eliminar l&iacute;nea">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        </td>
    </tr>
</template>
