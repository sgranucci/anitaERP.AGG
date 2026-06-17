@php
    use App\Models\Configuracion\Moneda;
    use App\Support\Stock\RecepcionProveedorFormItemsSupport;
    use App\Support\Stock\RecepcionProveedorParteUnicaSupport;

    $moneda_query = $moneda_query ?? Moneda::query()->orderBy('nombre')->get();

    $soloLectura = !($modoEdicion ?? false) || (($recepcion->estado ?? 'BORRADOR') !== 'BORRADOR');
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
            ]);
        })->values()->all();
        $items = RecepcionProveedorFormItemsSupport::enriquecerItemsParaVista(
            $items,
            $depositoCabeceraIdInt,
            $recepcion->proveedor_id ?? ($proveedorIdForm ?? null),
            $empresaIdOc ? (int) $empresaIdOc : null
        );
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
            <input type="text" class="form-control" readonly value="{{ optional($recepcion->ordencompras)->numeroordencompra ?? '' }}">
            <input type="hidden" name="ordencompra_id" value="{{ $recepcion->ordencompra_id ?? '' }}">
            @if($recepcion && $recepcion->ordencompra_id && (can('editar-ordencompra', false) || can('listar-ordencompra', false)))
            <a href="{{ $urlConsultaOc((int) $recepcion->ordencompra_id) }}"
               class="btn btn-sm btn-outline-primary mt-1" target="_blank" rel="noopener" title="Consultar orden de compra (sin menú)">
                <i class="fa fa-external-link"></i> Ver OC
            </a>
            @endif
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
                @if($recepcion)
                <button type="button" id="btn-cambiar-oc-recepcion" class="btn btn-sm btn-warning flex-shrink-0" title="Buscar y cambiar la OC vinculada">
                    <i class="fa fa-exchange"></i> Cambiar OC
                </button>
                @endif
                @if($recepcion && $recepcion->ordencompra_id && (can('editar-ordencompra', false) || can('listar-ordencompra', false)))
                <a href="{{ $urlConsultaOc((int) $recepcion->ordencompra_id) }}"
                   class="btn btn-sm btn-outline-primary flex-shrink-0" target="_blank" rel="noopener" title="Consultar orden de compra (sin menú)">
                    <i class="fa fa-external-link"></i> Ver OC
                </a>
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

<div class="form-group row">
    <label class="col-lg-2 col-form-label text-right">Fecha</label>
    <div class="col-lg-3">
        <input type="date" name="fecha" class="form-control" required
            value="{{ old('fecha', optional($recepcion->fecha ?? null)->format('Y-m-d') ?? date('Y-m-d')) }}"
            @if($soloLectura) readonly @endif>
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

@include('stock.partials.campo_consulta_deposito', [
    'prefix' => 'entrada',
    'layout' => 'form_row',
    'label' => 'Depósito general entrada',
    'ayuda_tooltip' => 'Opcional. Si no indica depósito general, cada artículo ingresa al depósito del maestro. En depósitos tipo Fórmulas la cantidad se convierte con el coeficiente del artículo.',
    'inputId' => 'recepcion_deposito_id',
    'inputName' => 'deposito_id',
    'depositoId' => $depositoCabeceraId,
    'codigo' => $depositoCabeceraCodigo,
    'descripcion' => $depositoCabeceraNombre,
    'required' => false,
    'solo_lectura' => $soloLectura,
    'col_label' => 'col-lg-2 col-form-label text-right',
    'col_input' => 'col-lg-4',
])

@if(!$soloLectura)
<div class="form-group row">
    <label class="col-lg-2 col-form-label text-right">
        OCR (foto/PDF)
        <i class="fa fa-question-circle text-muted tooltipsC ml-1"
           title="Suba remito o factura (PDF/JPG/PNG). Detecta la OC si es legible; si no, cargue primero la OC y el OCR aplicará cantidades sobre los ítems ya cargados."></i>
    </label>
    <div class="col-lg-9">
        <input type="file" id="archivo_ocr" accept=".pdf,.jpg,.jpeg,.png" class="form-control-file">
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
    @if(!$soloLectura && !($modoDevolucion ?? false))
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
                <th class="col-qty text-right" title="Cantidad pedida en la OC (cajas, packs, bultos)">Cant. OC</th>
                <th class="col-qty" title="Cantidad del remito en unidad de compra (ver columna Conversi&oacute;n)">Cant. recibida</th>
                <th class="col-qty" title="Cantidad rechazada en la misma unidad de compra">Rechaz.</th>
                <th class="col-conv text-right" title="Unidad de compra del remito, coeficiente y cantidad equivalente en unidad de stock ERP">Conversi&oacute;n</th>
                <th class="col-precio text-right" title="Precio unitario seg&uacute;n remito/factura">Precio rec.</th>
                <th class="col-importe text-right" title="Cantidad recibida &times; precio recepci&oacute;n">Total l&iacute;nea</th>
                <th class="col-mon" title="Moneda / cotizaci&oacute;n">Mon./Cot.</th>
                <th class="col-dep" title="Dep&oacute;sito">Dep.</th>
                <th class="col-acc"></th>
            </tr>
        </thead>
        <tbody id="tbody-items-recepcion">
        </tbody>
    </table>
</div>
<div class="d-flex justify-content-end align-items-center mt-2 mb-3" id="recepcion-total-recepcion-wrap">
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
    #tabla-items-recepcion .col-num { width: 2.25rem; }
    #tabla-items-recepcion .col-art { width: 11rem; min-width: 9rem; }
    #tabla-items-recepcion .col-desc { min-width: 8rem; }
    #tabla-items-recepcion .col-qty { width: 5.25rem; }
    #tabla-items-recepcion .col-conv { width: 5.75rem; min-width: 5.25rem; }
    #tabla-items-recepcion .col-precio { width: 6.25rem; min-width: 5.5rem; }
    #tabla-items-recepcion .col-importe { width: 7.75rem; min-width: 7.25rem; }
    #tabla-items-recepcion .col-mon { width: 5rem; }
    #tabla-items-recepcion .col-dep { width: 6.5rem; max-width: 8rem; }
    #tabla-items-recepcion .col-acc { width: 2rem; }
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
</style>

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
@endif

<script>
    window.recepcionProveedorItemsInicial = @json($items);
    window.recepcionProveedorSoloLectura = @json($soloLectura);
    window.recepcionProveedorId = @json($recepcion->id ?? null);
    window.recepcionProveedorDepositoCabeceraId = @json((int) $depositoCabeceraId ?: 0);
    window.recepcionProveedorDepositoCabeceraEmpresaId = @json($depositoCabeceraEmpresaId ? (int) $depositoCabeceraEmpresaId : null);
    window.recepcionProveedorDepositoCabeceraTipo = @json($depositoCabeceraTipo !== '' ? (string) $depositoCabeceraTipo : '');
    window.recepcionProveedorEmpresaIdInicial = @json($empresaIdOc !== '' && $empresaIdOc !== null ? (int) $empresaIdOc : null);
    window.recepcionProveedorOrdencompraIdInicial = @json($ordencompraIdForm);
    window.recepcionProveedorNumeroOcInicial = @json($numeroOcBuscar !== '' && $numeroOcBuscar !== null ? (int) $numeroOcBuscar : null);
    window.recepcionProveedorMonedas = @json($moneda_query->map(static fn ($m) => ['id' => (int) $m->id, 'abreviatura' => (string) $m->abreviatura])->values());
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
@include('includes.stock.modalconsultaarticulo')
