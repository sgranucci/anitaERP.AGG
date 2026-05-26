<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="255"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código</label>
    <div class="col-lg-8">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}" required maxlength="50"/>
        <small class="form-text text-muted">Código Anita (<code>clcat_categoria</code>). Debe ser único.</small>
    </div>
</div>

<hr>
<h5>Artículos canjeables</h5>
<p class="text-muted small">Artículos que el cliente puede elegir al canjear puntos de esta categoría de fidelidad.</p>
<table class="table" id="tabla-articulos-categoriafidelidad">
    <thead>
        <tr>
            <th style="width: 15%;">SKU</th>
            <th style="width: 35%;">Descripción</th>
            <th style="width: 4%;"></th>
        </tr>
    </thead>
    <tbody>
        @php
            $lineas = (isset($data) && $data->articulos && $data->articulos->count())
                ? $data->articulos
                : collect([new \App\Models\Ventas\CategoriafidelidadArticuloGastronomia()]);
        @endphp
        @foreach ($lineas as $idx => $linea)
        <tr class="item-categoriafidelidad-articulo">
            <td>
                <input type="hidden" class="linea_id" name="categoriafidelidad_articulo_ids[]" value="{{ old('categoriafidelidad_articulo_ids.'.$idx, $linea->id ?? '') }}">
                <div class="form-group row celda-articulo-categoriafidelidad mb-0">
                    <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{ old('articulo_ids.'.$idx, $linea->articulo_id ?? '') }}">
                    <button type="button" title="Consulta artículos" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                    </button>
                    <input type="text" class="codigoarticulo codigoarticulolocal col-lg-10 form-control" name="codigoarticulos[]" value="{{ old('codigoarticulos.'.$idx, optional($linea->articulo)->sku ?? '') }}">
                </div>
            </td>
            <td>
                <input type="text" class="descripcionarticulo form-control" name="descripcionarticulos[]" value="{{ old('descripcionarticulos.'.$idx, optional($linea->articulo)->descripcion ?? '') }}" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger eliminar_categoriafidelidad_articulo" title="Quitar renglón">
                    <i class="fa fa-times"></i>
                </button>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
<button type="button" id="agrega_renglon_categoriafidelidad_articulo" class="btn btn-outline-secondary btn-sm mt-2">
    <i class="fa fa-plus"></i> Agregar artículo
</button>
