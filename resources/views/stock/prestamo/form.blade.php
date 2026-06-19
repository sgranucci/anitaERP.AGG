@php
    $modoVer = ! ($modoEdicion ?? true);
    $depOrigenId = old('deposito_origen_id', isset($prestamo) ? $prestamo->deposito_origen_id : '');
    $depDestinoId = old('deposito_destino_id', isset($prestamo) ? $prestamo->deposito_destino_id : '');
    $depOrigenModel = (int) $depOrigenId > 0
        ? (isset($prestamo) && (int) ($prestamo->deposito_origen_id ?? 0) === (int) $depOrigenId
            ? $prestamo->depositoOrigen
            : \App\Models\Stock\Depmae::find((int) $depOrigenId))
        : null;
    $depDestinoModel = (int) $depDestinoId > 0
        ? (isset($prestamo) && (int) ($prestamo->deposito_destino_id ?? 0) === (int) $depDestinoId
            ? $prestamo->depositoDestino
            : \App\Models\Stock\Depmae::find((int) $depDestinoId))
        : null;

    $items = old('items');
    if ($items === null && isset($prestamo)) {
        $items = $prestamo->items->map(fn ($i) => [
            'articulo_id' => $i->articulo_id,
            'sku' => optional($i->articulos)->sku,
            'descripcion' => optional($i->articulos)->descripcion,
            'cantidad' => $i->cantidad,
            'observaciones' => $i->observaciones,
        ])->all();
    }
    if (empty($items)) {
        $items = [['articulo_id' => '', 'sku' => '', 'descripcion' => '', 'cantidad' => '', 'observaciones' => '']];
    }

    $saldosOrigenJson = json_encode($saldosOrigen ?? [], JSON_UNESCAPED_UNICODE);
    $saldosDestinoJson = json_encode($saldosDestino ?? [], JSON_UNESCAPED_UNICODE);
@endphp
<div class="row">
    <div class="col-md-12">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => $empresa_id ?? null,
            'col_label' => 'col-lg-2',
            'col_input' => 'col-lg-4',
        ])
    </div>
</div>
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label class="requerido">Fecha del préstamo</label>
            <input type="date" name="fecha_prestamo" class="form-control"
                value="{{ old('fecha_prestamo', isset($prestamo) ? optional($prestamo->fecha_prestamo)->format('Y-m-d') : date('Y-m-d')) }}" required>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label class="requerido">Fecha prometida de devolución</label>
            <input type="date" name="fecha_devolucion_prometida" class="form-control"
                value="{{ old('fecha_devolucion_prometida', isset($prestamo) ? optional($prestamo->fecha_devolucion_prometida)->format('Y-m-d') : '') }}" required>
        </div>
    </div>
    <div class="col-md-3">
        @include('stock.partials.campo_consulta_deposito', [
            'prefix' => 'prestamo_origen',
            'layout' => 'inline',
            'label' => 'Depósito origen',
            'inputName' => 'deposito_origen_id',
            'inputId' => 'prestamo_deposito_origen_id',
            'depositoId' => $depOrigenId,
            'codigo' => old('deposito_origen_codigo', optional($depOrigenModel)->codigo ?? ''),
            'descripcion' => old('deposito_origen_descripcion', optional($depOrigenModel)->nombre ?? ''),
            'required' => true,
        ])
    </div>
    <div class="col-md-3">
        @include('stock.partials.campo_consulta_deposito', [
            'prefix' => 'prestamo_destino',
            'layout' => 'inline',
            'label' => 'Depósito destino',
            'inputName' => 'deposito_destino_id',
            'inputId' => 'prestamo_deposito_destino_id',
            'depositoId' => $depDestinoId,
            'codigo' => old('deposito_destino_codigo', optional($depDestinoModel)->codigo ?? ''),
            'descripcion' => old('deposito_destino_descripcion', optional($depDestinoModel)->nombre ?? ''),
            'required' => true,
        ])
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <label>Observaciones</label>
            <textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones', $prestamo->observaciones ?? '') }}</textarea>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h4 class="card-title"><i class="fa fa-cubes"></i> Ítems</h4>
        <div class="card-tools">
            <span class="badge badge-info">Origen: <span id="badge-deposito-origen">—</span></span>
            <span class="badge badge-light ml-2">Destino: <span id="badge-deposito-destino">—</span></span>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover" id="tabla-prestamo-items">
            <thead>
                <tr>
                    <th style="width:12%">Artículo</th>
                    <th style="width:23%">Descripción</th>
                    <th style="width:12%">Cantidad</th>
                    <th style="width:12%">Saldo origen</th>
                    <th style="width:12%">Saldo destino</th>
                    <th style="width:25%">Observaciones</th>
                    <th style="width:4%"></th>
                </tr>
            </thead>
            <tbody id="tbody-prestamo-items">
                @foreach ($items as $idx => $item)
                    <tr class="prestamo-item-row">
                        <td>
                            <input type="hidden" class="articulo_id" name="items[{{ $idx }}][articulo_id]"
                                value="{{ old('items.'.$idx.'.articulo_id', $item['articulo_id'] ?? '') }}" required>
                            <div class="d-flex align-items-center flex-nowrap">
                                <button type="button" title="Consulta art&iacute;culos" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" class="codigoarticulo form-control form-control-sm ml-1"
                                    style="width:120px; min-width:120px;"
                                    value="{{ old('items.'.$idx.'.sku', $item['sku'] ?? '') }}" autocomplete="off">
                            </div>
                        </td>
                        <td>
                            <input type="text" class="descripcionarticulo form-control form-control-sm" readonly
                                value="{{ old('items.'.$idx.'.descripcion', $item['descripcion'] ?? '') }}">
                        </td>
                        <td>
                            <input type="number" step="0.000001" min="0.000001"
                                name="items[{{ $idx }}][cantidad]"
                                class="form-control input-cantidad"
                                value="{{ old('items.'.$idx.'.cantidad', $item['cantidad'] ?? '') }}" required>
                        </td>
                        <td><span class="saldo-origen text-monospace">—</span></td>
                        <td><span class="saldo-destino text-monospace">—</span></td>
                        <td>
                            <input type="text" name="items[{{ $idx }}][observaciones]" class="form-control"
                                value="{{ old('items.'.$idx.'.observaciones', $item['observaciones'] ?? '') }}" maxlength="255">
                        </td>
                        <td>
                            <button type="button" class="btn btn-link text-danger btn-eliminar-item" title="Eliminar">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <button type="button" id="btn-agregar-item" class="btn btn-outline-primary btn-sm">
            <i class="fa fa-plus"></i> Agregar ítem
        </button>
    </div>
</div>

<input type="hidden" id="prestamo-saldos-origen" value='{!! $saldosOrigenJson !!}'>
<input type="hidden" id="prestamo-saldos-destino" value='{!! $saldosDestinoJson !!}'>
<input type="hidden" id="prestamo-saldo-articulo-url" value="{{ route('prestamo_saldo_articulo') }}">

<template id="template-prestamo-item-row">
    <tr class="prestamo-item-row">
        <td>
            <input type="hidden" class="articulo_id" name="items[0][articulo_id]" value="" required>
            <div class="d-flex align-items-center flex-nowrap">
                <button type="button" title="Consulta art&iacute;culos" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="codigoarticulo form-control form-control-sm ml-1"
                    style="width:120px; min-width:120px;" autocomplete="off">
            </div>
        </td>
        <td><input type="text" class="descripcionarticulo form-control form-control-sm" readonly></td>
        <td>
            <input type="number" step="0.000001" min="0.000001" name="items[0][cantidad]"
                class="form-control input-cantidad" required>
        </td>
        <td><span class="saldo-origen text-monospace">—</span></td>
        <td><span class="saldo-destino text-monospace">—</span></td>
        <td><input type="text" name="items[0][observaciones]" class="form-control" maxlength="255"></td>
        <td>
            <button type="button" class="btn btn-link text-danger btn-eliminar-item" title="Eliminar">
                <i class="fa fa-trash"></i>
            </button>
        </td>
    </tr>
</template>
