@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'solo_lectura' => ! empty($data->id),
    'col_input' => 'col-lg-8',
])
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="255" required
               value="{{ old('nombre', $data->nombre ?? '') }}">
        <small class="form-text text-muted">Identificaci&oacute;n de la m&aacute;quina vending (ej. Kiosco piso 1).</small>
    </div>
</div>
<div class="form-group row">
    <label for="puntoventa_id" class="col-lg-3 col-form-label requerido">Punto de venta</label>
    <div class="col-lg-8">
        <select name="puntoventa_id" id="puntoventa_id" class="form-control" required>
            <option value="">Seleccione&hellip;</option>
            @foreach ($puntoventa_query as $pv)
                <option value="{{ $pv->id }}" {{ (int) old('puntoventa_id', $data->puntoventa_id ?? 0) === (int) $pv->id ? 'selected' : '' }}>
                    {{ $pv->codigo }} — {{ $pv->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Corresponde al c&oacute;digo de sucursal / punto de venta fiscal de la m&aacute;quina.</small>
    </div>
</div>
<div class="form-group row">
    <label for="ubicacion_id" class="col-lg-3 col-form-label requerido">Ubicaci&oacute;n gastronom&iacute;a</label>
    <div class="col-lg-8">
        <select name="ubicacion_id" id="ubicacion_id" class="form-control" required>
            <option value="">Seleccione&hellip;</option>
            @foreach ($ubicacion_query as $ubicacion)
                <option value="{{ $ubicacion->id }}" {{ (int) old('ubicacion_id', $data->ubicacion_id ?? 0) === (int) $ubicacion->id ? 'selected' : '' }}>
                    {{ $ubicacion->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Sector o sal&oacute;n de la empresa donde est&aacute; instalada la m&aacute;quina.</small>
    </div>
</div>
@php
    $depositoIdMov = old('deposito_id', $data->deposito_id ?? '');
    $depositoModelMov = $depositoModel ?? null;
@endphp
@include('stock.partials.campo_consulta_deposito', [
    'prefix' => 'maquinavending',
    'layout' => 'form_row',
    'inputName' => 'deposito_id',
    'inputId' => 'deposito_id',
    'depositoId' => $depositoIdMov,
    'codigo' => old('deposito_codigo', $depositoModelMov->codigo ?? ''),
    'descripcion' => old('deposito_descripcion', $depositoModelMov->nombre ?? ''),
    'col_label' => 'col-lg-3 col-form-label',
    'col_input' => 'col-lg-8',
    'label' => 'Depósito',
    'ayuda_tooltip' => 'Depósito de stock asociado a la máquina vending.',
])
@include('includes.stock.modalconsultadeposito')
<div class="form-group row">
    <label for="listaprecio_id" class="col-lg-3 col-form-label requerido">Lista de precios</label>
    <div class="col-lg-8">
        <select name="listaprecio_id" id="listaprecio_id" class="form-control" required>
            <option value="">Seleccione&hellip;</option>
            @foreach ($listaprecio_query ?? [] as $lp)
                <option value="{{ $lp->id }}" {{ (int) old('listaprecio_id', $data->listaprecio_id ?? config('precio.listaprecio_default_id', 2)) === (int) $lp->id ? 'selected' : '' }}>
                    {{ $lp->id }} — {{ $lp->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Precios de venta en la rendici&oacute;n vending (tabla <code>precio</code>). Si un rulo tiene precio fijo, prevalece sobre la lista.</small>
    </div>
</div>
<div class="form-group row">
    <label for="codigo_arca" class="col-lg-3 col-form-label">C&oacute;digo ARCA</label>
    <div class="col-lg-8">
        <input type="text" name="codigo_arca" id="codigo_arca" class="form-control" maxlength="20"
               value="{{ old('codigo_arca', $data->codigo_arca ?? '') }}">
    </div>
</div>
@if (! empty($data->codigo_anita))
<div class="form-group row">
    <label class="col-lg-3 col-form-label">C&oacute;d. Anita</label>
    <div class="col-lg-8">
        <input type="text" class="form-control" value="{{ $data->codigo_anita }}" readonly>
        <small class="form-text text-muted">Identificador legacy en Anita (<code>maqvm_codigo</code>). Solo lectura; se actualiza al sincronizar.</small>
    </div>
</div>
@endif
<div class="form-group row">
    <label for="numero_serie" class="col-lg-3 col-form-label">N&uacute;mero de serie</label>
    <div class="col-lg-8">
        <input type="text" name="numero_serie" id="numero_serie" class="form-control" maxlength="50"
               value="{{ old('numero_serie', $data->numero_serie ?? '') }}">
    </div>
</div>

<hr>
<h5 class="mb-3">Art&iacute;culos cargados por rulo</h5>
<p class="text-muted small">Indique el n&uacute;mero de rulo/ubicaci&oacute;n interna, el art&iacute;culo cargado y, opcionalmente, un <strong>precio fijo</strong> (si queda vac&iacute;o se usa la lista de precios de la m&aacute;quina). La consulta modal lista solo insumos gastronom&iacute;a; puede ingresar cualquier SKU v&aacute;lido del ERP directamente en el campo c&oacute;digo.</p>

<table class="table table-bordered" id="tabla-maquinavending-articulos">
    <thead>
        <tr>
            <th style="width: 12%;">N&deg; rulo</th>
            <th style="width: 18%;">SKU</th>
            <th style="width: 38%;">Descripci&oacute;n</th>
            <th style="width: 15%;">Precio fijo</th>
            <th style="width: 10%;"></th>
        </tr>
    </thead>
    <tbody id="tbody-maquinavending-articulos">
        @php
            $lineasForm = [];
            if (old('numero_rulo') !== null) {
                foreach (old('numero_rulo', []) as $idx => $numero) {
                    $lineasForm[] = (object) [
                        'numero_rulo' => $numero,
                        'articulo_id' => old('articulo_ids.'.$idx),
                        'sku' => old('codigoarticulos.'.$idx),
                        'descripcion' => old('descripcionarticulos.'.$idx),
                    ];
                }
            } elseif (($data->articulos ?? collect())->count() > 0) {
                foreach ($data->articulos as $lineaModel) {
                    $lineasForm[] = $lineaModel;
                }
            } else {
                $lineasForm[] = null;
            }
        @endphp
        @foreach ($lineasForm as $idxLinea => $linea)
            @include('ventas.maquinavending.partials.fila_articulo', ['linea' => $linea, 'idxLinea' => $idxLinea])
        @endforeach
    </tbody>
</table>
@include('ventas.maquinavending.template_articulo')
<div class="row mb-3">
    <div class="col-md-12">
        <button type="button" id="agrega_renglon_maquinavending_articulo" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
