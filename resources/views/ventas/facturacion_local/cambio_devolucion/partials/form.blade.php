@php
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceEstadosSupport;
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceLiquidacionSupport;
    $lineasExistentes = old('lineas');
    if (! is_array($lineasExistentes)) {
        $lineasExistentes = ($data->lineas ?? collect())->map(function ($l) {
            return [
                'tipo' => $l->tipo,
                'articulo_id' => $l->articulo_id,
                'articulo_codigo' => $l->articulo->sku ?? '',
                'descripcion' => $l->descripcion ?: ($l->articulo->descripcion ?? ''),
                'cantidad' => $l->cantidad,
                'precio_unitario' => $l->precio_unitario,
                'talle_id' => $l->talle_id,
                'color_id' => $l->color_id,
                'combinacion_id' => $l->combinacion_id,
            ];
        })->values()->all();
    }
    if ($lineasExistentes === []) {
        $lineasExistentes = [
            ['tipo' => 'devolver', 'articulo_id' => '', 'articulo_codigo' => '', 'descripcion' => '', 'cantidad' => 1, 'precio_unitario' => 0],
            ['tipo' => 'reemplazo', 'articulo_id' => '', 'articulo_codigo' => '', 'descripcion' => '', 'cantidad' => 1, 'precio_unitario' => 0],
        ];
    }
    $ro = ! ($editable ?? true);
@endphp

@include('includes.tabs-activas-estilos')
<div class="tabs-activas">
    <ul class="nav nav-tabs" id="tabs-cdm" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#tab-datos" role="tab">
                <i class="fa fa-info-circle"></i> Datos
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-lineas" role="tab">
                <i class="fa fa-list"></i> Líneas
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-comprobantes" role="tab">
                <i class="fa fa-file-text-o"></i> Comprobantes
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-archivos" role="tab">
                <i class="fa fa-paperclip"></i> Archivos
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-historial" role="tab">
                <i class="fa fa-history"></i> Historial
            </a>
        </li>
    </ul>
</div>

<div class="tab-content pt-3">
    <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
        @if ($puenteCuentacaja)
            <div class="alert alert-info py-2">
                Medio puente NCD: <strong>{{ $puenteCuentacaja->codigo }}</strong> — {{ $puenteCuentacaja->nombre }}
            </div>
        @else
            <div class="alert alert-warning py-2">
                No se encontró la cuentacaja puente (código configurado). Revise FACTURACION_LOCAL_CAMBIO_DEVOLUCION_PUENTE_CODIGO.
            </div>
        @endif

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2 requerido">Canal</label>
            <div class="col-lg-6">
                <select name="canal" id="canal" class="form-control" {{ $ro ? 'disabled' : '' }} required>
                    @foreach ($canales as $cKey => $cLabel)
                        <option value="{{ $cKey }}" {{ old('canal', $data->canal) === $cKey ? 'selected' : '' }}>{{ $cLabel }}</option>
                    @endforeach
                </select>
                @if ($ro)
                    <input type="hidden" name="canal" value="{{ $data->canal }}">
                @endif
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2 requerido">Local</label>
            <div class="col-lg-6">
                <select name="local_venta_id" id="local_venta_id" class="form-control" {{ $ro ? 'disabled' : '' }} required>
                    <option value="">Seleccione…</option>
                    @foreach ($locales as $loc)
                        <option value="{{ $loc->id }}"
                            data-empresa="{{ $loc->empresa_id }}"
                            {{ (int) old('local_venta_id', $data->local_venta_id) === (int) $loc->id ? 'selected' : '' }}>
                            {{ $loc->codigo }} — {{ $loc->nombre }}
                        </option>
                    @endforeach
                </select>
                @if ($ro)
                    <input type="hidden" name="local_venta_id" value="{{ $data->local_venta_id }}">
                @endif
                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ old('empresa_id', $data->empresa_id) }}">
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2 requerido">Factura original (código o ID)</label>
            <div class="col-lg-6">
                <div class="input-group">
                    <input type="hidden" name="venta_original_id" id="venta_original_id"
                           value="{{ old('venta_original_id', $data->venta_original_id) }}">
                    <input type="text" id="venta_original_codigo" class="form-control"
                           value="{{ old('venta_original_codigo', $data->ventaOriginal->codigo ?? '') }}"
                           placeholder="Código FAC o ID" {{ $ro ? 'readonly' : '' }} autocomplete="off">
                    @if (! $ro)
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary" id="btn-buscar-venta-original" title="Buscar">
                                <i class="fa fa-search"></i>
                            </button>
                        </div>
                    @endif
                </div>
                <small class="text-muted" id="venta_original_hint">
                    @if ($data->ventaOriginal)
                        Total: {{ number_format((float) $data->ventaOriginal->total, 2, ',', '.') }}
                    @endif
                </small>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Pedido Tienda Nube (ID)</label>
            <div class="col-lg-4">
                <input type="number" name="tiendanube_pedido_id" class="form-control"
                       value="{{ old('tiendanube_pedido_id', $data->tiendanube_pedido_id) }}"
                       {{ $ro ? 'readonly' : '' }}>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Cliente ID</label>
            <div class="col-lg-3">
                <input type="number" name="cliente_id" id="cliente_id" class="form-control"
                       value="{{ old('cliente_id', $data->cliente_id) }}" {{ $ro ? 'readonly' : '' }}>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Receptor</label>
            <div class="col-lg-4">
                <input type="text" name="receptor_nombre" id="receptor_nombre" class="form-control" placeholder="Nombre"
                       value="{{ old('receptor_nombre', $data->receptor_nombre) }}" {{ $ro ? 'readonly' : '' }}>
            </div>
            <div class="col-lg-3">
                <input type="text" name="receptor_documento" id="receptor_documento" class="form-control" placeholder="Documento"
                       value="{{ old('receptor_documento', $data->receptor_documento) }}" {{ $ro ? 'readonly' : '' }}>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Motivo</label>
            <div class="col-lg-3">
                <select name="motivo_codigo" class="form-control" {{ $ro ? 'disabled' : '' }}>
                    <option value="">—</option>
                    @foreach ($motivos as $mKey => $mLabel)
                        <option value="{{ $mKey }}" {{ old('motivo_codigo', $data->motivo_codigo) === $mKey ? 'selected' : '' }}>{{ $mLabel }}</option>
                    @endforeach
                </select>
                @if ($ro)
                    <input type="hidden" name="motivo_codigo" value="{{ $data->motivo_codigo }}">
                @endif
            </div>
            <div class="col-lg-4">
                <input type="text" name="motivo" class="form-control" placeholder="Detalle motivo"
                       value="{{ old('motivo', $data->motivo) }}" {{ $ro ? 'readonly' : '' }}>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Observación</label>
            <div class="col-lg-7">
                <textarea name="observacion" class="form-control" rows="3" {{ $ro ? 'readonly' : '' }}>{{ old('observacion', $data->observacion) }}</textarea>
            </div>
        </div>

        @if ($data->exists && (float) $data->diferencia_importe > 0.009)
            <div class="alert alert-secondary py-2">
                Diferencia:
                <strong>{{ CambioDevolucionMarketplaceLiquidacionSupport::etiquetaSentido($data->diferencia_sentido) }}</strong>
                $ {{ number_format((float) $data->diferencia_importe, 2, ',', '.') }}
                @if ($data->compensacion_observacion)
                    — {{ $data->compensacion_observacion }}
                @endif
            </div>
        @endif
    </div>

    <div class="tab-pane fade" id="tab-lineas" role="tabpanel">
        <p class="text-muted small">
            Líneas <strong>a devolver</strong> (referencia) y de <strong>reemplazo</strong> (se facturan al emitir FAC).
            Indique ID de artículo (SKU/código en descripción).
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered" id="cdm-lineas-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:120px">Tipo</th>
                        <th style="width:100px">Art. ID</th>
                        <th>Descripción</th>
                        <th style="width:90px">Cant.</th>
                        <th style="width:110px">Precio</th>
                        @if (! $ro)
                            <th style="width:60px"></th>
                        @endif
                    </tr>
                </thead>
                <tbody id="cdm-lineas-tbody">
                    @foreach ($lineasExistentes as $idx => $linea)
                    <tr class="cdm-linea-row">
                        <td>
                            <select name="lineas[{{ $idx }}][tipo]" class="form-control form-control-sm" {{ $ro ? 'disabled' : '' }}>
                                @foreach ($tiposLinea as $tKey => $tLabel)
                                    <option value="{{ $tKey }}" {{ ($linea['tipo'] ?? '') === $tKey ? 'selected' : '' }}>{{ $tLabel }}</option>
                                @endforeach
                            </select>
                            @if ($ro)
                                <input type="hidden" name="lineas[{{ $idx }}][tipo]" value="{{ $linea['tipo'] ?? '' }}">
                            @endif
                        </td>
                        <td>
                            <input type="number" name="lineas[{{ $idx }}][articulo_id]" class="form-control form-control-sm"
                                   value="{{ $linea['articulo_id'] ?? '' }}" {{ $ro ? 'readonly' : '' }}>
                        </td>
                        <td>
                            <input type="text" name="lineas[{{ $idx }}][descripcion]" class="form-control form-control-sm"
                                   value="{{ $linea['descripcion'] ?? '' }}" {{ $ro ? 'readonly' : '' }}>
                        </td>
                        <td>
                            <input type="number" step="0.0001" name="lineas[{{ $idx }}][cantidad]" class="form-control form-control-sm"
                                   value="{{ $linea['cantidad'] ?? 1 }}" {{ $ro ? 'readonly' : '' }}>
                        </td>
                        <td>
                            <input type="number" step="0.01" name="lineas[{{ $idx }}][precio_unitario]" class="form-control form-control-sm"
                                   value="{{ $linea['precio_unitario'] ?? 0 }}" {{ $ro ? 'readonly' : '' }}>
                        </td>
                        @if (! $ro)
                            <td class="text-center">
                                <button type="button" class="btn-accion-tabla cdm-quitar-linea" title="Quitar">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (! $ro)
            <button type="button" class="btn btn-outline-primary btn-sm" id="cdm-agregar-linea">
                <i class="fa fa-plus"></i> Agregar línea
            </button>
        @endif
    </div>

    <div class="tab-pane fade" id="tab-comprobantes" role="tabpanel">
        @include('ventas.facturacion_local.cambio_devolucion.partials.comprobantes')
    </div>

    <div class="tab-pane fade" id="tab-archivos" role="tabpanel">
        @include('ventas.facturacion_local.cambio_devolucion.partials.solapa_archivos')
    </div>

    <div class="tab-pane fade" id="tab-historial" role="tabpanel">
        @include('ventas.facturacion_local.cambio_devolucion.partials.historial')
    </div>
</div>

<template id="cdm-template-linea">
    <tr class="cdm-linea-row">
        <td>
            <select name="lineas[__IDX__][tipo]" class="form-control form-control-sm">
                @foreach ($tiposLinea as $tKey => $tLabel)
                    <option value="{{ $tKey }}">{{ $tLabel }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" name="lineas[__IDX__][articulo_id]" class="form-control form-control-sm" value="">
        </td>
        <td>
            <input type="text" name="lineas[__IDX__][descripcion]" class="form-control form-control-sm" value="">
        </td>
        <td>
            <input type="number" step="0.0001" name="lineas[__IDX__][cantidad]" class="form-control form-control-sm" value="1">
        </td>
        <td>
            <input type="number" step="0.01" name="lineas[__IDX__][precio_unitario]" class="form-control form-control-sm" value="0">
        </td>
        <td class="text-center">
            <button type="button" class="btn-accion-tabla cdm-quitar-linea" title="Quitar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
