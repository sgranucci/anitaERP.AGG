@php
    $items = old('items');
    if (! is_array($items)) {
        $items = [];
        foreach (($pedido->pedido_articulos ?? collect()) as $i => $pa) {
            $items[] = [
                'articulo_id' => $pa->articulo_id,
                'codigo_articulo' => $pa->articulos->sku ?? '',
                'desc_articulo' => $pa->articulos->descripcion ?? $pa->descripcion_aux ?? '',
                'numeroitem' => $pa->numeroitem ?? ($i + 1),
                'cantidad' => $pa->cantidad,
                'precio' => $pa->precio,
                'descuento' => $pa->descuento,
                'moneda_id' => $pa->moneda_id,
                'listaprecio_id' => $pa->listaprecio_id,
                'unidadmedida_id' => $pa->unidadmedida_id,
                'unidadmedida_alter_id' => $pa->unidadmedida_alter_id,
                'cantidad_alter' => $pa->cantidad_alter,
                'fechaentrega' => optional($pa->fechaentrega)->format('Y-m-d'),
                'orden_compra' => $pa->orden_compra,
                'articulo_cliente' => $pa->articulo_cliente,
                'partida' => $pa->partida,
                'porc_fason' => $pa->porc_fason,
                'precio_fason' => $pa->precio_fason,
                'incluyeimpuesto' => $pa->incluyeimpuesto ?? 'N',
                'estado' => $pa->estado,
                'descripcion_aux' => $pa->descripcion_aux,
            ];
        }
    }
    if ($items === []) {
        $items = [[
            'articulo_id' => '',
            'codigo_articulo' => '',
            'desc_articulo' => '',
            'numeroitem' => 1,
            'cantidad' => '',
            'precio' => '',
            'descuento' => 0,
            'moneda_id' => old('moneda_id', $pedido->moneda_id ?? ''),
            'listaprecio_id' => '',
            'unidadmedida_id' => '',
            'unidadmedida_alter_id' => '',
            'cantidad_alter' => '',
            'fechaentrega' => old('fechaentrega', substr((string) ($pedido->fechaentrega ?? date('Y-m-d')), 0, 10)),
            'orden_compra' => '',
            'articulo_cliente' => '',
            'partida' => 0,
            'porc_fason' => 0,
            'precio_fason' => 0,
            'incluyeimpuesto' => 'N',
            'estado' => 'P',
            'descripcion_aux' => '',
        ]];
    }
@endphp

<input type="hidden" name="empresa_id" id="empresa_id" value="{{ session('empresa_id') ?? ($empresa_query[0]->id ?? '') }}">

<div class="row">
    <div class="col-md-6">
        <div class="form-group row">
            <label class="col-lg-3 col-form-label">Cliente</label>
            <input type="hidden" id="cliente_id" name="cliente_id" value="{{ old('cliente_id', $pedido->cliente_id ?? '') }}">
            <button type="button" title="Consulta clientes (F1)" class="btn-accion-tabla consultacliente tooltipsC">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="col-lg-2 form-control codigocliente" id="codigocliente" name="codigocliente"
                   value="{{ old('codigocliente', $pedido->clientes->codigo ?? '') }}" placeholder="N&ordm;"
                   title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off">
            <input type="text" class="col-lg-5 form-control" id="nombrecliente" name="nombrecliente"
                   value="{{ old('nombrecliente', $pedido->clientes->nombre ?? '') }}" readonly>
        </div>

        <div class="form-group row tm-vendedor-campo">
            <label class="col-lg-3 col-form-label requerido">Vendedor</label>
            <input type="hidden" class="vendedor_id" name="vendedor_id" id="vendedor_id"
                   value="{{ old('vendedor_id', $pedido->vendedor_id ?? '') }}">
            <button type="button" title="Consulta vendedores (F1)" class="btn-accion-tabla consultavendedor tooltipsC">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="col-lg-2 form-control codigovendedor" id="codigovendedor" name="codigovendedor"
                   value="{{ old('codigovendedor', $pedido->vendedores->codigo ?? '') }}"
                   placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off">
            <input type="text" class="col-lg-5 form-control nombrevendedor" id="nombrevendedor" name="nombrevendedor"
                   value="{{ old('nombrevendedor', $pedido->vendedores->nombre ?? '') }}" readonly>
        </div>

        <div class="form-group row">
            <label class="col-lg-3 col-form-label">Cond. venta</label>
            <select name="condicionventa_id" id="condicionventa_id" class="col-lg-8 form-control">
                <option value="">-- Seleccionar --</option>
                @foreach ($condicionventa_query as $c)
                    <option value="{{ $c->id }}" @if ((int) old('condicionventa_id', $pedido->condicionventa_id ?? 0) === (int) $c->id) selected @endif>
                        {{ $c->codigo ?? '' }} — {{ $c->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group row tm-transporte-campo">
            <label class="col-lg-3 col-form-label">Expreso</label>
            <input type="hidden" class="transporte_id" id="transporte_id" name="transporte_id"
                   value="{{ old('transporte_id', $pedido->transporte_id ?? '') }}">
            <button type="button" title="Consulta expresos (F1)" class="btn-accion-tabla consultatransporte tooltipsC">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="col-lg-2 form-control codigotransporte" id="codigotransporte" name="codigotransporte"
                   value="{{ old('codigotransporte', $pedido->transportes->codigo ?? '') }}"
                   placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off">
            <input type="text" class="col-lg-5 form-control nombretransporte" id="nombretransporte" name="nombretransporte"
                   value="{{ old('nombretransporte', $pedido->transportes->nombre ?? '') }}" readonly>
        </div>

        <div class="form-group row" id="divlugar">
            <label for="lugarentrega" id="label-lugarentrega" class="col-lg-3 col-form-label">Lugar entrega</label>
            <div class="col-lg-8">
                <div class="input-group">
                    <input type="text" name="lugarentrega" id="lugarentrega" class="form-control"
                           value="{{ old('lugarentrega', $pedido->lugarentrega ?? '') }}" maxlength="60"
                           placeholder="Seleccione un lugar de entrega del cliente">
                    <div class="input-group-append" id="div-cambiar-lugarentrega" style="display: none;">
                        <button type="button" id="btn-cambiar-lugarentrega" class="btn btn-outline-secondary btn-sm"
                                title="Cambiar lugar de entrega">
                            Cambiar
                        </button>
                    </div>
                </div>
                <small id="aviso-lugarentrega-obligatorio" class="form-text text-danger" style="display: none;">
                    Este cliente tiene lugares de entrega cargados. Debe elegir uno para continuar.
                </small>
            </div>
            <input type="hidden" name="cliente_entrega_id" id="cliente_entrega_id"
                   value="{{ old('cliente_entrega_id', $pedido->cliente_entrega_id ?? '') }}">
            <input type="hidden" id="cliente_entrega_id_previa" name="cliente_entrega_id_previa"
                   value="{{ old('cliente_entrega_id', $pedido->cliente_entrega_id ?? '') }}">
            <input type="hidden" id="entrega_nombre" name="entrega_nombre" value="">
            <input type="hidden" id="fl_cliente_tiene_entrega" value="0">
            <input type="hidden" id="descuento" name="descuento" value="{{ old('descuento', $pedido->descuento ?? 0) }}">
            <input type="hidden" id="zonavta_id" name="zonavta_id" value="{{ old('zonavta_id', $pedido->zonavta_id ?? '') }}">
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group row">
            <label class="col-lg-3 col-form-label">Fecha</label>
            <input type="date" name="fecha" id="fecha" class="col-lg-3 form-control"
                   value="{{ substr(old('fecha', $pedido->fecha ?? date('Y-m-d')), 0, 10) }}" readonly>
            <label class="col-lg-2 col-form-label">Estado</label>
            <input type="text" class="col-lg-3 form-control" readonly
                   value="{{ $estadosCabecera[$pedido->estadopedido ?? '0'] ?? ($pedido->estadopedido ?? '') }}">
        </div>

        <div class="form-group row">
            <label class="col-lg-3 col-form-label requerido">Entrega</label>
            <input type="date" name="fechaentrega" id="fechaentrega" class="col-lg-3 form-control" required
                   value="{{ substr(old('fechaentrega', $pedido->fechaentrega ?? date('Y-m-d')), 0, 10) }}">
            <label class="col-lg-2 col-form-label">O. Compra</label>
            <input type="text" name="orden_compra" id="orden_compra" class="col-lg-3 form-control" maxlength="15"
                   value="{{ old('orden_compra', $pedido->orden_compra ?? '') }}">
        </div>

        <div class="form-group row">
            <label class="col-lg-3 col-form-label">Moneda</label>
            <select name="moneda_id" id="moneda_id" class="col-lg-3 form-control">
                <option value="">--</option>
                @foreach ($moneda_query as $m)
                    <option value="{{ $m->id }}" @if ((int) old('moneda_id', $pedido->moneda_id ?? 0) === (int) $m->id) selected @endif>
                        {{ $m->abreviatura ?? $m->nombre }}
                    </option>
                @endforeach
            </select>
            <label class="col-lg-2 col-form-label">Cotiz.</label>
            <input type="number" step="0.000001" name="cotizacion" id="cotizacion" class="col-lg-3 form-control"
                   value="{{ old('cotizacion', $pedido->cotizacion ?? 1) }}">
        </div>

        <div class="form-group row tm-deposito-campo">
            <label class="col-lg-3 col-form-label">Dep&oacute;sito</label>
            <input type="hidden" class="deposito_id" id="deposito_id" name="deposito_id"
                   value="{{ old('deposito_id', $pedido->deposito_id ?? '') }}">
            <button type="button" title="Consulta dep&oacute;sitos (F1)" class="btn-accion-tabla consultadeposito tooltipsC">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="col-lg-2 form-control codigodeposito"
                   value="{{ old('deposito_codigo', $pedido->deposito->codigo ?? '') }}"
                   placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off">
            <input type="text" class="col-lg-4 form-control descripciondeposito" readonly
                   value="{{ old('deposito_descripcion', $pedido->deposito->nombre ?? '') }}">
        </div>

        <div class="form-group row">
            <label class="col-lg-3 col-form-label">Stock</label>
            <select name="en_stock" id="en_stock" class="col-lg-3 form-control">
                <option value="">--</option>
                <option value="S" @if (old('en_stock', $pedido->en_stock ?? '') === 'S') selected @endif>Sí</option>
                <option value="N" @if (old('en_stock', $pedido->en_stock ?? '') === 'N') selected @endif>No</option>
            </select>
        </div>
    </div>
</div>

<div class="form-group row">
    <label class="col-lg-2 col-form-label">Leyenda</label>
    <textarea name="leyenda" id="leyenda" class="col-lg-9 form-control" rows="2" maxlength="160">{{ old('leyenda', $pedido->leyenda ?? '') }}</textarea>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Ítems</span>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-agregar-item-pedido-if">
            <i class="fa fa-plus"></i> Agregar ítem
        </button>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover" id="tabla-items-pedido-if">
            <thead style="background-color:#85C1E9;color:#17202A;">
                <tr>
                    <th>#</th>
                    <th>Artículo</th>
                    <th>Descripción</th>
                    <th>Art. cliente</th>
                    <th>Cantidad</th>
                    <th>UM</th>
                    <th>Cant. alt.</th>
                    <th>F. entrega</th>
                    <th>% fason</th>
                    <th>Precio</th>
                    <th>Dto</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-items-pedido-if">
                @foreach ($items as $idx => $item)
                    @include('ventas.pedido.interforming.partials.fila_item', ['idx' => $idx, 'item' => $item])
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<template id="tpl-fila-item-pedido-if">
    @include('ventas.pedido.interforming.partials.fila_item', [
        'idx' => '__IDX__',
        'item' => [
            'articulo_id' => '',
            'codigo_articulo' => '',
            'desc_articulo' => '',
            'numeroitem' => '__NUM__',
            'cantidad' => '',
            'precio' => '',
            'descuento' => 0,
            'moneda_id' => '',
            'listaprecio_id' => '',
            'unidadmedida_id' => '',
            'unidadmedida_alter_id' => '',
            'cantidad_alter' => '',
            'fechaentrega' => '',
            'orden_compra' => '',
            'articulo_cliente' => '',
            'partida' => 0,
            'porc_fason' => 0,
            'precio_fason' => 0,
            'incluyeimpuesto' => 'N',
            'estado' => 'P',
            'descripcion_aux' => '',
        ],
    ])
</template>
