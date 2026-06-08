@php
    $modoVer = ! ($modoEdicion ?? true);
    $items = old('items', isset($prestamo) ? $prestamo->items->map(fn ($i) => [
        'articulo_id' => $i->articulo_id,
        'cantidad' => $i->cantidad,
        'observaciones' => $i->observaciones,
    ])->all() : []);
    if (empty($items)) {
        $items = [['articulo_id' => '', 'cantidad' => '', 'observaciones' => '']];
    }
    $articulosJson = $articulos->map(fn ($a) => [
        'id' => $a->id,
        'sku' => $a->sku,
        'descripcion' => $a->descripcion,
        'label' => trim(($a->sku ?? '').' - '.($a->descripcion ?? '')),
    ])->values()->toJson(JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
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
        <div class="form-group">
            <label class="requerido">Depósito origen</label>
            <select name="deposito_origen_id" id="deposito_origen_id" class="form-control" required>
                <option value="">-- Seleccionar --</option>
                @foreach ($depositos as $d)
                    <option value="{{ $d->id }}" data-empresa-id="{{ $d->empresa_id }}"
                        @if ((int) old('deposito_origen_id', $prestamo->deposito_origen_id ?? 0) === (int) $d->id) selected @endif>
                        {{ $d->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label class="requerido">Depósito destino</label>
            <select name="deposito_destino_id" id="deposito_destino_id" class="form-control" required>
                <option value="">-- Seleccionar --</option>
                @foreach ($depositos as $d)
                    <option value="{{ $d->id }}" data-empresa-id="{{ $d->empresa_id }}"
                        @if ((int) old('deposito_destino_id', $prestamo->deposito_destino_id ?? 0) === (int) $d->id) selected @endif>
                        {{ $d->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
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
                    <th style="width:35%">Artículo</th>
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
                            <select name="items[{{ $idx }}][articulo_id]" class="form-control select-articulo" required>
                                <option value="">-- Elegir artículo --</option>
                                @foreach ($articulos as $a)
                                    <option value="{{ $a->id }}"
                                        @if ((int) ($item['articulo_id'] ?? 0) === (int) $a->id) selected @endif>
                                        {{ $a->sku }} - {{ $a->descripcion }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" step="0.000001" min="0.000001"
                                name="items[{{ $idx }}][cantidad]"
                                class="form-control input-cantidad"
                                value="{{ $item['cantidad'] ?? '' }}" required>
                        </td>
                        <td><span class="saldo-origen text-monospace">—</span></td>
                        <td><span class="saldo-destino text-monospace">—</span></td>
                        <td>
                            <input type="text" name="items[{{ $idx }}][observaciones]" class="form-control"
                                value="{{ $item['observaciones'] ?? '' }}" maxlength="255">
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

<input type="hidden" id="prestamo-articulos-data" data-articulos='{!! $articulosJson !!}'>
<input type="hidden" id="prestamo-saldos-origen" value='{!! $saldosOrigenJson !!}'>
<input type="hidden" id="prestamo-saldos-destino" value='{!! $saldosDestinoJson !!}'>
<input type="hidden" id="prestamo-saldo-articulo-url" value="{{ route('prestamo_saldo_articulo') }}">
