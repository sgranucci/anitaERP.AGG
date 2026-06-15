@php
    use App\Models\Configuracion\Moneda;
    use App\Support\Stock\RecepcionProveedorFormItemsSupport;
    use App\Support\Stock\RecepcionProveedorParteUnicaSupport;

    $moneda_query = $moneda_query ?? Moneda::query()->orderBy('nombre')->get();

    $soloLectura = !($modoEdicion ?? false) || (($recepcion->estado ?? 'BORRADOR') !== 'BORRADOR');
    $empresaIdOc = old('empresa_id', optional(optional($recepcion)->ordencompras)->empresa_id ?? optional($recepcion)->empresa_id ?? '');
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
    }
    $items = $items ?? [];
@endphp

<input type="hidden" id="empresa_id" name="empresa_id" value="{{ $empresaIdOc }}">
<input type="hidden" id="proveedor_id" name="proveedor_id" value="{{ $proveedorIdForm }}">

<div class="form-group row">
    <label class="col-lg-2 col-form-label text-right">Nº OC</label>
    <div class="col-lg-3">
        @if($soloLectura)
            <input type="text" class="form-control" readonly value="{{ optional($recepcion->ordencompras)->numeroordencompra ?? '' }}">
            <input type="hidden" name="ordencompra_id" value="{{ $recepcion->ordencompra_id ?? '' }}">
        @else
            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                <button type="button" id="btn-consulta-oc-recepcion-modal" class="btn btn-sm btn-outline-primary flex-shrink-0" title="Buscar OC pendientes en AnitaERP">
                    <i class="fa fa-search"></i>
                </button>
                <input type="number"
                       id="numero_oc_buscar"
                       name="numero_oc_buscar"
                       class="form-control"
                       placeholder="Número OC"
                       min="1"
                       value="{{ old('numero_oc_buscar', $numeroOcBuscar) }}"
                       autofocus
                       title="Enter o Tab para cargar la orden de compra"
                       style="min-width: 0;">
            </div>
            <small class="form-text text-muted">
                Enter o Tab para cargar. Si el remito no trae OC legible, ingrese el número o búsquela con la lupa, cargue la OC y luego suba el OCR para aplicar cantidades.
            </small>
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
    <label class="col-lg-2 col-form-label text-right">Nº factura remito</label>
    <div class="col-lg-4">
        <input type="text" name="numerofactura" class="form-control" maxlength="50"
            value="{{ old('numerofactura', $recepcion->numerofactura ?? '') }}"
            @if($soloLectura) readonly @endif>
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
<div class="form-group row">
    <div class="col-lg-2"></div>
    <div class="col-lg-9">
        <small class="form-text text-muted">
            Opcional. Si no indica depósito general, cada artículo ingresa al depósito configurado en el maestro de artículos.
            En depósitos tipo Fórmulas (ej. cocina) la cantidad se convierte con el coeficiente del artículo.
        </small>
    </div>
</div>

@if(!$soloLectura)
<div class="form-group row">
    <label class="col-lg-2 col-form-label text-right">OCR (foto/PDF)</label>
    <div class="col-lg-9">
        <input type="file" id="archivo_ocr" accept=".pdf,.jpg,.jpeg,.png" class="form-control-file">
        <small class="text-muted">Suba remito o factura (PDF/JPG/PNG). Detecta la OC si es legible; si no, cargue primero la OC y el OCR aplicará cantidades sobre los ítems ya cargados.</small>
    </div>
</div>
@endif

<hr>
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
                <th class="col-qty text-right" title="Cantidad OC">C.OC</th>
                <th class="col-qty" title="Cantidad recibida">C.rec.</th>
                <th class="col-coef text-right" title="Coeficiente">Coef.</th>
                <th class="col-stk text-right" title="Cantidad stock">Stk.</th>
                <th class="col-precio text-right" title="Precio OC">P.OC</th>
                <th class="col-precio" title="Precio recibido">P.rec.</th>
                <th class="col-mon" title="Moneda / cotizaci&oacute;n">Mon./Cot.</th>
                <th class="col-dep" title="Dep&oacute;sito">Dep.</th>
                <th class="col-acc"></th>
            </tr>
        </thead>
        <tbody id="tbody-items-recepcion">
        </tbody>
    </table>
</div>

<style>
    #tabla-items-recepcion.table-recepcion-items-compact {
        font-size: 0.8125rem;
    }
    #tabla-items-recepcion .col-num { width: 2.25rem; }
    #tabla-items-recepcion .col-art { width: 11rem; min-width: 9rem; }
    #tabla-items-recepcion .col-desc { min-width: 8rem; }
    #tabla-items-recepcion .col-qty { width: 4.75rem; }
    #tabla-items-recepcion .col-coef { width: 3.5rem; }
    #tabla-items-recepcion .col-stk { width: 4.5rem; }
    #tabla-items-recepcion .col-precio { width: 5.25rem; }
    #tabla-items-recepcion .col-mon { width: 5rem; }
    #tabla-items-recepcion .col-dep { width: 6.5rem; max-width: 8rem; }
    #tabla-items-recepcion .col-acc { width: 2rem; }
    #tabla-items-recepcion .input-coef-recepcion,
    #tabla-items-recepcion .input-qty-recepcion,
    #tabla-items-recepcion .input-precio-recepcion {
        padding-left: 0.25rem;
        padding-right: 0.25rem;
    }
    #tabla-items-recepcion .input-coef-recepcion {
        width: 3.25rem;
        min-width: 3.25rem;
        max-width: 3.25rem;
    }
    #tabla-items-recepcion .input-qty-recepcion {
        width: 4.5rem;
        min-width: 4.5rem;
        max-width: 4.5rem;
    }
    #tabla-items-recepcion .input-precio-recepcion {
        width: 5rem;
        min-width: 5rem;
        max-width: 5rem;
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
</style>

@if($recepcion && $recepcion->resumen_diferencias)
<div class="alert alert-warning">
    <strong>Resumen de diferencias:</strong><br>{!! nl2br(e($recepcion->resumen_diferencias)) !!}
</div>
@elseif($recepcion && $recepcion->comentario_precio)
<div class="alert alert-warning">
    <strong>Diferencia de precio:</strong> {{ $recepcion->comentario_precio }}
</div>
@endif

<script>
    window.recepcionProveedorItemsInicial = @json($items);
    window.recepcionProveedorSoloLectura = @json($soloLectura);
    window.recepcionProveedorId = @json($recepcion->id ?? null);
    window.recepcionProveedorDepositoCabeceraId = @json((int) $depositoCabeceraId ?: 0);
    window.recepcionProveedorOrdencompraIdInicial = @json($ordencompraIdForm);
    window.recepcionProveedorNumeroOcInicial = @json($numeroOcBuscar !== '' && $numeroOcBuscar !== null ? (int) $numeroOcBuscar : null);
    window.recepcionProveedorMonedas = @json($moneda_query->map(static fn ($m) => ['id' => (int) $m->id, 'abreviatura' => (string) $m->abreviatura])->values());
</script>
@include('includes.stock.modalconsultaarticulo')
