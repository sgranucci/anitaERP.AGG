@php
    use App\Models\Compras\Ordencompra_Articulo;
    use App\Models\Stock\Articulo as OcArticuloForm;
    $soloLectura = isset($visualizar) && $visualizar;
    if (isset($data) && $data) {
        $solicitanteTexto = trim((string) (optional($data->usuarios)->nombre ?? ''));
    } else {
        $solicitanteTexto = trim((string) (auth()->user()->nombre ?? ''));
    }
    $centrocostoDefaultDestino = (int) (
        (isset($data) && $data && $data->centrocosto_id)
            ? $data->centrocosto_id
            : (auth()->user()->centrocosto_id ?? 1)
    );
    $reqProveedor = (isset($data) && $data) ? $data->proveedores : null;
    $ocComprobanteFechaJson = static function ($v): string {
        if ($v === null) {
            return '';
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        $s = (string) $v;

        return strlen($s) >= 10 ? substr($s, 0, 10) : $s;
    };
    $comprobantesPayload = [];
    if (isset($data) && $data && $data->ordencompra_comprobantes && $data->ordencompra_comprobantes->count()) {
        foreach ($data->ordencompra_comprobantes as $c) {
            $cuotas = [];
            foreach ($c->ordencompra_comprobante_cuotas ?? [] as $q) {
                $cuotas[] = [
                    'fechavencimiento' => $ocComprobanteFechaJson($q->fechavencimiento),
                    'monto' => (float) $q->monto,
                    'moneda_id' => (int) $q->moneda_id,
                    'cotizacion' => $q->cotizacion !== null ? (float) $q->cotizacion : 1.0,
                    'formapago_id' => (int) ($q->formapago_id ?? 1),
                    'detalle' => $q->detalle,
                ];
            }
            $comprobantesPayload[] = [
                'tipocomprobante' => $c->tipocomprobante,
                'fechavencimiento' => $ocComprobanteFechaJson($c->fechavencimiento),
                'monto' => (float) $c->monto,
                'moneda_id' => (int) $c->moneda_id,
                'cotizacion' => $c->cotizacion !== null ? (float) $c->cotizacion : null,
                'detalle' => $c->detalle,
                'cantidadcuota' => $c->cantidadcuota,
                'condicionpago_id' => $c->condicionpago_id,
                'cuotas' => $cuotas,
            ];
        }
    }
    $comprobantesInicial = $comprobantesPayload;
    $oldCj = old('comprobantes_json');
    if (is_string($oldCj) && $oldCj !== '') {
        $dec = json_decode(trim($oldCj), true);
        if (is_array($dec)) {
            $comprobantesInicial = $dec;
        }
    }
@endphp

<input type="hidden" id="ordencompra_id_actual" value="{{ (isset($data) && $data) ? $data->id : '' }}">
{{-- Cerca del inicio del POST por max_input_vars. Textarea: JSON largo en value="" del hidden suele truncarse o romperse al parsear. --}}
<textarea name="comprobantes_json" id="comprobantes_json" class="d-none" autocomplete="off" aria-hidden="true" tabindex="-1" rows="1" cols="20">@json($comprobantesInicial)</textarea>

<div id="oc-solapa-principal" class="oc-solapa">
    <div class="row">
        <div class="col-md-6">
            <input type="hidden" name="requisicion_id" id="requisicion_id" value="{{ old('requisicion_id', (isset($data) && $data) ? ($data->requisicion_id ?? '') : '') }}">

            <div class="form-group row">
                <label class="col-lg-4 control-label">Requisición origen</label>
                <div class="col-lg-8">
                    <div class="input-group">
                        <input type="text" class="form-control" id="requisicion_display" readonly
                            value="{{ old('requisicion_display', (isset($data) && $data && $data->requisicion_id && $data->requisiciones) ? ('#'.$data->requisiciones->numerorequisicion.' — id '.$data->requisicion_id) : '') }}"
                            placeholder="Opcional — use la lupa para buscar aprobadas">
                        @php
                            $ocRequiOrigenFija = !$soloLectura
                                && isset($data)
                                && $data
                                && ! empty($data->requisicion_id);
                        @endphp
                        @if (!$soloLectura && ! $ocRequiOrigenFija)
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-primary" id="btn-consulta-requisicion-modal" title="Buscar requisición aprobada">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        @endif
                        @if ($ocRequiOrigenFija)
                            <small class="form-text text-muted">La requisición de origen no se puede cambiar en una OC ya vinculada.</small>
                        @endif
                    </div>
                </div>
            </div>

            @include('includes.form-empresa-asignada', [
                'empresa_query' => $empresa_query,
                'empresa_id' => (isset($data) && $data) ? $data->empresa_id : null,
                'solo_lectura' => $soloLectura,
                'col_label' => 'col-lg-4',
                'col_input' => 'col-lg-8',
            ])

            <div class="form-group row">
                <label for="solicitante_show" class="col-lg-4 control-label">Solicitante</label>
                <div class="col-lg-8">
                    <input type="text" id="solicitante_show" class="form-control" value="{{ $solicitanteTexto }}" readonly tabindex="-1">
                </div>
            </div>

            <div class="form-group row">
                <label class="col-lg-4 control-label">Estado</label>
                <div class="col-lg-8">
                    <input type="text" class="form-control" readonly tabindex="-1"
                        value="{{ (isset($data) && $data) ? ($data->estadoordencompra ?? '—') : \App\Support\Compras\OrdencompraEstados::PENDIENTE }}">
                </div>
            </div>

            <div class="form-group row align-items-end">
                <label for="fecha" class="col-lg-4 control-label requerido">Fecha / entrega</label>
                <div class="col-lg-4">
                    <small class="text-muted d-block">Fecha documento</small>
                    <input type="date" name="fecha" id="fecha" class="form-control" required
                        value="{{ old('fecha', (isset($data) && $data && $data->fecha) ? substr($data->fecha, 0, 10) : date('Y-m-d')) }}"
                        {{ $soloLectura ? 'readonly' : '' }}>
                </div>
                <div class="col-lg-4">
                    <small class="text-muted d-block">Fecha entrega</small>
                    <input type="date" name="fechaentrega" id="fechaentrega" class="form-control" required
                        value="{{ old('fechaentrega', (isset($data) && $data && $data->fechaentrega) ? substr($data->fechaentrega, 0, 10) : date('Y-m-d')) }}"
                        {{ $soloLectura ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="form-group row">
                <label for="centrocosto_id" class="col-lg-4 control-label requerido">Centro costo</label>
                <div class="col-lg-8">
                    <select name="centrocosto_id" id="centrocosto_id" class="form-control" required {{ $soloLectura ? 'disabled' : '' }}>
                        @php
                            $centrocostoUsuario_id = (isset($data) && $data)
                                ? ($data->centrocosto_id ?? (auth()->user()->centrocosto_id ?? 1))
                                : (auth()->user()->centrocosto_id ?? 1);
                            $centrocostoSeleccionado = (int) old('centrocosto_id', $centrocostoUsuario_id);
                        @endphp
                        @foreach ($centrocosto_query as $cc)
                            <option value="{{ $cc->id }}" {{ (int) $centrocostoSeleccionado === (int) $cc->id ? 'selected' : '' }}>
                                {{ $cc->codigo }} — {{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group row align-items-center" id="div-proveedor-oc">
                <label for="codigoproveedor" class="col-lg-4 control-label">Proveedor</label>
                <div class="col-lg-8">
                    <input type="hidden" id="proveedor_id" name="proveedor_id" value="{{ old('proveedor_id', (isset($data) && $data) ? ($data->proveedor_id ?? '') : '') }}">
                    <div class="d-flex flex-wrap align-items-center">
                        <input type="text" class="form-control codigoproveedor mr-2" id="codigoproveedor" name="codigoproveedor"
                            value="{{ old('codigoproveedor', optional($reqProveedor)->codigo ?? '') }}" style="width: 6rem;" {{ $soloLectura ? 'readonly' : '' }}>
                        <input type="text" class="form-control mr-2" id="nombreproveedor" name="nombreproveedor"
                            value="{{ old('nombreproveedor', optional($reqProveedor)->nombre ?? '') }}" readonly style="min-width: 8rem; flex: 1;">
                        @if (!$soloLectura)
                            <button type="button" title="Consulta proveedores (F1)" class="btn-accion-tabla consultaproveedor tooltipsC flex-shrink-0">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            @if (isset($data) && $data)
                <div class="form-group row">
                    <label class="col-lg-4 control-label">Sector legajo</label>
                    <div class="col-lg-8">
                        <input type="text" class="form-control" readonly value="{{ optional($data->sector_legajocompras)->nombre ?? '—' }}">
                    </div>
                </div>
            @endif

            @php
                $tratamientoValorActual = old('tratamiento', (isset($data) && $data) ? $data->tratamiento : 'NO ANTICIPADA');
                $tratamientoBloqueado = !empty($tratamiento_bloqueado_por_movimientos) && !$soloLectura;
                $tratamientoDisabled = $soloLectura || $tratamientoBloqueado;
            @endphp
            <div class="form-group row">
                <label for="tratamiento" class="col-lg-4 control-label requerido">Tratamiento</label>
                <div class="col-lg-4">
                    @if ($tratamientoBloqueado)
                        <input type="hidden" name="tratamiento" value="{{ $tratamientoValorActual }}">
                    @endif
                    <select id="tratamiento" class="form-control" required {{ $tratamientoDisabled ? 'disabled' : '' }}
                        @if (!$tratamientoBloqueado)
                            name="tratamiento"
                        @endif
                        @if ($tratamientoBloqueado)
                            title="No se puede cambiar: la OC ya tiene recepción o factura asociada"
                        @endif
                    >
                        @foreach ($tratamiento_enum as $t)
                            <option value="{{ $t['nombre'] }}" {{ $tratamientoValorActual == $t['nombre'] ? 'selected' : '' }}>
                                {{ $t['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                    @if ($tratamientoBloqueado)
                        <small class="form-text text-muted">No se puede cambiar: la OC ya tiene recepción o factura asociada.</small>
                    @endif
                </div>
                <label for="numeroordencompra_show" class="col-lg-2 control-label">Nº OC</label>
                <div class="col-lg-2">
                    <input type="text" id="numeroordencompra_show" class="form-control" readonly tabindex="-1" aria-readonly="true"
                        value="{{ (isset($data) && $data) ? $data->numeroordencompra : (isset($proximoNumeroordencompra) ? $proximoNumeroordencompra : '') }}">
                </div>
            </div>

            <div class="form-group row">
                <label for="comentario" class="col-lg-4 control-label">Comentario</label>
                <div class="col-lg-8">
                    <input type="text" name="comentario" id="comentario" class="form-control" maxlength="255"
                        value="{{ old('comentario', (isset($data) && $data) ? $data->comentario : '') }}" {{ $soloLectura ? 'readonly' : '' }}>
                </div>
            </div>

            @if (empty($soloLectura) && empty($visualizar))
            <div class="form-group row">
                <label for="comentario_envio_arbol" class="col-lg-4 control-label">Comentario al &aacute;rbol</label>
                <div class="col-lg-8">
                    <textarea name="comentario_envio_arbol" id="comentario_envio_arbol" class="form-control" rows="2" maxlength="255"
                        placeholder="Opcional: se env&iacute;a al firmante si esta grabaci&oacute;n dispara el &aacute;rbol de aprobaci&oacute;n">{{ old('comentario_envio_arbol') }}</textarea>
                    <small class="form-text text-muted">No se guarda en la cabecera de la OC; solo acompa&ntilde;a el env&iacute;o al circuito.</small>
                </div>
            </div>
            @endif

            <div class="form-group row">
                <label for="condicioncompra_id" class="col-lg-4 control-label">Condición compra</label>
                <div class="col-lg-8">
                    <select name="condicioncompra_id" id="condicioncompra_id" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                        <option value="">—</option>
                        @foreach ($condicioncompra_query as $row)
                            <option value="{{ $row->id }}" {{ (int) old('condicioncompra_id', (isset($data) && $data) ? ($data->condicioncompra_id ?? 0) : 0) === (int) $row->id ? 'selected' : '' }}>
                                {{ $row->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group row">
                <label for="condicionentrega_id" class="col-lg-4 control-label">Condición entrega</label>
                <div class="col-lg-8">
                    <select name="condicionentrega_id" id="condicionentrega_id" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                        <option value="">—</option>
                        @foreach ($condicionentrega_query as $row)
                            <option value="{{ $row->id }}" {{ (int) old('condicionentrega_id', (isset($data) && $data) ? ($data->condicionentrega_id ?? 0) : 0) === (int) $row->id ? 'selected' : '' }}>
                                {{ $row->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group row">
                <label for="condicionpago_id" class="col-lg-4 control-label">Condición pago</label>
                <div class="col-lg-8">
                    <select name="condicionpago_id" id="condicionpago_id" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                        <option value="">—</option>
                        @foreach ($condicionpago_query as $row)
                            <option value="{{ $row->id }}" {{ (int) old('condicionpago_id', (isset($data) && $data) ? ($data->condicionpago_id ?? 0) : 0) === (int) $row->id ? 'selected' : '' }}>
                                {{ $row->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group row">
                <label for="transporte_id" class="col-lg-4 control-label">Transporte</label>
                <div class="col-lg-8">
                    <select name="transporte_id" id="transporte_id" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                        <option value="">—</option>
                        @foreach ($transporte_query as $row)
                            <option value="{{ $row->id }}" {{ (int) old('transporte_id', (isset($data) && $data) ? ($data->transporte_id ?? 0) : 0) === (int) $row->id ? 'selected' : '' }}>
                                {{ $row->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group row">
                <label for="lugarentrega" class="col-lg-4 control-label">Lugar entrega</label>
                <div class="col-lg-8">
                    <input type="text" name="lugarentrega" id="lugarentrega" class="form-control" maxlength="255"
                        value="{{ old('lugarentrega', (isset($data) && $data) ? ($data->lugarentrega ?? '') : '') }}" {{ $soloLectura ? 'readonly' : '' }}>
                </div>
            </div>
        </div>
    </div>

    <div class="form-group row">
        <label for="detalle" class="col-lg-2 col-form-label requerido">Detalle</label>
        <div class="col-lg-9">
            <textarea name="detalle" id="detalle" rows="3" class="form-control" required {{ $soloLectura ? 'readonly' : '' }}>{{ old('detalle', (isset($data) && $data) ? $data->detalle : '') }}</textarea>
        </div>
    </div>

    @include('compras.ordencompra.partials.bloque_contrato')

    <div class="card card-outline card-secondary mb-3">
        <div class="card-header">
            <strong>Condiciones de contratación</strong>
            <small class="text-muted">(resumen automático de comprobantes y cuotas; se actualiza al guardar)</small>
        </div>
        <div class="card-body">
            <textarea class="form-control" rows="5" readonly style="background:#f8f9fa;">{{ (isset($data) && $data) ? ($data->condiciones_contratacion ?? '') : '' }}</textarea>
        </div>
    </div>
</div>

    <div id="oc-solapa-articulos" class="oc-solapa" style="display:none;">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
        <h5 class="mb-0">Artículos</h5>
        @if (\App\Support\Compras\OrdencompraUiConfigSupport::entregaSemanal())
            <button type="button" class="btn btn-outline-info btn-sm oc-abrir-entrega-semanal-resumen"
                title="Ver todas las entregas semanales de la orden (matriz por artículo y fecha)">
                <i class="fa fa-calendar"></i> Entregas semanales (orden)
            </button>
        @endif
    </div>
    <p class="text-muted small mb-2">
        Requiere proveedor en cabecera. Si el art&iacute;culo tiene cat&aacute;logo <code>articulo_proveedor</code> para ese proveedor,
        se completan nombre, precio de lista, UM de compra y coeficiente (conversi&oacute;n a stock al recibir).
        Sin cat&aacute;logo, se usan datos del maestro.
    </p>
    <style>
        #tabla-articulos-ordencompra tr.item-ordencompra-articulo-sub td {
            border-top: none !important;
            padding-top: 0.15rem !important;
            padding-bottom: 0.15rem !important;
            vertical-align: middle;
        }
        #tabla-articulos-ordencompra .oc-meta-fila-una-linea {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.35rem 0.75rem;
            line-height: 1.25;
        }
        #tabla-articulos-ordencompra .oc-meta-fila-una-linea > .oc-meta-bloque {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            min-width: 0;
        }
        #tabla-articulos-ordencompra .oc-meta-fila-una-linea > .oc-meta-bloque-detalle {
            flex: 1 1 58%;
        }
        #tabla-articulos-ordencompra .oc-meta-fila-una-linea > .oc-meta-bloque-origen {
            flex: 1 1 38%;
            max-width: 42%;
        }
        #tabla-articulos-ordencompra .oc-linea-item-leyenda {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.8125rem;
            min-width: 0;
            flex: 1 1 0;
        }
        #tabla-articulos-ordencompra td.oc-cant-alt-celda {
            max-width: 5.5rem;
            vertical-align: middle;
        }
        #tabla-articulos-ordencompra .oc-cant-alt-texto {
            display: block;
            font-size: 0.75rem;
            line-height: 1.2;
            color: #495057;
            white-space: nowrap;
        }
        #tabla-articulos-ordencompra .oc-cant-alt-texto .oc-cant-alt-valor {
            font-weight: 600;
        }
        #tabla-articulos-ordencompra .oc-cant-alt-texto .oc-cant-alt-um {
            color: #6c757d;
            margin-left: 0.15rem;
        }
    </style>
    @php
        $modoStockColorTalleInicial = old('modo_stock_color_talle', '');
        if ($modoStockColorTalleInicial === '' && isset($data) && $data && $data->ordencompra_articulos) {
            foreach ($data->ordencompra_articulos as $linModo) {
                if ((bool) (optional($linModo->articulos)->maneja_stock_color_talle ?? false)
                    || (int) ($linModo->color_id ?? 0) > 0
                    || (int) ($linModo->talle_id ?? 0) > 0) {
                    $modoStockColorTalleInicial = '1';
                    break;
                }
            }
            if ($modoStockColorTalleInicial === '' && $data->ordencompra_articulos->count() > 0) {
                $modoStockColorTalleInicial = '0';
            }
        }
        $ocPedirPartidaCapex = \App\Support\Compras\OrdencompraUiConfigSupport::pedirPartidaCapex();
        $ocMostrarPesoArticulo = \App\Support\Compras\OrdencompraUiConfigSupport::mostrarPesoArticulo();
        $ocEntregaSemanal = \App\Support\Compras\OrdencompraUiConfigSupport::entregaSemanal();
        $ocColspanMetaArticulos = $soloLectura ? 16 : 17;
        if (! $ocPedirPartidaCapex) {
            $ocColspanMetaArticulos -= 2;
        }
        if ($ocMostrarPesoArticulo) {
            $ocColspanMetaArticulos += 2;
        }
        if ($ocEntregaSemanal) {
            $ocColspanMetaArticulos += 1;
        }
        $fechaDocOc = old('fecha', (isset($data) && $data && $data->fecha) ? substr($data->fecha, 0, 10) : date('Y-m-d'));
        $ocMonedaPesoId = (int) (
            optional($moneda_query->firstWhere('abreviatura', '$'))->id
            ?? optional($moneda_query->firstWhere('abreviatura', 'ARS'))->id
            ?? 1
        );
        $prevFeEntrega = $fechaDocOc;
    @endphp
    <div id="ms-ayuda-color-talle" class="alert alert-info py-2 small mb-2" style="display:none;">
        Este comprobante usa stock por color y talle: todas las líneas deben tener color y talle.
    </div>
    <input type="hidden" name="modo_stock_color_talle" id="modo_stock_color_talle" value="{{ $modoStockColorTalleInicial }}">
    <table class="table table-sm table-bordered" id="tabla-articulos-ordencompra"
        data-oc-cc-destino-default="{{ $centrocostoDefaultDestino }}"
        data-oc-moneda-peso-id="{{ $ocMonedaPesoId }}"
        data-oc-pedir-partida-capex="{{ $ocPedirPartidaCapex ? '1' : '0' }}"
        data-oc-mostrar-peso="{{ $ocMostrarPesoArticulo ? '1' : '0' }}"
        data-oc-entrega-semanal="{{ $ocEntregaSemanal ? '1' : '0' }}">
        <thead>
            <tr>
                <th style="width: 9%;">Artículo</th>
                <th style="width: 11%;">Descripción</th>
                <th style="width: 6%;" class="text-nowrap" title="Solo si el artículo tiene filas en articulo_proveedor: UM compra y coef. del catálogo">Prov./UM</th>
                <th class="ms-col-color-talle" style="width: 6%; display:none;">Color</th>
                <th class="ms-col-color-talle" style="width: 5%; display:none;">Talle</th>
                <th style="width: 5%;">Cant.</th>
                @if ($ocEntregaSemanal)
                    <th style="width: 5%;" class="text-nowrap oc-col-entrega-semanal" title="Entregas semanales (fecha/cantidad); la suma define la cantidad">Entregas</th>
                @endif
                @if ($ocMostrarPesoArticulo)
                    <th style="width: 5%;" class="text-nowrap oc-col-peso" title="Peso unitario (precarga del ABM; editable)">Peso unit.</th>
                    <th style="width: 5%;" class="text-nowrap oc-col-peso" title="Cantidad × peso unitario (se recalcula al editar)">Peso tot.</th>
                @endif
                <th style="width: 5%;" class="text-nowrap" title="Cantidad en unidad alternativa (cantidad × unidades x envase) o conversión UM compra → stock">Cant. alt.</th>
                <th style="width: 7%;">Precio</th>
                <th style="width: 5%;">Moneda</th>
                <th style="width: 5%;">Cotiz.</th>
                <th style="width: 7%;">F. entrega línea</th>
                <th style="width: 8%;">CC destino</th>
                @if ($ocPedirPartidaCapex)
                    <th style="width: 15%;">Partida presupuesto</th>
                    <th style="width: 14%;">CAPEX</th>
                @endif
                <th style="width: 6%;">Det. línea</th>
                <th style="width: 6%;" class="text-nowrap">Origen</th>
                @if (!$soloLectura)
                    <th style="width: 4%;"></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php
                $oldArticuloIds = old('articulo_ids');
                if (is_array($oldArticuloIds) && count(array_filter($oldArticuloIds, static fn ($v) => $v !== null && $v !== '')) > 0) {
                    $idsArtOld = [];
                    foreach ($oldArticuloIds as $aidOld) {
                        if ($aidOld !== null && $aidOld !== '') {
                            $idsArtOld[] = (int) $aidOld;
                        }
                    }
                    $artsOldMap = $idsArtOld === []
                        ? collect()
                        : OcArticuloForm::query()
                            ->with(['unidadesdemedidasalternativas'])
                            ->whereIn('id', array_values(array_unique($idsArtOld)))
                            ->get()
                            ->keyBy('id');
                    $lineas = collect();
                    foreach ($oldArticuloIds as $iOld => $aidOld) {
                        if ($aidOld === null || $aidOld === '') {
                            continue;
                        }
                        $linOld = new Ordencompra_Articulo([
                            'id' => old('ordencompra_articulo_ids.'.$iOld) ?: null,
                            'articulo_id' => (int) $aidOld,
                            'cantidad' => old('cantidades.'.$iOld, 1),
                            'precio' => old('precios.'.$iOld, 0),
                            'moneda_id' => old('moneda_linea_ids.'.$iOld, 1),
                            'cotizacion' => old('cotizaciones_linea.'.$iOld, 1),
                            'fechaentrega' => old('fechaentrega_articulos.'.$iOld),
                            'centrocostodestino_id' => old('centrocostodestino_ids.'.$iOld),
                            'partidagasto_id' => old('partidagasto_ids.'.$iOld) ?: null,
                            'capex_id' => old('capex_ids.'.$iOld) ?: null,
                            'detalle' => old('detalle_articulos.'.$iOld),
                            'descuento' => old('descuentos_linea.'.$iOld),
                            'cantidadalternativa' => old('cantidadalternativas.'.$iOld),
                            'peso_unitario' => old('peso_unitarios.'.$iOld),
                            'peso_total' => old('peso_totales.'.$iOld),
                            'requisicion_articulo_id' => old('requisicion_articulo_ids.'.$iOld) ?: null,
                            'articulo_proveedor_id' => old('articulo_proveedor_ids.'.$iOld) ?: null,
                            'precio_origen_tipo' => old('precio_origen_tipos.'.$iOld),
                            'precio_origen_ref_id' => old('precio_origen_ref_ids.'.$iOld) ?: null,
                            'precio_origen_etiqueta' => old('precio_origen_etiquetas.'.$iOld),
                            'color_id' => old('colores_id.'.$iOld) ?: null,
                            'talle_id' => old('talles_id.'.$iOld) ?: null,
                        ]);
                        $artRel = $artsOldMap->get((int) $aidOld);
                        if ($artRel) {
                            $linOld->setRelation('articulos', $artRel);
                        }
                        $lineas->push($linOld);
                    }
                    if ($lineas->isEmpty()) {
                        $lineas = collect([new Ordencompra_Articulo()]);
                    }
                } else {
                    $lineas = (isset($data) && $data && $data->ordencompra_articulos && $data->ordencompra_articulos->count())
                        ? $data->ordencompra_articulos
                        : collect([new Ordencompra_Articulo()]);
                }
            @endphp
            @foreach ($lineas as $idx => $linea)
                @php
                    $feLineVal = old('fechaentrega_articulos.'.$idx);
                    if ($feLineVal === null || $feLineVal === '') {
                        if (! empty($linea->fechaentrega)) {
                            $feLineVal = substr($linea->fechaentrega, 0, 10);
                        } elseif ($idx === 0) {
                            $feLineVal = $fechaDocOc;
                        } else {
                            $feLineVal = $prevFeEntrega;
                        }
                    }
                    $prevFeEntrega = $feLineVal;
                    $_pgDesc = old('descripcionpartidagastos.'.$idx);
                    if ($_pgDesc === null || $_pgDesc === '') {
                        $_pgDesc = optional($linea->partidagastos?->articulos)->descripcion
                            ?? optional($linea->partidagastos)->detalle
                            ?? '';
                    }
                    $_pgId = old('partidagasto_ids.'.$idx, $linea->partidagasto_id ?? '');
                    if (! empty($_pgId) && trim((string) $_pgDesc) === '') {
                        $_pgDesc = '(Sin descripción en artículo — partida asignada)';
                    }
                    $_reqArtId = old('requisicion_articulo_ids.'.$idx, $linea->requisicion_articulo_id ?? '');
                    $_poTipo = old('precio_origen_tipos.'.$idx, $linea->precio_origen_tipo ?? '');
                    $_poRef = old('precio_origen_ref_ids.'.$idx, $linea->precio_origen_ref_id ?? '');
                    $_poEtiq = old('precio_origen_etiquetas.'.$idx, $linea->precio_origen_etiqueta ?? '');
                    $_artLinea = $linea->articulos;
                    $_uxenv = (float) ($_artLinea?->unidadesxenvase ?? 0);
                    $_umAltAbrev = optional($_artLinea?->unidadesdemedidasalternativas)->abreviatura ?? '';
                    $_cantLinea = (float) old('cantidades.'.$idx, $linea->cantidad ?? 1);
                    $_cantAltOld = old('cantidadalternativas.'.$idx, $linea->cantidadalternativa ?? '');
                    if ($_uxenv > 0 && $_cantLinea != 0.0) {
                        $_cantAltCalc = $_cantLinea * $_uxenv;
                        $_cantAltShow = rtrim(rtrim(number_format($_cantAltCalc, 4, '.', ''), '0'), '.');
                    } else {
                        $_cantAltShow = ($_cantAltOld !== null && $_cantAltOld !== '')
                            ? rtrim(rtrim(number_format((float) $_cantAltOld, 4, '.', ''), '0'), '.')
                            : '';
                    }
                    $_colorIdLin = (int) old('colores_id.'.$idx, $linea->color_id ?? 0);
                    $_talleIdLin = (int) old('talles_id.'.$idx, $linea->talle_id ?? 0);
                    $_manejaColorTalle = (bool) old(
                        'maneja_stock_color_talle.'.$idx,
                        optional($linea->articulos)->maneja_stock_color_talle ?? ($_colorIdLin > 0 || $_talleIdLin > 0)
                    );
                    $_ap = $linea->articulo_proveedor;
                    $_apId = (int) old('articulo_proveedor_ids.'.$idx, $linea->articulo_proveedor_id ?? 0);
                    $_apCodigo = old('linea_codigo_articulo_proveedor.'.$idx, optional($_ap)->codigo_articulo_proveedor ?? '');
                    $_apCoef = old('linea_coef_conversion.'.$idx, optional($_ap)->coeficiente_conversion ?? '');
                    $_apUm = old('linea_um_compra_abrev.'.$idx, optional(optional($_ap)->unidadesmedidacompra)->abreviatura ?? '');
                    $_provEtiq = '';
                    if ($_apId > 0 && $_ap) {
                        $_p = $_ap->proveedores;
                        $_provEtiq = trim((string) (optional($_p)->codigo ?? '').' '.(optional($_p)->nombre ?? ''));
                        if ($_apUm !== '') {
                            $_provEtiq = trim($_provEtiq.' · '.$_apUm);
                        }
                    }
                    $_descLineaOc = old('descripcionarticulos.'.$idx);
                    if ($_descLineaOc === null || $_descLineaOc === '') {
                        $_descLineaOc = trim((string) (optional($_ap)->nombre_articulo_proveedor ?? '')) !== ''
                            ? (string) $_ap->nombre_articulo_proveedor
                            : (optional($linea->articulos)->descripcion ?? '');
                    }
                    $_pesoUnit = old('peso_unitarios.'.$idx, $linea->peso_unitario ?? null);
                    if (($_pesoUnit === null || $_pesoUnit === '') && $ocMostrarPesoArticulo) {
                        $_pesoUnit = optional($linea->articulos)->peso ?? '';
                    }
                    $_pesoUnitShow = ($_pesoUnit !== null && $_pesoUnit !== '')
                        ? rtrim(rtrim(number_format((float) $_pesoUnit, 6, '.', ''), '0'), '.')
                        : '';
                    $_pesoTotalOld = old('peso_totales.'.$idx, $linea->peso_total ?? null);
                    if ($_pesoTotalOld !== null && $_pesoTotalOld !== '') {
                        $_pesoTotalShow = rtrim(rtrim(number_format((float) $_pesoTotalOld, 6, '.', ''), '0'), '.');
                    } elseif ($_pesoUnitShow !== '' && $_cantLinea > 0) {
                        $_pesoTotalShow = rtrim(rtrim(number_format((float) $_pesoUnitShow * $_cantLinea, 6, '.', ''), '0'), '.');
                    } else {
                        $_pesoTotalShow = '';
                    }
                    $_entregasJson = old('entregas_semanal_json.'.$idx);
                    if ($_entregasJson === null || $_entregasJson === '') {
                        $_entregasCol = $linea->relationLoaded('entregas')
                            ? $linea->entregas
                            : collect();
                        $_entregasArr = $_entregasCol->map(static function ($e) {
                            return [
                                'fecha' => $e->fecha ? substr((string) $e->fecha, 0, 10) : '',
                                'cantidad' => (float) $e->cantidad,
                            ];
                        })->filter(static function ($e) {
                            return $e['fecha'] !== '' && (float) $e['cantidad'] > 0;
                        })->values()->all();
                        $_entregasJson = json_encode($_entregasArr, JSON_UNESCAPED_UNICODE);
                    }
                    $_entregasCount = 0;
                    $_entregasDecoded = json_decode((string) $_entregasJson, true);
                    if (is_array($_entregasDecoded)) {
                        $_entregasCount = count($_entregasDecoded);
                    }
                    $_cantConEntregas = $ocEntregaSemanal && $_entregasCount > 0;
                @endphp
                <tr class="item-ordencompra-articulo" data-maneja-stock-color-talle="{{ $_manejaColorTalle ? '1' : '0' }}">
                    <td>
                        <input type="hidden" class="ordencompra_articulo_id" name="ordencompra_articulo_ids[]" value="{{ old('ordencompra_articulo_ids.'.$idx, $linea->id ?? '') }}">
                        <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{ old('articulo_ids.'.$idx, $linea->articulo_id ?? '') }}">
                        <input type="hidden" class="oc-requisicion-articulo-id" name="requisicion_articulo_ids[]" value="{{ $_reqArtId }}">
                        <input type="hidden" class="oc-precio-origen-tipo" name="precio_origen_tipos[]" value="{{ $_poTipo }}">
                        <input type="hidden" class="oc-precio-origen-ref-id" name="precio_origen_ref_ids[]" value="{{ $_poRef }}">
                        <input type="hidden" class="oc-precio-origen-etiqueta" name="precio_origen_etiquetas[]" value="{{ $_poEtiq }}">
                        <input type="hidden" class="linea-articulo-proveedor-id" name="articulo_proveedor_ids[]" value="{{ $_apId > 0 ? $_apId : '' }}">
                        <input type="hidden" class="linea-codigo-articulo-proveedor" name="linea_codigo_articulo_proveedor[]" value="{{ $_apCodigo }}">
                        <input type="hidden" class="linea-coef-conversion" value="{{ $_apCoef !== '' && $_apCoef !== null ? $_apCoef : '' }}">
                        <input type="hidden" class="linea-um-compra-abrev" value="{{ $_apUm }}">
                        <input type="hidden" name="descuentos_linea[]" value="{{ old('descuentos_linea.'.$idx, $linea->descuento ?? '') }}">
                        <input type="hidden" name="cantidadalternativas[]" class="oc-cantidadalternativa" value="{{ $_cantAltShow }}">
                        @if ($ocEntregaSemanal)
                            <input type="hidden" class="oc-entregas-semanal-json" name="entregas_semanal_json[]" value="{{ $_entregasJson }}">
                        @endif
                        @if (! $ocPedirPartidaCapex)
                            <input type="hidden" class="partidagasto_id" name="partidagasto_ids[]" value="{{ old('partidagasto_ids.'.$idx, $linea->partidagasto_id ?? '') }}">
                            <input type="hidden" class="capex_id" name="capex_ids[]" value="{{ old('capex_ids.'.$idx, $linea->capex_id ?? '') }}">
                        @endif
                        <textarea name="detalle_articulos[]" class="d-none oc-ta-detalle-linea" aria-hidden="true">{{ old('detalle_articulos.'.$idx, $linea->detalle ?? '') }}</textarea>
                        <div class="form-group row celda-articulo-ordencompra mb-0 d-flex align-items-center flex-nowrap">
                            @if (!$soloLectura)
                                <button type="button" title="Consulta art&iacute;culos (F1)" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                            @endif
                            <input type="text" class="codigoarticulo form-control flex-shrink-0" style="width: 140px; max-width: 15vw; height: 38px;"
                                name="codigoarticulos[]" value="{{ old('codigoarticulos.'.$idx, optional($linea->articulos)->sku ?? '') }}" {{ $soloLectura ? 'readonly' : '' }}>
                        </div>
                    </td>
                    <td>
                        <input type="text" class="descripcionarticulo form-control" name="descripcionarticulos[]"
                            value="{{ $_descLineaOc }}" readonly>
                    </td>
                    <td class="align-middle px-1">
                        <small class="linea-proveedor-etiqueta text-muted d-block text-truncate" style="max-width: 6.5rem;" title="{{ e($_provEtiq) }}">{{ $_provEtiq !== '' ? $_provEtiq : '—' }}</small>
                        <div class="linea-conversion-hint{{ ($_apUm !== '' && (float) $_apCoef > 0) ? '' : ' d-none' }}">
                            @if ($_apUm !== '' && (float) $_apCoef > 0)
                                <small class="text-muted" title="Al recibir: stock = cantidad compra × coef">{{ $_apUm }} ×{{ rtrim(rtrim(number_format((float) $_apCoef, 6, '.', ''), '0'), '.') }} → stock</small>
                            @endif
                        </div>
                    </td>
                    @include('stock.movimientostock.partials.fila_color_talle', [
                        'colorId' => $_colorIdLin,
                        'talleId' => $_talleIdLin,
                        'manejaColorTalle' => $_manejaColorTalle,
                    ])
                    <td>
                        <input type="number" step="0.0001" name="cantidades[]" class="form-control cantidad-linea{{ $_cantConEntregas ? ' oc-cant-desde-entregas' : '' }}"
                            value="{{ old('cantidades.'.$idx, $linea->cantidad ?? '1') }}"
                            @if ($soloLectura || $_cantConEntregas)
                                readonly
                            @endif
                            title="{{ $_cantConEntregas ? 'Cantidad = suma de entregas semanales' : '' }}">
                        <input type="hidden" class="oc-unidadesxenvase" value="{{ $_uxenv > 0 ? $_uxenv : '' }}">
                        <input type="hidden" class="oc-um-alt-abrev" value="{{ $_umAltAbrev }}">
                    </td>
                    @if ($ocEntregaSemanal)
                        <td class="align-middle p-1 text-center oc-col-entrega-semanal">
                            <button type="button"
                                class="btn btn-sm btn-outline-info oc-abrir-entrega-semanal"
                                title="{{ $soloLectura ? 'Ver entregas semanales' : 'Cargar / consultar entregas semanales' }}">
                                <i class="fa fa-calendar"></i>
                                <span class="badge badge-secondary oc-entregas-count{{ $_entregasCount > 0 ? '' : ' d-none' }}">{{ $_entregasCount > 0 ? $_entregasCount : '' }}</span>
                            </button>
                        </td>
                    @endif
                    @if ($ocMostrarPesoArticulo)
                        <td class="oc-col-peso align-middle px-1">
                            <input type="number" step="0.000001" min="0" name="peso_unitarios[]" class="form-control form-control-sm oc-peso-unitario"
                                value="{{ $_pesoUnitShow }}"
                                title="Peso unitario (precarga del ABM; editable)"
                                {{ $soloLectura ? 'readonly' : '' }}>
                        </td>
                        <td class="oc-col-peso align-middle px-1">
                            <input type="number" step="0.000001" name="peso_totales[]" class="form-control form-control-sm oc-peso-total"
                                value="{{ $_pesoTotalShow }}" readonly title="Cantidad × peso unitario (recalculado)">
                        </td>
                    @endif
                    <td class="oc-cant-alt-celda text-right px-1">
                        <span class="oc-cant-alt-texto" title="Cantidad × unidades x envase">
                            @if ($_uxenv > 0 && $_cantAltShow !== '')
                                <span class="oc-cant-alt-valor">{{ $_cantAltShow }}</span>
                                @if ($_umAltAbrev !== '')
                                    <span class="oc-cant-alt-um">{{ $_umAltAbrev }}</span>
                                @endif
                            @else
                                <span class="oc-cant-alt-valor text-muted">—</span>
                                <span class="oc-cant-alt-um"></span>
                            @endif
                        </span>
                    </td>
                    <td>
                        <input type="number" step="0.0001" name="precios[]" class="form-control precio-linea"
                            value="{{ old('precios.'.$idx, $linea->precio ?? '0') }}" {{ $soloLectura ? 'readonly' : '' }}>
                    </td>
                    <td>
                        <select name="moneda_linea_ids[]" class="form-control oc-moneda-linea" {{ $soloLectura ? 'disabled' : '' }}>
                            @foreach ($moneda_query as $moneda)
                                <option value="{{ $moneda->id }}" {{ (int) old('moneda_linea_ids.'.$idx, $linea->moneda_id ?? 1) === (int) $moneda->id ? 'selected' : '' }}>
                                    {{ $moneda->abreviatura }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.0001" min="0" name="cotizaciones_linea[]" class="form-control oc-cotizacion-linea"
                            value="{{ old('cotizaciones_linea.'.$idx, $linea->cotizacion ?? 1) }}" {{ $soloLectura ? 'readonly' : '' }}>
                    </td>
                    <td>
                        <input type="date" name="fechaentrega_articulos[]" class="form-control"
                            value="{{ $feLineVal }}" {{ $soloLectura ? 'readonly' : '' }}>
                    </td>
                    <td>
                        <select name="centrocostodestino_ids[]" class="form-control" {{ $soloLectura ? 'disabled' : '' }}>
                            @foreach ($centrocosto_query as $cc)
                                <option value="{{ $cc->id }}" {{ (int) old('centrocostodestino_ids.'.$idx, $linea->centrocostodestino_id ?? $centrocostoDefaultDestino) === (int) $cc->id ? 'selected' : '' }}>
                                    {{ $cc->codigo }}-{{ $cc->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    @if ($ocPedirPartidaCapex)
                        <td class="align-middle">
                            <div class="celda-partidagasto d-flex align-items-center flex-nowrap">
                                <input type="hidden" class="partidagasto_id" name="partidagasto_ids[]" value="{{ old('partidagasto_ids.'.$idx, $linea->partidagasto_id ?? '') }}">
                                @if (!$soloLectura)
                                    <button type="button" title="Consulta partidas (F1, &uacute;ltimo presupuesto)" style="padding:1;" class="btn-accion-tabla consultapartidagasto tooltipsC flex-shrink-0">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                @endif
                                <input type="text" class="codigopartidagasto form-control form-control-sm ml-1" style="width: 4.25rem; flex: 0 0 auto;" name="codigopartidagastos[]"
                                    value="{{ old('codigopartidagastos.'.$idx, optional($linea->partidagastos)->codigo ?? '') }}" {{ $soloLectura ? 'readonly' : '' }}>
                                <input type="text" class="descripcionpartidagasto form-control form-control-sm ml-1 flex-grow-1" style="min-width: 0; font-size: 0.8rem;" name="descripcionpartidagastos[]"
                                    value="{{ $_pgDesc }}" readonly title="{{ $_pgDesc }}">
                            </div>
                        </td>
                        <td class="align-middle">
                            <div class="celda-capex d-flex align-items-center flex-nowrap">
                                <input type="hidden" class="capex_id" name="capex_ids[]" value="{{ old('capex_ids.'.$idx, $linea->capex_id ?? '') }}">
                                @if (!$soloLectura)
                                    <button type="button" title="Consulta CAPEX (F1, &uacute;ltimo presupuesto)" style="padding:1;" class="btn-accion-tabla consultacapex tooltipsC flex-shrink-0">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                @endif
                                <input type="text" class="codigocapex form-control form-control-sm ml-1" style="width: 4.25rem; flex: 0 0 auto;" name="codigocapexs[]"
                                    value="{{ old('codigocapexs.'.$idx, optional($linea->capexs)->codigo ?? '') }}" {{ $soloLectura ? 'readonly' : '' }}>
                                <input type="text" class="descripcioncapex form-control form-control-sm ml-1 flex-grow-1" style="min-width: 0; font-size: 0.8rem;" name="descripcioncapexs[]"
                                    value="{{ old('descripcioncapexs.'.$idx, optional($linea->capexs)->nombre ?? '') }}" readonly title="{{ old('descripcioncapexs.'.$idx, optional($linea->capexs)->nombre ?? '') }}">
                            </div>
                        </td>
                    @endif
                    <td class="align-middle p-1 text-center">
                        @if (!$soloLectura)
                            <button type="button" title="Editar detalle de la línea" class="btn btn-sm btn-outline-secondary oc-abrir-detalle-linea">
                                <i class="fa fa-align-left"></i>
                            </button>
                        @else
                            <button type="button" title="Ver detalle de la línea" class="btn btn-sm btn-outline-secondary oc-abrir-detalle-linea">
                                <i class="fa fa-eye"></i>
                            </button>
                        @endif
                    </td>
                    <td class="align-middle p-1 text-center">
                        @if (!$soloLectura)
                            <button type="button" class="btn btn-sm btn-outline-primary oc-btn-origen-precio py-0 px-1" style="font-size: 0.7rem; line-height: 1.2;" title="Elegir origen del precio (lista, presupuesto o requisición)">
                                <i class="fa fa-tags" aria-hidden="true"></i><span class="sr-only"> Origen</span>
                            </button>
                        @endif
                    </td>
                    @if (!$soloLectura)
                        <td class="text-center">
                            <button type="button" title="Eliminar línea" class="btn-accion-tabla eliminar_ordencompra_articulo tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    @endif
                </tr>
                <tr class="item-ordencompra-articulo-sub">
                    <td colspan="{{ $ocColspanMetaArticulos }}" class="px-2 bg-light">
                        <div class="oc-meta-fila-una-linea">
                            <div class="oc-meta-bloque oc-meta-bloque-detalle">
                                <span class="font-weight-bold text-secondary small text-nowrap flex-shrink-0 mr-1">Detalle línea</span>
                                <div class="oc-detalle-linea-badge oc-linea-item-leyenda text-body"></div>
                            </div>
                            <div class="oc-meta-bloque oc-meta-bloque-origen border-left pl-2 ml-1">
                                <span class="font-weight-bold text-secondary small text-nowrap flex-shrink-0 mr-1">Origen precio</span>
                                <div class="oc-origen-precio-resumen oc-linea-item-leyenda text-body" title="{{ $_poEtiq }}">{{ $_poEtiq !== '' && $_poEtiq !== null ? $_poEtiq : '—' }}</div>
                            </div>
                            @if ($ocEntregaSemanal)
                                <div class="oc-meta-bloque oc-meta-bloque-entregas border-left pl-2 ml-1">
                                    <span class="font-weight-bold text-secondary small text-nowrap flex-shrink-0 mr-1">Entregas</span>
                                    <div class="oc-entregas-semanal-resumen oc-linea-item-leyenda text-body">—</div>
                                </div>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if (!$soloLectura)
        <button type="button" class="btn btn-danger btn-sm" id="agrega_renglon_ordencompra_articulo">+ Agregar renglón</button>
    @endif
    @if ($ocEntregaSemanal)
        <button type="button" class="btn btn-outline-info btn-sm ml-1 oc-abrir-entrega-semanal-resumen"
            title="Ver todas las entregas semanales de la orden">
            <i class="fa fa-calendar"></i> Entregas semanales (orden)
        </button>
    @endif

    <div class="modal fade" id="modalOcDetalleLinea" tabindex="-1" role="dialog" aria-labelledby="modalOcDetalleLineaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="modalOcDetalleLineaLabel">Detalle de la línea</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">Texto adicional guardado en la línea del ítem (orden de compra).</p>
                    <textarea id="oc_detalle_linea_editor" class="form-control" rows="7" placeholder="Observaciones específicas de esta línea…"></textarea>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                    @if (!$soloLectura)
                        <button type="button" class="btn btn-primary btn-sm" id="oc_detalle_linea_guardar">Guardar</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalOcOrigenPrecio" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Origen del precio de la línea</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2" id="modalOcOrigenPrecioSubtitulo"></p>
                    <div id="modalOcOrigenPrecioCargando" class="text-center text-muted py-3 d-none">Cargando opciones…</div>
                    <div id="modalOcOrigenPrecioError" class="alert alert-danger d-none"></div>
                    <div id="modalOcOrigenPrecioOpciones"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    @if ($ocEntregaSemanal)
        @include('compras.ordencompra.partials.modal_entrega_semanal', [
            'soloLectura' => $soloLectura,
        ])
        @include('compras.ordencompra.partials.modal_entrega_semanal_resumen')
    @endif

    @php
        $ocTot = $oc_totales_resumen ?? \App\Support\Compras\OrdencompraTotalesResumen::vacioParaVista();
        $fmtOc = static fn ($v) => number_format((float) $v, 2, ',', '.');
        $filasIvaOc = $ocTot['filas_iva'] ?? [];
    @endphp
    <div class="card border-secondary mt-3 mb-2" id="oc-panel-totales">
        <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center">
            <strong>Totales de la orden</strong>
            <small class="text-muted">Importes en moneda del primer ítem: <span id="oc-tot-mon-abrev">{{ $ocTot['moneda_abrev'] !== '' ? $ocTot['moneda_abrev'] : '—' }}</span></small>
        </div>
        <div class="card-body py-3">
            <div class="row">
                <div class="col-lg-4 mb-3 mb-lg-0">
                    @php
                        $ocDtoTipo = old(
                            'descuento_tipo',
                            (isset($data) && $data)
                                ? ($data->descuento_tipo ?? \App\Support\Compras\OrdencompraDescuentoSupport::TIPO_PORCENTAJE)
                                : \App\Support\Compras\OrdencompraDescuentoSupport::TIPO_PORCENTAJE
                        );
                        $ocDtoTipo = \App\Support\Compras\OrdencompraDescuentoSupport::normalizarTipo($ocDtoTipo);
                        $ocDtoEsImporte = $ocDtoTipo === \App\Support\Compras\OrdencompraDescuentoSupport::TIPO_IMPORTE;
                    @endphp
                    <label for="descuento" class="d-block font-weight-bold">Descuento general</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <select name="descuento_tipo" id="descuento_tipo" class="custom-select"
                                style="max-width: 7.5rem;" {{ $soloLectura ? 'disabled' : '' }}>
                                <option value="porcentaje" {{ $ocDtoTipo === 'porcentaje' ? 'selected' : '' }}>%</option>
                                <option value="importe" {{ $ocDtoTipo === 'importe' ? 'selected' : '' }}>Monto</option>
                            </select>
                            @if ($soloLectura)
                                <input type="hidden" name="descuento_tipo" value="{{ $ocDtoTipo }}">
                            @endif
                        </div>
                        <input type="number" step="0.01" min="0" name="descuento" id="descuento" class="form-control"
                            value="{{ old('descuento', (isset($data) && $data) ? ($data->descuento ?? '') : '') }}"
                            {{ $soloLectura ? 'readonly' : '' }}
                            placeholder="{{ $ocDtoEsImporte ? '0.00' : '0' }}">
                    </div>
                    <small class="text-muted" id="descuento_ayuda">
                        @if ($ocDtoEsImporte)
                            Monto fijo sobre el neto de ítems antes del IVA (en moneda del 1.er ítem).
                        @else
                            Porcentaje sobre el neto de ítems antes del IVA.
                        @endif
                    </small>
                </div>
                <div class="col-lg-8">
                    <table class="table table-sm table-borderless mb-0 oc-tabla-resumen-totales">
                        <tbody>
                            <tr>
                                <td class="text-muted pl-0">Subtotal ítems <span class="small">(sin IVA; cant. × precio × cotiz. por línea en moneda del 1.er ítem)</span></td>
                                <td class="text-right font-weight-bold text-nowrap" id="oc-tot-sub">{{ $fmtOc($ocTot['subtotal_bruto_sin_iva']) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted pl-0">Descuento</td>
                                <td class="text-right text-nowrap" id="oc-tot-dto">-{{ $fmtOc($ocTot['importe_descuento']) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted pl-0">Neto ítems <span class="small">(sin impuesto)</span></td>
                                <td class="text-right font-weight-bold text-nowrap" id="oc-tot-neto">{{ $fmtOc($ocTot['neto_sin_iva']) }}</td>
                            </tr>
                            @if (count($filasIvaOc) > 1)
                                @foreach ($filasIvaOc as $fi)
                                    <tr class="oc-fila-iva-detalle">
                                        <td class="text-muted pl-0">IVA {{ rtrim(rtrim(number_format((float) $fi['tasa'], 2, ',', '.'), '0'), ',') }}%</td>
                                        <td class="text-right text-nowrap">{{ $fmtOc($fi['importe']) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td class="text-muted pl-0">Total IVA <span class="small">(nacional)</span></td>
                                    <td class="text-right text-nowrap" id="oc-tot-iva">{{ $fmtOc($ocTot['iva_total']) }}</td>
                                </tr>
                            @elseif (count($filasIvaOc) === 1)
                                @php $fi0 = $filasIvaOc[0]; @endphp
                                <tr>
                                    <td class="text-muted pl-0">IVA {{ rtrim(rtrim(number_format((float) $fi0['tasa'], 2, ',', '.'), '0'), ',') }}% <span class="small">(nacional)</span></td>
                                    <td class="text-right text-nowrap" id="oc-tot-iva">{{ $fmtOc($fi0['importe']) }}</td>
                                </tr>
                            @else
                                <tr>
                                    <td class="text-muted pl-0">IVA <span class="small">(nacional)</span></td>
                                    <td class="text-right text-nowrap" id="oc-tot-iva">{{ $fmtOc($ocTot['iva_total']) }}</td>
                                </tr>
                            @endif
                            <tr class="border-top">
                                <td class="pl-0 pt-2"><strong>Total orden</strong></td>
                                <td class="text-right pt-2 text-nowrap">
                                    <strong>
                                        <span id="oc-tot-final-moneda" class="text-muted mr-1">{{ $ocTot['moneda_abrev'] !== '' ? $ocTot['moneda_abrev'] : '—' }}</span><span id="oc-tot-final">{{ $fmtOc($ocTot['total']) }}</span>
                                    </strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="oc-solapa-comprobantes" class="oc-solapa" style="display:none;">
    <h5>Comprobantes a venir</h5>
    <p class="text-muted small">Agregue cada comprobante esperado y defina sus cuotas con el asistente por condición de pago o de forma manual.</p>

    @if (!$soloLectura)
        <div class="mb-2">
            <button type="button" class="btn btn-danger btn-sm" id="oc_btn_agregar_comprobante"><i class="fa fa-plus"></i> Agregar comprobante</button>
        </div>
    @endif

    <div class="table-responsive mb-3">
        <table class="table table-bordered table-sm" id="oc_tabla_comprobantes_resumen">
            <thead class="thead-light">
                <tr>
                    <th>#</th>
                    <th>Tipo</th>
                    <th>Vencimiento</th>
                    <th class="text-right">Monto</th>
                    <th>Moneda</th>
                    <th style="max-width: 14rem;">Detalle</th>
                    <th>Cuotas</th>
                    <th class="text-nowrap" data-orderable="false"></th>
                </tr>
            </thead>
            <tbody id="oc_tabla_comprobantes_body"></tbody>
        </table>
    </div>

    @include('compras.ordencompra._modales_comprobantes')
</div>

<div id="oc-solapa-archivos" class="oc-solapa" style="display:none;">
    @include('compras.ordencompra.partials.solapa_archivos_ordencompra', [
        'data' => $data ?? null,
        'visualizar' => $visualizar ?? null,
    ])
</div>

@if (isset($data) && $data)
    <div id="oc-solapa-historia-legajo" class="oc-solapa" style="display:none;">
        <h5>Historia del legajo (sectores)</h5>
        <table class="table table-bordered table-sm" id="tabla-historia-legajo">
            <thead><tr><th>Fecha</th><th>Sector</th><th>Observación</th><th>Leyenda</th><th>Usuario</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>

    <div id="oc-solapa-historia-estados" class="oc-solapa" style="display:none;">
        <h5>Historia de estados</h5>
        <table class="table table-bordered table-sm" id="tabla-historia-estados">
            <thead><tr><th>Fecha y hora</th><th>Estado</th><th>Observación</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>

    <div id="oc-solapa-recepciones" class="oc-solapa" style="display:none;">
        <div id="oc-recepciones-resumen" class="alert alert-light border small mb-2 d-none"></div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm table-hover" id="tabla-recepciones-oc">
                <thead style="background-color:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:2.5rem;" title="Expandir detalle de líneas">Det.</th>
                        <th>Documento</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Total</th>
                        <th>Mon.</th>
                        <th>Usuario</th>
                        <th>Diferencias</th>
                        <th style="min-width:14rem;">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <p id="oc-recepciones-vacio" class="text-muted small d-none mb-0">No hay recepciones ni devoluciones vinculadas a esta orden de compra.</p>
    </div>

    <div id="oc-solapa-historia-precios" class="oc-solapa" style="display:none;">
        <p class="small text-muted mb-2">
            Cambios de precio en ítems de la OC aplicados al confirmar una recepción o manualmente desde la solapa Recepciones.
        </p>
        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="tabla-historia-precios-oc">
                <thead style="background-color:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Fecha</th>
                        <th>SKU</th>
                        <th>Descripci&oacute;n</th>
                        <th class="text-right">Precio anterior</th>
                        <th class="text-right">Precio nuevo</th>
                        <th>Origen</th>
                        <th>Recepci&oacute;n</th>
                        <th>Usuario</th>
                        <th>Comentario</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <p id="oc-historia-precios-vacio" class="text-muted small d-none mb-0">No hay cambios de precio registrados para esta orden de compra.</p>
    </div>

    <div id="oc-solapa-arbol" class="oc-solapa" style="display:none;">
        <div id="oc-aviso-arbol" class="alert alert-warning d-none"></div>
        <div id="oc-panel-ia-arbol-solapa" class="d-none mb-3"></div>
        <h5>Movimientos árbol de aprobación</h5>
        <table class="table table-bordered table-sm" id="tabla-movimientos-arbol">
            <thead><tr><th>Nivel</th><th>Estado mov.</th><th>Indicación OC</th><th>Obs.</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
@endif
