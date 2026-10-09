@php
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceEstadosSupport;
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceLiquidacionSupport;
    use App\Support\Ventas\Tiendanube\TiendanubeTiendasSupport;
    $codigoMaestro = static function ($modelo): string {
        if ($modelo === null) {
            return '';
        }
        $codigo = trim((string) ($modelo->codigo ?? ''));

        return $codigo !== '' ? $codigo : trim((string) ($modelo->nombre ?? ''));
    };
    $lineasExistentes = old('lineas');
    if (! is_array($lineasExistentes)) {
        $lineasExistentes = ($data->lineas ?? collect())->map(function ($l) use ($codigoMaestro) {
            return [
                'tipo' => $l->tipo,
                'articulo_id' => $l->articulo_id,
                'articulo_codigo' => $l->articulo->sku ?? '',
                'descripcion' => $l->descripcion ?: ($l->articulo->descripcion ?? ''),
                'cantidad' => $l->cantidad,
                'precio_unitario' => $l->precio_unitario,
                'venta_emision_id' => $l->venta_emision_id,
                'talle_id' => $l->talle_id,
                'talle_codigo' => $codigoMaestro($l->talle),
                'talle_nombre' => $l->talle->nombre ?? '',
                'color_id' => $l->color_id,
                'color_codigo' => $codigoMaestro($l->color),
                'color_nombre' => $l->color->nombre ?? '',
                'combinacion_id' => $l->combinacion_id,
                'combinacion_codigo' => $l->combinacion->codigo ?? '',
                'combinacion_nombre' => $l->combinacion->nombre ?? '',
            ];
        })->values()->all();
    }
    $pedidoTn = $data->relationLoaded('tiendanubePedido') ? $data->tiendanubePedido : ($data->tiendanubePedido ?? null);
    $pedidoNumero = old('pedido_numero', $pedidoTn->order_number ?? '');
    $tiendaNombre = old('tienda_nombre', $pedidoTn ? TiendanubeTiendasSupport::nombre($pedidoTn->store_id) : '');
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
            <label class="col-lg-4 control-label text-right pr-2">Tienda</label>
            <div class="col-lg-6">
                <input type="hidden" name="tiendanube_pedido_id" id="tiendanube_pedido_id"
                       value="{{ old('tiendanube_pedido_id', $data->tiendanube_pedido_id) }}">
                <input type="hidden" name="pedido_numero" id="pedido_numero_hidden" value="{{ $pedidoNumero }}">
                <input type="hidden" name="tienda_nombre" id="tienda_nombre_hidden" value="{{ $tiendaNombre }}">
                <input type="text" id="tienda_nombre" class="form-control" value="{{ $tiendaNombre }}" readonly tabindex="-1" placeholder="Se completa al elegir la factura">
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Nº pedido de la tienda</label>
            <div class="col-lg-4">
                <input type="text" id="pedido_numero" class="form-control" value="{{ $pedidoNumero }}" readonly tabindex="-1" placeholder="Se completa al elegir la factura">
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

        @php
            $motivoSel = (int) old('motivo_devolucion_id', $data->motivo_devolucion_id ?? 0);
        @endphp
        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2">Motivo</label>
            <div class="col-lg-4">
                <select name="motivo_devolucion_id" id="cdm-motivo" class="form-control" {{ $ro ? 'disabled' : '' }} required>
                    <option value="">Elegí el motivo…</option>
                    @foreach ($motivos as $motivo)
                        @php
                            $selMotivo = $motivoSel === (int) $motivo['id']
                                || ($motivoSel === 0 && (string) ($data->motivo_codigo ?? '') === (string) $motivo['codigo']);
                        @endphp
                        <option value="{{ $motivo['id'] }}" data-vuelve="{{ $motivo['vuelve_stock'] ? '1' : '0' }}" {{ $selMotivo ? 'selected' : '' }}>
                            {{ $motivo['nombre'] }} — {{ $motivo['vuelve_stock'] ? 'vuelve al stock' : 'no entra al stock' }}
                        </option>
                    @endforeach
                </select>
                @if ($ro)
                    <input type="hidden" name="motivo_devolucion_id" value="{{ $motivoSel ?: ($data->motivo_devolucion_id ?? '') }}">
                @endif
                <small class="text-muted d-block mt-1" id="cdm-motivo-ayuda">
                    El motivo define si el par ingresa al stock vendible al emitir la nota de crédito. Queda registrado en el historial.
                </small>
            </div>
            <div class="col-lg-3">
                <input type="text" name="motivo" class="form-control" placeholder="Detalle"
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
            Al elegir la factura se cargan las líneas a devolver con el artículo, el talle y el color o la combinación.
            El precio inicial es el de la factura. Si lo cambian (descuento por transferencia: mismo importe del producto en devolver y en reemplazo), la nota de crédito sale por ese precio.
            El reemplazo se elige con lupa o F1. Enter en el SKU lo resuelve.
            El calzado pide talle y, según el artículo, color o combinación. Si no maneja variante, esos campos quedan en «No aplica».
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered" id="cdm-lineas-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:120px">Tipo</th>
                        <th>Artículo</th>
                        <th>Combinación</th>
                        <th>Color</th>
                        <th>Talle</th>
                        <th style="width:90px">Cant.</th>
                        <th style="width:110px">Precio</th>
                        @if (! $ro)
                            <th style="width:60px"></th>
                        @endif
                    </tr>
                </thead>
                <tbody id="cdm-lineas-tbody">
                    @foreach ($lineasExistentes as $idx => $linea)
                        @include('ventas.facturacion_local.cambio_devolucion.partials.fila_linea', [
                            'idx' => $idx,
                            'linea' => $linea,
                            'ro' => $ro,
                        ])
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

@if (! $ro)
<div class="modal fade" id="cdm-modal-ventas" tabindex="-1" role="dialog" aria-labelledby="cdm-modal-ventas-titulo" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cdm-modal-ventas-titulo">Facturas encontradas</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Elegí la factura. Enter toma la primera fila.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Código</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th class="text-right">Total</th>
                                <th>Pedido</th>
                                <th>Tienda</th>
                                <th style="width:90px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cdm-modal-ventas-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
            </div>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultatalle')
@include('includes.stock.modalconsultacolor')
@include('includes.stock.modalconsultacombinacion')
<template id="cdm-template-linea">
    @include('ventas.facturacion_local.cambio_devolucion.partials.fila_linea', [
        'idx' => '__IDX__',
        'linea' => [
            'tipo' => 'reemplazo',
            'articulo_id' => '',
            'articulo_codigo' => '',
            'descripcion' => '',
            'cantidad' => 1,
            'precio_unitario' => 0,
        ],
        'ro' => false,
    ])
</template>
@endif
