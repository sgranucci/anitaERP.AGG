@php
    $soloLectura = !($modoEdicion ?? false) || (($recepcion->estado ?? 'BORRADOR') !== 'BORRADOR');
    $empresaIdOc = old('empresa_id', optional(optional($recepcion)->ordencompras)->empresa_id ?? optional($recepcion)->empresa_id ?? '');
    $depositoCabeceraId = old('deposito_id', optional($recepcion)->deposito_id ?? '');
    $depositoCabecera = optional(optional($recepcion)->depositos);
    $items = old('items');
    if ($items === null && $recepcion) {
        $items = $recepcion->recepcion_proveedor_articulos->map(function ($l) {
            return array_merge($l->toArray(), [
                'sku' => optional($l->articulos)->sku ?? '',
                'descripcion' => optional($l->articulos)->nombre ?? '',
                'deposito_nombre' => optional($l->depositos)->nombre ?? '',
                'depositoentrega_id' => optional($l->articulos)->depositoentrega_id ?? null,
                'coeficiente_articulo' => (float) (optional($l->articulos)->coeficienteconversion ?? 1) ?: 1,
                'coeficiente_proveedor' => (float) ($l->coeficienteconversion ?? 1),
                'es_deposito_formula' => optional($l->depositos)->tipodeposito === 'Formulas',
                'articulo_stock_id' => $l->articulo_stock_id,
                'articulo_stock_sku' => optional($l->articulo_stock)->sku ?? '',
                'skualternativo' => optional($l->articulos)->skualternativo ?? '',
            ]);
        })->values()->all();
    }
    $items = $items ?? [];
@endphp

<input type="hidden" id="empresa_id" name="empresa_id" value="{{ $empresaIdOc }}">

<div class="form-group row">
    <label class="col-lg-2 col-form-label text-right">Nº OC</label>
    <div class="col-lg-3">
        @if($soloLectura)
            <input type="text" class="form-control" readonly value="{{ optional($recepcion->ordencompras)->numeroordencompra ?? '' }}">
            <input type="hidden" name="ordencompra_id" value="{{ $recepcion->ordencompra_id ?? '' }}">
        @else
            <div class="input-group">
                <input type="number" id="numero_oc_buscar" class="form-control" placeholder="Número OC" min="1">
                <div class="input-group-append">
                    <button type="button" class="btn btn-info" id="btn-cargar-oc"><i class="fa fa-search"></i> Cargar OC</button>
                </div>
            </div>
            <input type="hidden" name="ordencompra_id" id="ordencompra_id" value="{{ old('ordencompra_id', $recepcion->ordencompra_id ?? '') }}">
        @endif
    </div>
    <label class="col-lg-2 col-form-label text-right">Proveedor</label>
    <div class="col-lg-4">
        <input type="text" id="proveedor_nombre" class="form-control" readonly
            value="{{ old('proveedor_nombre', optional(optional($recepcion)->proveedores)->nombre ?? '') }}">
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
    'codigo' => $depositoCabecera->codigo ?? '',
    'descripcion' => $depositoCabecera->nombre ?? '',
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
        <small class="text-muted">Preparado para carga automatizada por OCR (requiere permiso y variable de entorno).</small>
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
    <table class="table table-sm table-bordered" id="tabla-items-recepcion">
        <thead class="thead-light">
            <tr>
                <th>#</th>
                <th>Artículo</th>
                <th>Cant. OC</th>
                <th>Cant. rec.</th>
                <th>Coef.</th>
                <th>Cant. stock</th>
                <th>Precio OC</th>
                <th>Precio rec.</th>
                <th>Lista prov.</th>
                <th>Depósito</th>
                <th>Coment. precio</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tbody-items-recepcion">
        </tbody>
    </table>
</div>

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
</script>
