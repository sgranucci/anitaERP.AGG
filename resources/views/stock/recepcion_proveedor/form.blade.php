@php
    use App\Models\Configuracion\Moneda;
    use App\Support\Stock\RecepcionProveedorAccionLineaOc;
    use App\Support\Stock\RecepcionProveedorFormItemsSupport;
    use App\Support\Stock\RecepcionProveedorImpuestoInternoSupport;
    use App\Support\Stock\RecepcionProveedorParteUnicaSupport;

    $moneda_query = $moneda_query ?? Moneda::query()->orderBy('nombre')->get();

    $soloLectura = ! ($modoEdicion ?? false)
        || ((($recepcion->estado ?? 'BORRADOR') !== 'BORRADOR') && ! ($modoDevolucion ?? false));
    $empresaIdOc = old('empresa_id', optional(optional($recepcion)->ordencompras)->empresa_id ?? optional($recepcion)->empresa_id ?? '');
    $depositoCabeceraEmpresaId = optional(optional($recepcion)->depositos)->empresa_id ?? null;
    $depositoCabeceraTipo = optional(optional($recepcion)->depositos)->tipodeposito ?? '';
    $depositoCabeceraId = old('deposito_id', optional($recepcion)->deposito_id ?? '');
    $depositoCabeceraIdInt = (int) $depositoCabeceraId ?: null;
    $depositoCabecera = optional(optional($recepcion)->depositos);
    $depositoCabeceraResuelto = RecepcionProveedorFormItemsSupport::depositoCabeceraDesdeId($depositoCabeceraIdInt);
    if ($depositoCabeceraResuelto !== null) {
        $depositoCabeceraCodigo = $depositoCabeceraResuelto['codigo'];
        $depositoCabeceraNombre = $depositoCabeceraResuelto['nombre'];
    } else {
        $depositoCabeceraCodigo = $depositoCabecera->codigo ?? '';
        $depositoCabeceraNombre = $depositoCabecera->nombre ?? '';
    }

    $ordencompraIdForm = (int) old('ordencompra_id', $recepcion->ordencompra_id ?? 0) ?: null;
    $cabeceraOld = RecepcionProveedorFormItemsSupport::datosCabeceraDesdeOrdencompra(
        $ordencompraIdForm,
        old('proveedor_nombre'),
        old('numero_oc_buscar')
    );
    $numeroOcBuscar = $cabeceraOld['numero_oc'] ?? '';
    $proveedorNombreForm = $cabeceraOld['proveedor_nombre'] ?? '';
    $proveedorIdForm = (int) old('proveedor_id', $cabeceraOld['proveedor_id'] ?? optional(optional($recepcion)->proveedores)->id ?? 0) ?: '';
    if ($cabeceraOld['empresa_id'] ?? null) {
        $empresaIdOc = $cabeceraOld['empresa_id'];
    }
    $empresaNombreOc = old(
        'empresa_nombre_oc',
        $cabeceraOld['empresa_nombre']
            ?? optional(optional(optional($recepcion)->ordencompras)->empresas)->nombre
            ?? ''
    );

    $items = old('items');
    if (is_array($items) && $items !== []) {
        foreach ($items as $idxItem => $itemOld) {
            if (! is_array($itemOld)) {
                continue;
            }
            if ($cabeceraOld['proveedor_id'] ?? null) {
                $items[$idxItem]['_proveedor_id'] = $cabeceraOld['proveedor_id'];
            }
            if ($empresaIdOc) {
                $items[$idxItem]['_empresa_id'] = (int) $empresaIdOc;
            }
        }
        $items = RecepcionProveedorFormItemsSupport::enriquecerItemsParaVista(
            $items,
            $depositoCabeceraIdInt,
            $cabeceraOld['proveedor_id'] ?? null,
            $empresaIdOc ? (int) $empresaIdOc : null
        );
    } elseif ($items === null && $recepcion) {
        if (($recepcion->estado ?? '') === 'BORRADOR' && !($modoDevolucion ?? false)) {
            $items = RecepcionProveedorFormItemsSupport::itemsGrillaDesdeRecepcion(
                $recepcion,
                $depositoCabeceraIdInt
            );
        } else {
        $items = $recepcion->recepcion_proveedor_articulos->map(function ($l) {
            return array_merge($l->toArray(), [
                'moneda_id' => (int) ($l->moneda_id ?: 1),
                'cotizacion' => (float) ($l->cotizacion ?: 1),
                'sku' => optional($l->articulos)->sku ?? '',
                'descripcion' => optional($l->articulos)->descripcion
                    ?? $l->detalle
                    ?? optional($l->ordencompra_articulos)->detalle
                    ?? '',
                'deposito_nombre' => optional($l->depositos)->nombre ?? '',
                'depositoentrega_id' => optional($l->articulos)->depositoentrega_id ?? null,
                'coeficiente_articulo' => (float) (optional($l->articulos)->coeficienteconversion ?? 1) ?: 1,
                'coeficiente_proveedor' => (float) ($l->coeficienteconversion ?? 1),
                'es_deposito_formula' => optional($l->depositos)->tipodeposito === 'Formulas',
                'articulo_stock_id' => $l->articulo_stock_id,
                'articulo_stock_sku' => optional($l->articulo_stock)->sku ?? '',
                'skualternativo' => optional($l->articulos)->skualternativo ?? '',
                'maneja_parte_unica' => RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($l->articulos),
                'color_id' => $l->color_id ? (int) $l->color_id : null,
                'talle_id' => $l->talle_id ? (int) $l->talle_id : null,
                'color_nombre' => optional($l->color)->nombre ?? '',
                'talle_nombre' => optional($l->talle)->nombre ?? '',
                'maneja_stock_color_talle' => (bool) (optional($l->articulos)->maneja_stock_color_talle
                    ?? (($l->color_id || $l->talle_id) ? true : false)),
            ]);
        })->values()->all();
        $items = RecepcionProveedorFormItemsSupport::enriquecerItemsParaVista(
            $items,
            $depositoCabeceraIdInt,
            $recepcion->proveedor_id ?? ($proveedorIdForm ?? null),
            $empresaIdOc ? (int) $empresaIdOc : null
        );
        }
    }
    if ($modoDevolucion ?? false) {
        $items = array_values(array_map(static function (array $item): array {
            $tieneMaxOrigen = array_key_exists('cantidad_recepcionada_origen', $item)
                && $item['cantidad_recepcionada_origen'] !== null
                && $item['cantidad_recepcionada_origen'] !== '';
            if (! $tieneMaxOrigen) {
                $recibida = (float) ($item['cantidad'] ?? 0);
                $item['cantidad_recepcionada_origen'] = $recibida;
                $item['cantidad'] = $recibida;
                $item['cantidad_rechazada'] = 0;
                $item['accion_linea_oc'] = $recibida > 0.000001
                    ? RecepcionProveedorAccionLineaOc::RECIBIR
                    : RecepcionProveedorAccionLineaOc::PENDIENTE;
            }

            return $item;
        }, $items ?? []));
    }
    $items = $items ?? [];

    $urlConsultaOc = static function (?int $ordencompraId): ?string {
        if (! $ordencompraId) {
            return null;
        }

        return route('editar_ordencompra', [
            'id' => $ordencompraId,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    };
    $puedeConsultarOc = can('editar-ordencompra', false) || can('listar-ordencompra', false);
    $puedeModificarPrecio = can('modificar-precio-recepcion-proveedor', false);
    $puedeAgregarArticuloExtra = \App\Support\Stock\RecepcionProveedorArticuloExtraSupport::puedeAgregar();
    $descuentoOcCabecera = (float) ($cabeceraOld['descuento_ordencompra'] ?? optional(optional($recepcion)->ordencompras)->descuento ?? 0);
    $tipoArticuloCigarrilloId = RecepcionProveedorImpuestoInternoSupport::tipoArticuloCigarrilloId();
    $impuestoInternoValor = old('impuesto_interno', optional($recepcion)->impuesto_interno);
@endphp

<input type="hidden" id="empresa_id" name="empresa_id" value="{{ $empresaIdOc }}">
<input type="hidden" id="proveedor_id" name="proveedor_id" value="{{ $proveedorIdForm }}">

<div class="text-center py-2 border-bottom rounded-top bg-white mb-3">
    <button type="button" id="rp-boton-principal" class="btn btn-primary btn-sm mx-1 rp-tab-solapa font-weight-bold">Recepción</button>
    @if($recepcion)
    <button type="button" id="rp-boton-historia-estados" class="btn btn-info btn-sm mx-1 rp-tab-solapa">Historia estados</button>
    @if(! empty($asientoPreview['activo']))
    <button type="button" id="rp-boton-asiento-contable" class="btn btn-info btn-sm mx-1 rp-tab-solapa">
        <span class="fa fa-calculator"></span> Asiento contable
        @if(! empty($asientoPreview['error']))
        <span class="badge badge-warning ml-1" title="Revise el cuadre antes de confirmar">!</span>
        @elseif(! empty($recepcion->asiento_id))
        <span class="badge badge-light ml-1">OK</span>
        @endif
    </button>
    @endif
    <button type="button" id="rp-boton-archivos" class="btn btn-info btn-sm mx-1 rp-tab-solapa">
        <span class="fa fa-paperclip"></span> Archivos asociados
        @if($recepcion->recepcion_proveedor_archivos->count())
        <span class="badge badge-light ml-1">{{ $recepcion->recepcion_proveedor_archivos->count() }}</span>
        @endif
    </button>
    @if (!empty($mostrar_solapa_ingresos))
    <button type="button" id="rp-boton-ingresos" class="btn btn-info btn-sm mx-1 rp-tab-solapa">
        <span class="fa fa-id-badge"></span> Ingresos
        <span class="badge badge-light ml-1 ingreso-solapa-badge-count">{{ ($tickets_ingreso ?? collect())->count() }}</span>
    </button>
    @endif
    @if (!empty($mostrar_solapa_validacion))
    <button type="button" id="rp-boton-validacion" class="btn btn-info btn-sm mx-1 rp-tab-solapa">
        <span class="fa fa-check-square-o"></span> Validación
        @if (!empty($validacionAbonoCompleta) && ($validacionAbono ?? null))
        <span class="badge badge-light ml-1">OK</span>
        @elseif ($validacionAbono ?? null)
        <span class="badge badge-warning ml-1">Pendiente</span>
        @endif
    </button>
    @endif
    @endif
</div>

<div id="rp-solapa-principal" class="rp-solapa">
<div class="form-group row">
    <label class="col-lg-2 col-form-label text-right">
        Nº OC
        @if(!$soloLectura)
        <i class="fa fa-question-circle text-muted tooltipsC ml-1"
           title="Enter o Tab para cargar. Si el remito no trae OC legible, ingrese el número o búsquela con la lupa, cargue la OC y luego suba el OCR para aplicar cantidades."></i>
        @endif
    </label>
    <div class="col-lg-3">
        @if($soloLectura)
            <div class="d-flex flex-wrap align-items-center" style="gap: 4px;">
                <input type="text" class="form-control flex-grow-1" readonly value="{{ optional($recepcion->ordencompras)->numeroordencompra ?? '' }}">
                <input type="hidden" name="ordencompra_id" id="ordencompra_id" value="{{ $recepcion->ordencompra_id ?? '' }}">
                @if($puedeConsultarOc)
                <a href="{{ $urlConsultaOc((int) ($recepcion->ordencompra_id ?? 0)) ?: '#' }}"
                   id="btn-consultar-oc-recepcion"
                   class="btn btn-sm btn-info flex-shrink-0 {{ ($recepcion->ordencompra_id ?? 0) ? '' : 'd-none' }}"
                   target="_blank" rel="noopener"
                   title="Consultar orden de compra en nueva pesta&ntilde;a (modo consulta, sin men&uacute;)">
                    <i class="fa fa-file-text-o"></i> Consultar OC
                </a>
                @endif
            </div>
        @else
            <div class="d-flex flex-wrap align-items-center" style="gap: 4px;">
                <button type="button" id="btn-consulta-oc-recepcion-modal" class="btn btn-sm btn-outline-primary flex-shrink-0" title="Buscar OC pendientes en AnitaERP">
                    <i class="fa fa-search"></i>
                </button>
                <input type="number"
                       id="numero_oc_buscar"
                       name="numero_oc_buscar"
                       class="form-control flex-grow-1"
                       placeholder="Número OC"
                       min="1"
                       value="{{ old('numero_oc_buscar', $numeroOcBuscar) }}"
                       autofocus
                       title="Enter o Tab para cargar la orden de compra"
                       style="min-width: 6rem; max-width: 10rem;">
                @if($puedeConsultarOc)
                <a href="{{ $urlConsultaOc($ordencompraIdForm) ?: '#' }}"
                   id="btn-consultar-oc-recepcion"
                   class="btn btn-sm btn-info flex-shrink-0 {{ $ordencompraIdForm ? '' : 'd-none' }}"
                   target="_blank" rel="noopener"
                   title="Consultar orden de compra en nueva pesta&ntilde;a (modo consulta, sin men&uacute;)">
                    <i class="fa fa-file-text-o"></i> Consultar OC
                </a>
                @endif
                @if($recepcion)
                <button type="button" id="btn-cambiar-oc-recepcion" class="btn btn-sm btn-warning flex-shrink-0" title="Buscar y cambiar la OC vinculada">
                    <i class="fa fa-exchange"></i> Cambiar OC
                </button>
                @endif
            </div>
            <input type="hidden" name="ordencompra_id" id="ordencompra_id" value="{{ old('ordencompra_id', $recepcion->ordencompra_id ?? '') }}">
        @endif
    </div>
    <label class="col-lg-2 col-form-label text-right">Proveedor</label>
    <div class="col-lg-4">
        <input type="text" id="proveedor_nombre" name="proveedor_nombre" class="form-control" readonly
            value="{{ old('proveedor_nombre', $proveedorNombreForm ?: optional(optional($recepcion)->proveedores)->nombre ?? '') }}">
    </div>
</div>

@include('stock.recepcion_proveedor.partials.aviso_descuento_oc', ['descuentoOc' => $descuentoOcCabecera])

@php
    // Devolución: siempre fecha del día (no heredar la de la recepción origen / período cerrado).
    $fechaFormDefault = ($modoDevolucion ?? false)
        ? date('Y-m-d')
        : (optional($recepcion->fecha ?? null)->format('Y-m-d') ?? date('Y-m-d'));
@endphp
<div class="form-group row">
    <label class="col-lg-2 col-form-label text-right">Fecha</label>
    <div class="col-lg-3">
        <input type="date" id="fecha" name="fecha" class="form-control" required
            max="{{ date('Y-m-d') }}"
            value="{{ old('fecha', $fechaFormDefault) }}"
            @if($soloLectura || ($modoDevolucion ?? false)) readonly @endif>
        @if($modoDevolucion ?? false)
            <small class="form-text text-muted">La devolución se registra con la fecha de hoy.</small>
        @endif
    </div>
    <label class="col-lg-2 col-form-label text-right">
        Nº factura remito
        <i class="fa fa-question-circle text-muted tooltipsC ml-1"
           title="Referencia del comprobante del proveedor. Solo número (ej. 265): se toma como factura FAC con la letra del proveedor. Con guión: sucursal-número (ej. 1-265). Con tipo: FAC 1-265, REM 999, ND, NC. Opcional."></i>
    </label>
    <div class="col-lg-4">
        <input type="text" name="numerofactura" class="form-control" maxlength="50"
            value="{{ old('numerofactura', $recepcion->numerofactura ?? '') }}"
            placeholder="265 · 1-265 · FAC 1-265"
            @if($soloLectura) readonly @endif>
        @if(!$soloLectura)
        <small class="form-text text-muted">
            Formas válidas: <strong>solo número</strong> (<code>265</code> → FAC con letra del proveedor);
            <strong>sucursal-número</strong> (<code>1-265</code>);
            <strong>tipo y número</strong> (<code>FAC 1-265</code>, <code>REM 999</code>, <code>ND</code>, <code>NC</code>).
        </small>
        @endif
    </div>
</div>

<div class="form-group row">
    <label class="col-lg-2 col-form-label text-right">Observación</label>
    <div class="col-lg-9">
        <input type="text" name="observacion" class="form-control" maxlength="255"
            value="{{ old('observacion', $recepcion->observacion ?? '') }}"
            @if($soloLectura) readonly @endif>
    </div>
</div>

<div class="form-group row mb-2 align-items-center">
    @include('stock.partials.campo_consulta_deposito', [
        'prefix' => 'entrada',
        'layout' => 'form_row',
        'wrap_row' => false,
        'label' => 'Depósito general entrada',
        'ayuda_tooltip' => 'Opcional si cada artículo tiene depósito de entrega en el maestro. Obligatorio cuando hay líneas a recibir, rechazar o cerrar sin depósito propio. En depósitos tipo Fórmulas la cantidad se convierte con el coeficiente del artículo.',
        'inputId' => 'recepcion_deposito_id',
        'inputName' => 'deposito_id',
        'depositoId' => $depositoCabeceraId,
        'codigo' => $depositoCabeceraCodigo,
        'descripcion' => $depositoCabeceraNombre,
        'required' => false,
        'solo_lectura' => $soloLectura,
        'col_label' => 'col-lg-2 col-form-label text-right',
        'col_input' => 'col-lg-5',
    ])
    <label class="col-lg-2 col-form-label text-right pl-0 pr-1" for="empresa_nombre_oc">Empresa</label>
    <div class="col-lg-3">
        <input type="text" id="empresa_nombre_oc" name="empresa_nombre_oc"
            class="form-control form-control-sm text-truncate recepcion-empresa-oc"
            readonly
            title="{{ $empresaNombreOc }}"
            value="{{ $empresaNombreOc }}"
            placeholder="De la OC">
    </div>
</div>

@php
    $puedeIntercompanyRecepcion = \App\Support\Stock\RecepcionProveedorIntercompanySupport::puedeUsar();
    $empresaDepositoCabeceraInt = $depositoCabeceraEmpresaId ? (int) $depositoCabeceraEmpresaId : 0;
    $empresaOcInt = ($empresaIdOc !== '' && $empresaIdOc !== null) ? (int) $empresaIdOc : 0;
    $ingresoIntercompany = \App\Support\Stock\RecepcionProveedorIntercompanySupport::esIngresoIntercompany($empresaDepositoCabeceraInt, $empresaOcInt);
    $empresaDepositoNombre = optional(optional(optional($recepcion)->depositos)->empresas)->nombre ?? '';
@endphp

@if($puedeIntercompanyRecepcion && !$soloLectura)
<div class="form-group row mb-2" id="rp_panel_intercompany">
    <div class="col-lg-2"></div>
    <div class="col-lg-9">
        <button type="button" id="rp_btn_intercompany" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-building"></i> Ver dep&oacute;sitos de otras empresas
        </button>
        <input type="hidden" id="rp_modo_intercompany" value="0">
        <small class="text-muted d-block mt-1">
            Permite ingresar la mercader&iacute;a en un dep&oacute;sito de otra empresa. La recepci&oacute;n mantiene la empresa de la orden de compra.
        </small>
    </div>
</div>
@endif

<div class="form-group row mb-2 {{ $ingresoIntercompany ? '' : 'd-none' }}" id="rp_aviso_intercompany">
    <div class="col-lg-2"></div>
    <div class="col-lg-9">
        <div class="alert alert-warning py-2 mb-0">
            <i class="fa fa-building"></i>
            <strong>Ingreso intercompany:</strong>
            <span id="rp_aviso_intercompany_texto">
                el dep&oacute;sito de entrada pertenece a
                <strong id="rp_aviso_intercompany_empresa">{{ $empresaDepositoNombre ?: 'otra empresa' }}</strong>,
                distinta a la empresa de la recepci&oacute;n{{ $empresaNombreOc ? ' ('.$empresaNombreOc.')' : '' }}.
            </span>
        </div>
    </div>
</div>

@if(!$soloLectura)
<div class="form-group row">
    <label class="col-lg-2 col-form-label text-right">
        OCR (foto/PDF)
        <i class="fa fa-question-circle text-muted tooltipsC ml-1"
           title="Suba remito o factura (PDF/JPG/PNG). Detecta la OC si es legible; si no, cargue primero la OC y el OCR aplicará cantidades sobre los ítems ya cargados."></i>
    </label>
    <div class="col-lg-9">
        <input type="file" id="archivo_ocr" accept=".pdf,.jpg,.jpeg,.png" class="form-control-file"
               data-descartar-url="{{ route('descartar_ai_decision') }}">
        <input type="hidden" name="ai_decision_id" id="ai_decision_id" value="">
        <input type="hidden" name="ai_sugerencia_hash" id="ai_sugerencia_hash" value="">
        <input type="hidden" name="origen_carga" id="origen_carga" value="{{ old('origen_carga', optional($recepcion)->origen_carga ?? 'MANUAL') }}">
        <div id="ocr-ai-score" class="small text-muted mt-1 d-none"></div>
        <div id="ocr-debug-wrap" class="mt-2 d-none">
            <div class="card border-secondary">
                <div class="card-header py-1 px-2 bg-light">
                    <button type="button" class="btn btn-link btn-sm text-secondary p-0 text-left collapsed d-flex align-items-center"
                            id="ocr-debug-toggle"
                            data-toggle="collapse"
                            data-target="#ocr-debug-panel"
                            aria-expanded="false"
                            aria-controls="ocr-debug-panel">
                        <i class="fa fa-chevron-right mr-1 ocr-debug-chevron" aria-hidden="true"></i>
                        <span>Detalle OCR (JSON)</span>
                    </button>
                </div>
                <div class="collapse" id="ocr-debug-panel">
                    <div class="card-body p-2">
                        <pre id="ocr_debug_json" class="small bg-white border rounded p-2 mb-0" style="max-height: 22rem; overflow: auto; white-space: pre-wrap; font-size: 0.75rem;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<hr class="my-3">
<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Ítems</h5>
    @if(!$soloLectura && !($modoDevolucion ?? false) && $puedeAgregarArticuloExtra)
    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-agregar-extra">
        <i class="fa fa-plus"></i> Agregar artículo extra (no pedido en OC)
    </button>
    @endif
</div>
<div class="table-responsive">
    <table class="table table-sm table-bordered table-recepcion-items-compact" id="tabla-items-recepcion">
        <thead class="thead-light">
            <tr>
                <th class="col-num">#</th>
                <th class="col-art">Art.</th>
                <th class="col-desc">Descripci&oacute;n</th>
                <th class="col-color ms-col-color-talle" style="display:none;">Color</th>
                <th class="col-talle ms-col-color-talle" style="display:none;">Talle</th>
                <th class="col-qty text-right" title="{{ ($modoDevolucion ?? false) ? 'Cantidad recepcionada en el COM origen' : 'Cantidad pedida en la OC; si ya hubo recepciones confirmadas muestra ingresada y pendiente' }}">
                    {{ ($modoDevolucion ?? false) ? 'Recibida' : 'Cant. OC' }}
                </th>
                <th class="col-qty" title="{{ ($modoDevolucion ?? false) ? 'Cantidad a devolver (no puede superar lo recepcionado)' : 'Cantidad del remito en unidad de compra (ver columna Conversi&oacute;n)' }}">
                    {{ ($modoDevolucion ?? false) ? 'Cant. a devolver' : 'Cant. recibida' }}
                </th>
                @if(!($modoDevolucion ?? false))
                <th class="col-qty" title="Cantidad rechazada en la misma unidad de compra">Rechaz.</th>
                @endif
                <th class="col-conv text-right" title="Si el artículo tiene articulo_proveedor: UM compra del catálogo × coeficiente → cantidad en UM stock. Sin catálogo, coef = 1.">Conversi&oacute;n</th>
                <th class="col-precio text-right" title="Precio unitario seg&uacute;n remito/factura">Precio rec.</th>
                <th class="col-importe text-right" title="Cantidad recibida &times; precio recepci&oacute;n">Total l&iacute;nea</th>
                <th class="col-mon" title="Moneda / cotizaci&oacute;n">Mon./Cot.</th>
                <th class="col-dep" title="Dep&oacute;sito">Dep.</th>
                @if(!($modoDevolucion ?? false))
                <th class="col-acc" title="Pendiente o cierre OC (opcional si la l&iacute;nea queda sin cantidad)">OC</th>
                @endif
            </tr>
        </thead>
        <tbody id="tbody-items-recepcion">
        </tbody>
    </table>
</div>
<div class="d-flex justify-content-end align-items-center mt-2 mb-3 flex-wrap" id="recepcion-total-recepcion-wrap">
    <div id="recepcion-impuesto-interno-wrap" class="mr-4 text-right" style="display:none;">
        <label for="recepcion-impuesto-interno" class="text-muted mb-0 d-block">
            Impuesto interno
            <span class="text-danger">*</span>
        </label>
        <span class="text-muted small d-block">(total factura cigarrillos; prorrateo en &uacute;ltima compra)</span>
        @if($soloLectura)
            <strong class="h5 mb-0 text-primary d-block mt-1">
                {{ $impuestoInternoValor !== null && $impuestoInternoValor !== '' ? number_format((float) $impuestoInternoValor, 2, ',', '.') : '—' }}
            </strong>
        @else
            <input type="number" step="0.01" min="0" class="form-control form-control-sm text-right d-inline-block mt-1"
                   style="max-width: 9rem;"
                   name="impuesto_interno" id="recepcion-impuesto-interno"
                   value="{{ $impuestoInternoValor !== null && $impuestoInternoValor !== '' ? number_format((float) $impuestoInternoValor, 2, '.', '') : '' }}"
                   placeholder="0,00">
        @endif
    </div>
    <div class="text-right">
        <span class="text-muted mr-2">Total recepci&oacute;n</span>
        <span class="text-muted small d-block">(cantidad recibida &times; precio remito)</span>
        <strong id="recepcion-total-recepcion" class="h5 mb-0 text-primary d-block mt-1">—</strong>
    </div>
</div>

<style>
    #tabla-items-recepcion.table-recepcion-items-compact {
        font-size: 0.8125rem;
    }
    #empresa_nombre_oc.recepcion-empresa-oc {
        max-width: 100%;
        font-size: 0.8125rem;
    }
    #tabla-items-recepcion .col-num { width: 2.25rem; }
    #tabla-items-recepcion .col-art { width: 11rem; min-width: 9rem; }
    #tabla-items-recepcion .col-desc { min-width: 6rem; max-width: 14rem; }
    #tabla-items-recepcion .col-color,
    #tabla-items-recepcion .col-talle { width: 5.5rem; min-width: 4.5rem; }
    #tabla-items-recepcion .col-qty { width: 6.5rem; }
    #tabla-items-recepcion .col-conv { width: 5.75rem; min-width: 5.25rem; }
    #tabla-items-recepcion .col-precio { width: 6.25rem; min-width: 5.5rem; }
    #tabla-items-recepcion .col-importe { width: 7.75rem; min-width: 7.25rem; }
    #tabla-items-recepcion .col-mon { width: 5rem; }
    #tabla-items-recepcion .col-dep { width: 6.5rem; max-width: 8rem; }
    #tabla-items-recepcion .col-acc { width: 4.5rem; min-width: 4.25rem; }
    #tabla-items-recepcion .item-accion-oc-select { font-size: 0.7rem; padding: 0.1rem 0.15rem; height: calc(1.5em + 0.35rem); }
    #tabla-items-recepcion .input-qty-recepcion,
    #tabla-items-recepcion .input-precio-recepcion,
    #tabla-items-recepcion .input-importe-linea-recepcion {
        padding-left: 0.25rem;
        padding-right: 0.25rem;
    }
    #tabla-items-recepcion .input-qty-um-recepcion .form-control {
        min-width: 2.75rem;
        padding: 0.15rem 0.25rem;
    }
    #tabla-items-recepcion .input-qty-um-recepcion .input-group-text {
        font-size: 0.65rem;
        padding: 0 0.2rem;
        line-height: 1.1;
        max-width: 2.5rem;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    #tabla-items-recepcion .input-precio-recepcion {
        width: 100%;
        min-width: 4.5rem;
        max-width: 6rem;
    }
    #tabla-items-recepcion .input-importe-linea-recepcion {
        min-width: 3.75rem;
    }
    #tabla-items-recepcion .input-importe-grupo-recepcion {
        flex-wrap: nowrap;
    }
    #tabla-items-recepcion .input-importe-grupo-recepcion .btn-linea-precio-modal {
        padding: 0.15rem 0.4rem;
        line-height: 1.1;
    }
    #tabla-items-recepcion td .celda-importe-linea {
        overflow: visible;
    }
    #tabla-items-recepcion tr.item-recepcion-comentario-precio td > div {
        background: #fff3cd;
        border-left: 3px solid #ffc107;
        padding: 0.25rem 0.5rem;
        border-radius: 0.15rem;
    }
    #tabla-items-recepcion .celda-conversion-recepcion {
        font-size: 0.72rem;
        line-height: 1.25;
    }
    #tabla-items-recepcion .celda-conversion-recepcion .conv-stock {
        white-space: nowrap;
    }
    #tabla-items-recepcion .celda-articulo-recepcion .codigoarticulo {
        width: 6.5rem;
        max-width: 12vw;
    }
    #tabla-items-recepcion .descripcionarticulo {
        font-size: 0.78rem;
    }
    #tabla-items-recepcion .celda-moneda-cot-recepcion select,
    #tabla-items-recepcion .celda-moneda-cot-recepcion .item-cotizacion {
        width: 100%;
        min-width: 0;
        padding: 0.1rem 0.2rem;
        font-size: 0.78rem;
    }
    #tabla-items-recepcion .celda-moneda-cot-recepcion .item-cotizacion {
        margin-top: 0.15rem;
    }
    #tabla-items-recepcion .item-deposito-texto {
        display: block;
        font-size: 0.72rem;
        line-height: 1.2;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 7.5rem;
    }
    #tabla-items-recepcion tr.item-recepcion-comentario-precio td {
        border-top: none;
        padding-top: 0;
        padding-bottom: 0.35rem;
    }
    #tabla-items-recepcion tr.item-recepcion-comentario-precio .item-comentario-precio {
        max-width: 28rem;
        font-size: 0.8rem;
    }
    #tabla-items-recepcion tr.item-recepcion-motivo-rechazo td {
        border-top: none;
        padding-top: 0;
        padding-bottom: 0.35rem;
    }
    #tabla-items-recepcion tr.item-recepcion-motivo-rechazo .item-motivo-rechazo {
        max-width: 28rem;
        font-size: 0.8rem;
    }
    #tabla-items-recepcion tr.item-recepcion-comentario-diferencia td {
        border-top: none;
        padding-top: 0;
        padding-bottom: 0.35rem;
    }
    #tabla-items-recepcion tr.item-recepcion-comentario-diferencia .item-comentario-diferencia {
        max-width: 28rem;
        font-size: 0.8rem;
    }
</style>
@include('stock.recepcion_proveedor.partials.banner_confirmando_styles')

@if($recepcion && $recepcion->fl_precio_pendiente_aprobacion)
<div class="alert alert-info">
    <strong>Precio pendiente de aprobaci&oacute;n en compras:</strong>
    carg&oacute; precios de factura/remito distintos a la OC. Compras debe actualizar la orden de compra antes de confirmar esta recepci&oacute;n.
    @if($recepcion->comentario_precio)
        <br><span class="small">{!! nl2br(e($recepcion->comentario_precio)) !!}</span>
    @endif
</div>
@endif

@if($recepcion && $recepcion->resumen_rechazos)
<div class="alert alert-danger">
    <strong>Líneas rechazadas:</strong><br>{!! nl2br(e($recepcion->resumen_rechazos)) !!}
</div>
@endif

@if($recepcion && $recepcion->resumen_diferencias)
<div class="alert alert-warning">
    <strong>Resumen de diferencias:</strong><br>{!! nl2br(e($recepcion->resumen_diferencias)) !!}
</div>
@elseif($recepcion && $recepcion->comentario_precio)
<div class="alert alert-warning">
    <strong>Diferencia de precio:</strong> {{ $recepcion->comentario_precio }}
</div>
@endif

</div>{{-- /rp-solapa-principal --}}

@if($recepcion)
<div id="rp-solapa-historia-estados" class="rp-solapa" style="display:none;">
    @include('stock.recepcion_proveedor.partials.solapa_historia_estados', ['recepcion' => $recepcion])
</div>
@if(! empty($asientoPreview['activo']))
<div id="rp-solapa-asiento-contable" class="rp-solapa" style="display:none;">
    @include('stock.recepcion_proveedor.partials.solapa_asiento_contable', [
        'recepcion' => $recepcion,
        'asientoPreview' => $asientoPreview ?? ['activo' => false],
    ])
</div>
@endif
<div id="rp-solapa-archivos" class="rp-solapa" style="display:none;">
    @include('stock.recepcion_proveedor.partials.solapa_archivos', [
        'recepcion' => $recepcion,
        'soloLectura' => $soloLectura,
    ])
</div>
@if (!empty($mostrar_solapa_ingresos))
<div id="rp-solapa-ingresos" class="rp-solapa" style="display:none;">
    @if (!empty($mostrar_solapa_validacion))
        <div class="alert alert-info py-2">
            La última carga de respuestas de la consulta de ingresos está en la solapa
            <button type="button" class="btn btn-link btn-sm p-0 align-baseline js-rp-abrir-validacion">Validación</button>.
            @if (! empty($urlValidacionAbono))
                También puede
                <a href="{{ $urlValidacionAbono }}">abrir el formulario completo</a>.
            @endif
        </div>
    @endif
    @include('seguridad.ingreso_proveedor.partials.solapa_vinculada', [
        'tickets' => $tickets_ingreso ?? collect(),
        'url_nuevo_ticket_ingreso' => $url_nuevo_ticket_ingreso ?? null,
        'ingresoContexto' => [
            'empresa_id' => optional($recepcion->ordencompras)->empresa_id ?? $recepcion->empresa_id ?? null,
            'proveedor_id' => optional($recepcion->ordencompras)->proveedor_id ?? optional($recepcion->proveedores)->id ?? null,
            'ordencompra_id' => $recepcion->ordencompra_id ?? null,
        ],
    ])
</div>
@endif
@if (!empty($mostrar_solapa_validacion))
<div id="rp-solapa-validacion" class="rp-solapa" style="display:none;">
    @include('stock.recepcion_proveedor.partials.solapa_validacion_abono')
</div>
@endif
@endif

<script>
    window.recepcionProveedorItemsInicial = @json($items);
    window.recepcionProveedorModoDevolucion = @json($modoDevolucion ?? false);
    window.recepcionProveedorSoloLectura = @json($soloLectura);
    window.recepcionProveedorId = @json($recepcion->id ?? null);
    window.recepcionProveedorDepositoCabeceraId = @json((int) $depositoCabeceraId ?: 0);
    window.recepcionProveedorDepositoCabeceraEmpresaId = @json($depositoCabeceraEmpresaId ? (int) $depositoCabeceraEmpresaId : null);
    window.recepcionProveedorDepositoCabeceraTipo = @json($depositoCabeceraTipo !== '' ? (string) $depositoCabeceraTipo : '');
    window.recepcionProveedorEmpresaIdInicial = @json($empresaIdOc !== '' && $empresaIdOc !== null ? (int) $empresaIdOc : null);
    window.recepcionProveedorOrdencompraIdInicial = @json($ordencompraIdForm);
    window.recepcionProveedorNumeroOcInicial = @json($numeroOcBuscar !== '' && $numeroOcBuscar !== null ? (int) $numeroOcBuscar : null);
    window.recepcionProveedorDescuentoOcInicial = @json($descuentoOcCabecera);
    window.recepcionProveedorTipoarticuloCigarrilloId = @json($tipoArticuloCigarrilloId);
    window.recepcionProveedorMonedas = @json($moneda_query->map(static fn ($m) => ['id' => (int) $m->id, 'abreviatura' => (string) $m->abreviatura])->values());
    @php
        $rpColores = \App\Models\Stock\Color::query()->orderBy('nombre')->get(['id', 'nombre']);
        $rpTalles = \App\Models\Stock\Talle::query()->orderBy('nombre')->get(['id', 'nombre']);
    @endphp
    window.msColoresOpciones = @json($rpColores->map(fn ($c) => ['id' => (int) $c->id, 'nombre' => $c->nombre])->values());
    window.msTallesOpciones = @json($rpTalles->map(fn ($t) => ['id' => (int) $t->id, 'nombre' => $t->nombre])->values());
    window.recepcionProveedorPuedeConsultarOc = @json($puedeConsultarOc);
    window.recepcionProveedorPuedeModificarPrecio = @json($puedeModificarPrecio);
    window.recepcionProveedorPuedeAgregarArticuloExtra = @json($puedeAgregarArticuloExtra);
    window.recepcionProveedorPuedeIntercompany = @json($puedeIntercompanyRecepcion);
    window.recepcionProveedorEmpresaNombreOc = @json($empresaNombreOc);
    window.recepcionProveedorDepositoCabeceraEmpresaNombre = @json($empresaDepositoNombre);
    window.recepcionProveedorModalCatalogoHabilitado = @json(
        config('recepcion_proveedor.modal_articulo_proveedor_habilitado') && ! $soloLectura
    );
    window.recepcionProveedorPreviewCatalogoUrl = @json(
        config('recepcion_proveedor.modal_articulo_proveedor_habilitado') && ! $soloLectura
            ? route('recepcion_proveedor_preview_articulo_proveedor')
            : null
    );
</script>
@if (config('recepcion_proveedor.modal_articulo_proveedor_habilitado') && ! $soloLectura)
@include('stock.recepcion_proveedor.partials.modal_articulo_proveedor')
@endif
@include('stock.recepcion_proveedor.partials.modal_linea_precio')
@if(!($soloLectura ?? false) && !($modoDevolucion ?? false) && (! $recepcion || ($recepcion->estado ?? '') === 'BORRADOR'))
@include('stock.recepcion_proveedor.partials.modal_confirmar_diferencias')
@endif
@include('includes.stock.modalconsultaarticulo')
@include('includes.compras.modal_elegir_articulo_proveedor')
