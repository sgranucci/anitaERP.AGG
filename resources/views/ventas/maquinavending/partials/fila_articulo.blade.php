@php
    $idxLinea = $idxLinea ?? 0;
    $numeroRulo = $linea->numero_rulo ?? '';
    $articuloId = $linea->articulo_id ?? '';
    $sku = $linea->sku ?? optional($linea->articulo ?? null)->sku ?? '';
    $descripcion = $linea->descripcion ?? optional($linea->articulo ?? null)->descripcion ?? '';
    $precioLista = $linea->precio_lista ?? optional($linea->articulo ?? null)->precio_lista ?? '';
@endphp
<tr class="item-maquinavending-articulo" data-articulo-solo-insumo-gastronomia="1">
    <td>
        <input type="number" name="numero_rulo[]" class="form-control numero-rulo" min="1" step="1"
               value="{{ old('numero_rulo.'.$idxLinea, $numeroRulo) }}">
    </td>
    <td>
        <div class="form-group row mb-0" id="articulo">
            <input type="hidden" class="articulo_id" name="articulo_ids[]"
                   value="{{ old('articulo_ids.'.$idxLinea, $articuloId) }}">
            <button type="button" title="Consulta art&iacute;culos insumo" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" style="width: 150px; height: 38px;" class="codigoarticulo form-control" name="codigoarticulos[]"
                   value="{{ old('codigoarticulos.'.$idxLinea, $sku) }}">
        </div>
    </td>
    <td>
        <input type="text" style="width: 100%; height: 38px;" class="descripcionarticulo form-control" name="descripcionarticulos[]"
               value="{{ old('descripcionarticulos.'.$idxLinea, $descripcion) }}" readonly>
    </td>
    <td>
        <input type="number" name="precio_lista[]" class="form-control precio-lista" min="0" step="0.01"
               value="{{ old('precio_lista.'.$idxLinea, $precioLista !== '' && $precioLista !== null ? number_format((float) $precioLista, 2, '.', '') : '') }}"
               placeholder="Opcional">
    </td>
    <td class="text-center">
        <button type="button" title="Elimina esta l&iacute;nea" class="btn-accion-tabla eliminar_maquinavending_articulo tooltipsC">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
