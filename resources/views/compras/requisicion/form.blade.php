@php
    $oficinaCompraId = old('oficinacompra_id', (isset($data) && $data) ? ($data->oficinacompra_id ?? null) : null);
    $oficinaCompraNombre = '';
    if (!empty($oficinaCompraId) && isset($oficinacompra_query)) {
        $oficinaCompraNombre = optional($oficinacompra_query->firstWhere('id', (int) $oficinaCompraId))->nombre ?? '';
    }
    // Solo consulta cuando $visualizar es truthy (no bastaba isset(): en editar viene false y ocultaba edición).
    $soloLectura = isset($visualizar) && $visualizar;
    $edicionLimitadaAprobada = !empty($edicionLimitadaAprobada);
    $cabeceraSoloLectura = $soloLectura || $edicionLimitadaAprobada;
    $proveedorEditable = ! $soloLectura || $edicionLimitadaAprobada;
    $lineasSoloLectura = $cabeceraSoloLectura;

    if (isset($data) && $data) {
        $solicitanteTexto = trim((string) (optional($data->usuarios)->nombre ?? ''));
    } else {
        $solicitanteTexto = trim((string) (auth()->user()->nombre ?? ''));
    }
@endphp
<div id="tab1" class="form1 tab-content">
    <div class="row">
        <div class="col-sm-6">
            <input type="hidden" name="requisicion_id" id="requisicion_id" value="{{ (isset($data) && $data) ? $data->id : '' }}">
            @if($edicionLimitadaAprobada && isset($data))
                <input type="hidden" name="empresa_id" value="{{ old('empresa_id', $data->empresa_id) }}">
                <input type="hidden" name="fecha" value="{{ old('fecha', $data->fecha ? substr($data->fecha, 0, 10) : '') }}">
                <input type="hidden" name="fechaentrega" value="{{ old('fechaentrega', $data->fechaentrega ? substr($data->fechaentrega, 0, 10) : '') }}">
                <input type="hidden" name="centrocosto_id" value="{{ old('centrocosto_id', $data->centrocosto_id) }}">
                <input type="hidden" name="tratamiento" value="{{ old('tratamiento', $data->tratamiento) }}">
                <input type="hidden" name="contrataciondirecta" value="{{ old('contrataciondirecta', $data->contrataciondirecta) }}">
                <input type="hidden" name="estado" value="{{ old('estado', $data->estado) }}">
            @endif
            <input type="hidden" name="oficinacompra_id" id="oficinacompra_id" value="{{ old('oficinacompra_id', (isset($data) && $data) ? ($data->oficinacompra_id ?? '') : '') }}">
            @if(isset($oficinacompra_query))
                <script>
                    window.oficinacompraMap = window.oficinacompraMap || {};
                    @foreach($oficinacompra_query as $oc)
                        window.oficinacompraMap["{{ $oc->id }}"] = @json($oc->nombre);
                    @endforeach
                </script>
            @endif

            @include('includes.form-empresa-asignada', [
                'empresa_query' => $empresa_query,
                'empresa_id' => (isset($data) && $data) ? $data->empresa_id : null,
                'solo_lectura' => $cabeceraSoloLectura,
                'col_input' => 'col-lg-5',
            ])

            <div class="form-group row">
                <label for="oficinacompra_id_show" class="col-lg-3 control-label">Oficina compra</label>
                <div class="col-lg-5">
                    <input type="text" id="oficinacompra_id_show" class="form-control" value="{{ $oficinaCompraNombre }}" readonly>
                </div>
            </div>

            <div class="form-group row">
                <label for="solicitante_show" class="col-lg-3 control-label">Solicitante</label>
                <div class="col-lg-5">
                    <input type="text" id="solicitante_show" class="form-control" value="{{ $solicitanteTexto }}" readonly tabindex="-1" aria-readonly="true">
                </div>
            </div>

            <div class="form-group row">
                <label for="fecha" class="col-lg-3 control-label requerido">Fecha</label>
                <div class="col-lg-3">
                    <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', (isset($data) && $data && $data->fecha) ? substr($data->fecha, 0, 10) : date('Y-m-d')) }}" required {{ $cabeceraSoloLectura ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="form-group row">
                <label for="fechaentrega" class="col-lg-3 control-label requerido">Fecha entrega</label>
                <div class="col-lg-3">
                    <input type="date" name="fechaentrega" id="fechaentrega" class="form-control" value="{{ old('fechaentrega', (isset($data) && $data && $data->fechaentrega) ? substr($data->fechaentrega, 0, 10) : date('Y-m-d')) }}" required {{ $cabeceraSoloLectura ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="form-group row">
                <label for="centrocosto_id" class="col-lg-3 control-label requerido">Centro costo</label>
                <div class="col-lg-4">
                    <select name="centrocosto_id" id="centrocosto_id" class="form-control" required {{ $cabeceraSoloLectura ? 'disabled' : '' }}>
                        @php $centrocostoUsuario_id = (isset($data) && $data) ? $data->centrocosto_id : (auth()->user()->centrocosto_id ?? 1); @endphp
                        @foreach ($centrocosto_query as $cc)
                            @if ($cc->id > 0)
                                @if ($cc->id == $centrocostoUsuario_id)
                                    <option value="{{ $cc->id }}" selected>
                                        {{ $cc->codigo }} - {{ $cc->nombre }}
                                    </option>
                                @endif
                            @else
                                <option value="{{ $cc->id }}" {{ (int) old('centrocosto_id', (isset($data) && $data) ? $data->centrocosto_id : '') === (int) $cc->id ? 'selected' : '' }}>
                                    {{ $cc->codigo }} - {{ $cc->nombre }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
            @php
                $reqProveedor = (isset($data) && $data) ? $data->proveedores : null;
                $condicionPagoProveedorNombre = optional(optional($reqProveedor)->condicionpagos)->nombre ?? '';
            @endphp
            <div class="form-group row align-items-center" id="div-proveedor">
                <label for="codigoproveedor" class="col-lg-3 control-label">Proveedor sugerido</label>
                <div class="col-lg-9">
                    <input type="hidden" id="proveedor_id" name="proveedor_id" value="{{ old('proveedor_id', (isset($data) && $data) ? ($data->proveedor_id ?? '') : '') }}">
                    <div class="d-flex flex-wrap align-items-center">
                        <input type="text" class="form-control codigoproveedor mr-2" id="codigoproveedor" name="codigoproveedor" value="{{ old('codigoproveedor', optional($reqProveedor)->codigo ?? '') }}" style="width: 6.5rem; max-width: 30%; flex-shrink: 0;" {{ !$proveedorEditable ? 'readonly' : '' }}>
                        <input type="text" class="form-control mr-2" id="nombreproveedor" name="nombreproveedor" value="{{ old('nombreproveedor', optional($reqProveedor)->nombre ?? '') }}" readonly style="min-width: 8rem; flex: 1 1 8rem;">
                        @if($proveedorEditable)
                        <button type="button" title="Consulta proveedores (F1)" class="btn-accion-tabla consultaproveedor tooltipsC mr-2 mr-md-3 flex-shrink-0">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        @endif
                        <div class="d-flex align-items-center flex-grow-1" style="min-width: 12rem;">
                            <label for="condicionpago_proveedor_show" class="control-label mb-0 mr-2 text-nowrap">C.pago</label>
                            <input type="text" class="form-control" id="condicionpago_proveedor_show" readonly tabindex="-1" value="{{ $condicionPagoProveedorNombre }}" placeholder="—">
                        </div>
                    </div>
                    <span id="nombretiposuspension" class="col-form-label text-danger small mb-0 d-block"></span>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="formapago_id" class="col-lg-3 control-label">Forma de pago</label>
                <div class="col-lg-4">
                    <select name="formapago_id" id="formapago_id" class="form-control" {{ $cabeceraSoloLectura ? 'disabled' : '' }}>
                        <option value="">—</option>
                        @foreach ($formapago_query as $fp)
                            <option value="{{ $fp->id }}" {{ (int) old('formapago_id', (isset($data) && $data) ? $data->formapago_id : '') === (int) $fp->id ? 'selected' : '' }}>
                                {{ $fp->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <label for="numerorequisicion" class="col-lg-2 control-label">Requisición</label>
                <div class="col-lg-2">
                    <input type="text" name="numerorequisicion" id="numerorequisicion" class="form-control" value="{{ old('numerorequisicion', (isset($data) && $data) ? $data->numerorequisicion : '') }}" readonly>
                </div>
            </div>

            <div class="form-group row">
                <label for="tratamiento" class="col-lg-3 control-label requerido">Tratamiento</label>
                <div class="col-lg-4">
                    <select name="tratamiento" id="tratamiento" class="form-control" required {{ $cabeceraSoloLectura ? 'disabled' : '' }}>
                        @foreach ($tratamiento_enum as $t)
                            <option value="{{ $t['nombre'] }}" {{ old('tratamiento', (isset($data) && $data) ? $data->tratamiento : 'Normal') == $t['nombre'] ? 'selected' : '' }}>
                                {{ $t['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group row">
                <label for="motivotratamiento" class="col-lg-3 control-label">Motivo tratamiento</label>
                <div class="col-lg-8">
                    <input type="text" name="motivotratamiento" id="motivotratamiento" class="form-control" value="{{ old('motivotratamiento', (isset($data) && $data) ? $data->motivotratamiento : '') }}" {{ $cabeceraSoloLectura ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="form-group row">
                <label for="contrataciondirecta" class="col-lg-3 control-label requerido">Contratación directa</label>
                <div class="col-lg-4">
                    <select name="contrataciondirecta" id="contrataciondirecta" class="form-control" required {{ $cabeceraSoloLectura ? 'disabled' : '' }}>
                        @foreach ($contratacionDirecta_enum as $t)
                            <option value="{{ $t['nombre'] }}" {{ old('contrataciondirecta', (isset($data) && $data) ? $data->contrataciondirecta : 'Normal') == $t['nombre'] ? 'selected' : '' }}>
                                {{ $t['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>            

            <div class="form-group row">
                <label for="comentario" class="col-lg-3 control-label">Comentario</label>
                <div class="col-lg-8">
                    <input type="text" name="comentario" id="comentario" class="form-control" value="{{ old('comentario', (isset($data) && $data) ? $data->comentario : '') }}" {{ $cabeceraSoloLectura ? 'readonly' : '' }}>
                </div>
            </div>

            @if(isset($data))
            <div class="form-group row">
                <label for="estado" class="col-lg-3 control-label">Estado</label>
                <div class="col-lg-5">
                    @if(!empty($es_provisorio))
                        <input type="hidden" name="estado" value="{{ $estado_provisorio ?? 'PROVISORIO' }}">
                        <input type="text" id="estado" class="form-control" value="{{ $estado_provisorio ?? 'PROVISORIO' }}" readonly>
                    @else
                    <select name="estado" id="estado" class="form-control" {{ $cabeceraSoloLectura ? 'disabled' : '' }}>
                        @foreach ($estado_enum as $e)
                            @if(($e['nombre'] ?? '') === 'PROVISORIO')
                                @continue
                            @endif
                            <option value="{{ $e['nombre'] }}" {{ old('estado', $data->estado ?? '') == $e['nombre'] ? 'selected' : '' }}>
                                {{ $e['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group row">
            <label for="detalle" class="col-lg-3 col-form-label">Detalle</label>
            <div class="col-lg-6">
                <textarea name="detalle" id="detalle" rows="3" class="form-control" {{ $cabeceraSoloLectura ? 'readonly' : '' }}>{{ old('detalle', (isset($data) && $data) ? $data->detalle : '') }}</textarea>
            </div>
        </div>
    </div>
    <hr>
    <h5>Artículos</h5>
    <p class="text-muted small mb-2">
        Si el art&iacute;culo tiene datos en la solapa <strong>Proveedores</strong> del maestro (<code>articulo_proveedor</code>),
        al cargarlo se completan nombre, precio de lista, UM de compra y proveedor de la l&iacute;nea.
        Sin cat&aacute;logo, se usan los datos del maestro como hasta ahora.
    </p>
    @php
        $centrocostoDefaultDestino = (int) (
            (isset($data) && $data && $data->centrocosto_id)
                ? $data->centrocosto_id
                : (auth()->user()->centrocosto_id ?? 1)
        );
        $monedaDefaultLinea = 1;
        if (isset($data) && $data && $data->requisicion_articulos && $data->requisicion_articulos->count()) {
            $monedaDefaultLinea = (int) ($data->requisicion_articulos->first()->moneda_id ?? 1);
        }
        $modoStockColorTalleInicial = old('modo_stock_color_talle', '');
        if ($modoStockColorTalleInicial === '' && isset($data) && $data && $data->requisicion_articulos) {
            foreach ($data->requisicion_articulos as $linModo) {
                if ((bool) (optional($linModo->articulos)->maneja_stock_color_talle ?? false)
                    || (int) ($linModo->color_id ?? 0) > 0
                    || (int) ($linModo->talle_id ?? 0) > 0) {
                    $modoStockColorTalleInicial = '1';
                    break;
                }
            }
            if ($modoStockColorTalleInicial === '' && $data->requisicion_articulos->count() > 0) {
                $modoStockColorTalleInicial = '0';
            }
        }
    @endphp
    <div id="ms-ayuda-color-talle" class="alert alert-info py-2 small mb-2" style="display:none;">
        Este comprobante usa stock por color y talle: todas las líneas deben tener color y talle.
    </div>
    <input type="hidden" name="modo_stock_color_talle" id="modo_stock_color_talle" value="{{ $modoStockColorTalleInicial }}">
    <style>
        #tabla-articulos-requisicion tbody tr.req-requisicion-linea-cerrada td {
            background-color: rgba(25, 135, 84, 0.1);
        }
        #tabla-articulos-requisicion tbody tr.req-requisicion-linea-cerrada:hover td {
            background-color: rgba(25, 135, 84, 0.16);
        }
        #tabla-articulos-requisicion td.req-cant-alt-celda {
            max-width: 5.5rem;
            vertical-align: middle;
        }
        #tabla-articulos-requisicion .req-cant-alt-texto {
            display: block;
            font-size: 0.75rem;
            line-height: 1.2;
            color: #495057;
            white-space: nowrap;
        }
        #tabla-articulos-requisicion .req-cant-alt-texto .req-cant-alt-valor {
            font-weight: 600;
        }
        #tabla-articulos-requisicion .req-cant-alt-texto .req-cant-alt-um {
            color: #6c757d;
            margin-left: 0.15rem;
        }
    </style>
    <table class="table" id="tabla-articulos-requisicion" data-requisicion-cc-destino-default="{{ $centrocostoDefaultDestino }}" data-requisicion-moneda-default="{{ $monedaDefaultLinea }}">
        <thead>
            <tr>
                <th style="width: 11%;">Artículo</th>
                <th style="width: 12%;">Descripción</th>
                <th style="width: 7%;" class="text-nowrap" title="Solo si el artículo tiene filas en articulo_proveedor: proveedor/UM de compra del catálogo">Prov.</th>
                <th class="ms-col-color-talle" style="width: 7%; display:none;">Color</th>
                <th class="ms-col-color-talle" style="width: 6%; display:none;">Talle</th>
                <th style="width: 7%;">Cantidad</th>
                <th style="width: 6%;" class="text-nowrap" title="Cantidad en unidad alternativa (cantidad × unidades x envase) o conversión UM compra → stock">Cant. alt.</th>
                <th style="width: 7%;">Precio unit.</th>
                <th style="width: 5%;">Moneda</th>
                <th style="width: 9%;">CC destino</th>
                <th style="width: 18%;">Partida presupuesto</th>
                <th style="width: 18%;">Capex</th>
                @if(!$lineasSoloLectura)
                <th style="width: 4%;"></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php
                $lineas = (isset($data) && $data && $data->requisicion_articulos && $data->requisicion_articulos->count())
                    ? $data->requisicion_articulos
                    : collect([new \App\Models\Compras\Requisicion_Articulo()]);
            @endphp
            @foreach ($lineas as $idx => $linea)
            @php
                $_etiqCierre = trim((string) old('precio_origen_etiqueta_linea.'.$idx, $linea->precio_origen_etiqueta ?? ''));
                $_lineaCerrada = $_etiqCierre !== '';
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
                $_provLinId = (int) old('linea_proveedor_ids.'.$idx, $linea->proveedor_id ?? 0);
                $_apId = (int) old('articulo_proveedor_ids.'.$idx, $linea->articulo_proveedor_id ?? 0);
                $_apCodigo = old('linea_codigo_articulo_proveedor.'.$idx, optional($_ap)->codigo_articulo_proveedor ?? '');
                $_apCoef = old('linea_coef_conversion.'.$idx, optional($_ap)->coeficiente_conversion ?? '');
                $_apUm = old('linea_um_compra_abrev.'.$idx, optional(optional($_ap)->unidadesmedidacompra)->abreviatura ?? '');
                $_provEtiq = '';
                if ($_provLinId > 0) {
                    $_p = $linea->proveedores ?? optional($_ap)->proveedores;
                    $_provEtiq = trim((string) (optional($_p)->codigo ?? '').' '.(optional($_p)->nombre ?? ''));
                }
                $_descLinea = old('descripcionarticulos.'.$idx);
                if ($_descLinea === null || $_descLinea === '') {
                    $_descLinea = trim((string) (optional($_ap)->nombre_articulo_proveedor ?? '')) !== ''
                        ? (string) $_ap->nombre_articulo_proveedor
                        : (optional($linea->articulos)->descripcion ?? '');
                }
            @endphp
            <tr class="item-requisicion-articulo{{ $_lineaCerrada ? ' req-requisicion-linea-cerrada' : '' }}"@if($_lineaCerrada) title="{{ e($_etiqCierre) }}"@endif
                data-maneja-stock-color-talle="{{ $_manejaColorTalle ? '1' : '0' }}">
                <td>
                    <input type="hidden" class="requisicion_articulo_id" name="requisicion_articulo_ids[]" value="{{ old('requisicion_articulo_ids.'.$idx, $linea->id ?? '') }}">
                    <input type="hidden" name="cantidadalternativas[]" class="req-cantidadalternativa" value="{{ $_cantAltShow }}">
                    <input type="hidden" class="linea-proveedor-id" name="linea_proveedor_ids[]" value="{{ $_provLinId > 0 ? $_provLinId : '' }}">
                    <input type="hidden" class="linea-articulo-proveedor-id" name="articulo_proveedor_ids[]" value="{{ $_apId > 0 ? $_apId : '' }}">
                    <input type="hidden" class="linea-codigo-articulo-proveedor" name="linea_codigo_articulo_proveedor[]" value="{{ $_apCodigo }}">
                    <input type="hidden" class="linea-coef-conversion" value="{{ $_apCoef !== '' && $_apCoef !== null ? $_apCoef : '' }}">
                    <input type="hidden" class="linea-um-compra-abrev" value="{{ $_apUm }}">
                    <div class="form-group row celda-articulo-requisicion mb-0 d-flex align-items-center flex-nowrap">
                        <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{ old('articulo_ids.'.$idx, $linea->articulo_id ?? '') }}" >
                        <button type="button" title="Consulta art&iacute;culos (F1)" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0">
                                <i class="fa fa-search text-primary"></i>
                        </button>
                        <button type="button" title="Consultar listas de precios de compra (si no hay artículo, muestra las últimas listas vigentes del proveedor)" style="padding:1;" class="btn-accion-tabla consultalistasprecio tooltipsC flex-shrink-0">
                                <i class="fa fa-tags text-info"></i>
                        </button>
                        <input type="text" class="codigoarticulo codigoarticulolocal form-control flex-shrink-0" style="width: 140px; max-width: 15vw; height: 38px;" name="codigoarticulos[]" value="{{ optional($linea->articulos)->sku ?? '' }}" {{ $cabeceraSoloLectura ? 'readonly' : '' }} >
                    </div>
                </td>
                <td>
                    <input type="text" class="descripcionarticulo form-control" name="descripcionarticulos[]" value="{{ $_descLinea }}" readonly>
                </td>
                <td class="align-middle px-1">
                    <small class="linea-proveedor-etiqueta text-muted d-block text-truncate" style="max-width: 7rem;" title="{{ e($_provEtiq) }}">{{ $_provEtiq !== '' ? $_provEtiq : '—' }}</small>
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
                    <input type="number" step="0.0001" name="cantidades[]" class="form-control cantidad-linea" value="{{ old('cantidades.'.$idx, $linea->cantidad ?? '1') }}" {{ $cabeceraSoloLectura ? 'readonly' : '' }}>
                    <input type="hidden" class="req-unidadesxenvase" value="{{ $_uxenv > 0 ? $_uxenv : '' }}">
                    <input type="hidden" class="req-um-alt-abrev" value="{{ $_umAltAbrev }}">
                </td>
                <td class="req-cant-alt-celda text-right px-1">
                    <span class="req-cant-alt-texto" title="Cantidad × unidades x envase">
                        @if ($_uxenv > 0 && $_cantAltShow !== '')
                            <span class="req-cant-alt-valor">{{ $_cantAltShow }}</span>
                            @if ($_umAltAbrev !== '')
                                <span class="req-cant-alt-um">{{ $_umAltAbrev }}</span>
                            @endif
                        @else
                            <span class="req-cant-alt-valor text-muted">—</span>
                            <span class="req-cant-alt-um"></span>
                        @endif
                    </span>
                </td>
                <td>
                    <input type="number" step="0.0001" name="precios[]" class="form-control precio-linea" value="{{ old('precios.'.$idx, $linea->precio ?? '0') }}" {{ $cabeceraSoloLectura ? 'readonly' : '' }}>
                </td>
                <td>
                    <select name="moneda_linea_ids[]" class="form-control" {{ $cabeceraSoloLectura ? 'disabled' : '' }}>
                        @foreach ($moneda_query as $moneda)
                            <option value="{{ $moneda->id }}" {{ (int) old('moneda_linea_ids.'.$idx, $linea->moneda_id ?? 1) === (int) $moneda->id ? 'selected' : '' }}>
                                {{ $moneda->abreviatura }}
                            </option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <select name="centrocostodestino_ids[]" class="form-control" {{ $cabeceraSoloLectura ? 'disabled' : '' }}>
                        @foreach ($centrocosto_query as $cc)
                            <option value="{{ $cc->id }}" {{ (int) old('centrocostodestino_ids.'.$idx, $linea->centrocostodestino_id ?? $centrocostoDefaultDestino) === (int) $cc->id ? 'selected' : '' }}>
                                {{ $cc->codigo }}-{{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                </td>
                <td class="align-middle">
                    <div class="celda-partidagasto d-flex align-items-center flex-nowrap">
                        <input type="hidden" class="partidagasto_id" name="partidagasto_ids[]" value="{{ old('partidagasto_ids.'.$idx, $linea->partidagasto_id ?? '') }}" >
                        <button type="button" title="Consulta partidas (F1, &uacute;ltimo presupuesto)" style="padding:1;" class="btn-accion-tabla consultapartidagasto tooltipsC flex-shrink-0">
                                <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text" class="codigopartidagasto form-control form-control-sm ml-1" style="width: 4.25rem; flex: 0 0 auto;" name="codigopartidagastos[]" value="{{ optional($linea->partidagastos)->codigo ?? '' }}" {{ $cabeceraSoloLectura ? 'readonly' : '' }} >
                        <input type="text" class="descripcionpartidagasto form-control form-control-sm ml-1 flex-grow-1" style="min-width: 0; font-size: 0.8rem;" name="descripcionpartidagastos[]" value="{{ old('descripcionpartidagastos.'.$idx, optional($linea->partidagastos?->articulos)->detalle ?? '') }}" readonly title="{{ old('descripcionpartidagastos.'.$idx, optional($linea->partidagastos?->articulos)->detalle ?? '') }}">
                    </div>
                </td>
                <td class="align-middle">
                    <div class="celda-capex d-flex align-items-center flex-nowrap">
                        <input type="hidden" class="capex_id" name="capex_ids[]" value="{{ old('capex_ids.'.$idx, $linea->capex_id ?? '') }}">
                        <button type="button" title="Consulta CAPEX (F1, &uacute;ltimo presupuesto)" style="padding:1;" class="btn-accion-tabla consultacapex tooltipsC flex-shrink-0">
                                <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text" class="codigocapex form-control form-control-sm ml-1" style="width: 4.25rem; flex: 0 0 auto;" name="codigocapexs[]" value="{{ optional($linea->capexs)->codigo ?? '' }}" {{ $cabeceraSoloLectura ? 'readonly' : '' }} >
                        <input type="text" class="descripcioncapex form-control form-control-sm ml-1 flex-grow-1" style="min-width: 0; font-size: 0.8rem;" name="descripcioncapexs[]" value="{{ old('descripcioncapexs.'.$idx, optional($linea->capexs)->nombre ?? '') }}" readonly title="{{ old('descripcioncapexs.'.$idx, optional($linea->capexs)->nombre ?? '') }}">
                    </div>
                </td>
                @if(!$lineasSoloLectura)
                <td class="text-center">
                    <button type="button" title="Eliminar línea" class="btn-accion-tabla eliminar_requisicion_articulo tooltipsC">
                        <i class="fa fa-times-circle text-danger"></i>
                    </button>
                </td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>
    @php
        $reqTotMonto = 0.0;
        $reqTotMonAbrev = '—';
        if (isset($data) && $data) {
            $totReq = \App\Support\Compras\RequisicionTotalesCabecera::desdeModelo(
                $data,
                app(\App\Queries\Configuracion\CotizacionQueryInterface::class)
            );
            $reqTotMonto = (float) ($totReq['monto'] ?? 0);
            $abReqTot = trim((string) ($totReq['monedacabecera_abreviatura'] ?? ''));
            $reqTotMonAbrev = $abReqTot !== '' ? $abReqTot : '—';
        }
        $fmtReqTot = static fn ($v) => number_format((float) $v, 2, ',', '.');
    @endphp
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-2 mb-3">
        @if(!$lineasSoloLectura)
        <button type="button" class="btn btn-danger mb-2 mb-md-0" id="agrega_renglon_requisicion_articulo">+ Agrega rengl&oacute;n</button>
        @else
        <div></div>
        @endif
        <div class="card border-secondary mb-0" id="req-panel-totales">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <span class="text-muted small mb-1 mb-sm-0 mr-3">Total requisici&oacute;n</span>
                    <strong class="text-nowrap" style="font-size: 1.1rem;">
                        <span id="req-tot-moneda" class="text-muted mr-1">{{ $reqTotMonAbrev }}</span><span id="req-tot-importe">{{ $fmtReqTot($reqTotMonto) }}</span>
                    </strong>
                </div>
                <div class="text-muted small mt-1">
                    Importes en moneda del primer &iacute;tem (cotizaci&oacute;n del d&iacute;a).
                </div>
            </div>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.presupuesto.modalconsultapartidagasto', ['centrocosto_query' => $centrocosto_query ?? null])
@include('includes.presupuesto.modalconsultacapex', ['centrocosto_query' => $centrocosto_query ?? null])
@include('includes.compras.modalconsultaproveedor')
@include('includes.compras.modalconsultalistasprecio')
@include('includes.compras.modal_elegir_articulo_proveedor')
