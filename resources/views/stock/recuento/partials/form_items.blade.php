@php
    $soloLectura = $soloLectura ?? false;
    $importLineas = session('recuento_import_lineas', []);
    $lineas = old('articulo_ids')
        ? null
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
        ]];
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

<div class="card">
    <div class="card-header py-2">
        <h4 class="card-title mb-0"><i class="fa fa-cubes"></i> Líneas de conteo</h4>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0" id="tabla-recuento-items">
            <thead>
                <tr>
                    <th style="width:14%">Artículo</th>
                    <th style="width:24%">Descripción</th>
                    <th style="width:8%">UM</th>
                    <th style="width:10%">Saldo dep.</th>
                    <th style="width:10%">Contado</th>
                    <th style="width:10%">Diferencia</th>
                    <th style="width:14%" class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-recuento-items">
                @foreach ($lineas as $idx => $linea)
                    @php
                        $articuloId = old('articulo_ids.'.$idx, $linea['articulo_id'] ?? '');
                        $saldo = old('saldos_sistema.'.$idx, $linea['saldo_sistema'] ?? '');
                        $contado = old('cantidades_contadas.'.$idx, $linea['cantidad_contada'] ?? '');
                        $dif = is_numeric($contado) && is_numeric($saldo) ? (float) $contado - (float) $saldo : null;
                    @endphp
                    <tr class="recuento-item-row">
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
                                <input type="text" class="codigoarticulo form-control form-control-sm ml-1" style="width:150px; min-width:150px;" value="{{ old('codigoarticulos.'.$idx, $linea['sku'] ?? '') }}" @if ($soloLectura) readonly @endif>
                            </div>
                        </td>
                        <td>
                            <input type="text" class="descripcionarticulo form-control form-control-sm" name="detalle_articulos[]" value="{{ old('detalle_articulos.'.$idx, $linea['detalle'] ?? ($linea['descripcion'] ?? '')) }}" readonly>
                        </td>
                        <td><span class="unidad-medida-label text-monospace">{{ old('unidadmedida_labels.'.$idx, $linea['unidadmedida'] ?? '—') }}</span></td>
                        <td><span class="saldo-deposito text-monospace">{{ $saldo !== '' ? rtrim(rtrim(number_format((float) $saldo, 6, '.', ''), '0'), '.') : '—' }}</span></td>
                        <td>
                            <input type="number" step="0.000001" min="0" name="cantidades_contadas[]" class="form-control form-control-sm input-cantidad-contada" value="{{ $contado }}" @if ($soloLectura) readonly @endif>
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
    <tr class="recuento-item-row">
        <td>
            <input type="hidden" class="recuento_item_id" name="recuento_item_ids[]" value="">
            <input type="hidden" class="articulo_id" name="articulo_ids[]" value="">
            <input type="hidden" class="unidadmedida_id" name="unidadmedida_ids[]" value="">
            <input type="hidden" class="saldo_sistema_input" name="saldos_sistema[]" value="">
            <div class="d-flex align-items-center flex-nowrap">
                <button type="button" title="Consulta art&iacute;culos (F1)" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="codigoarticulo form-control form-control-sm ml-1" style="width:150px; min-width:150px;">
            </div>
        </td>
        <td><input type="text" class="descripcionarticulo form-control form-control-sm" name="detalle_articulos[]" readonly></td>
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
