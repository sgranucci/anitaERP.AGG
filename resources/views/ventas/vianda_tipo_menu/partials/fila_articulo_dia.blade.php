@php
    $articuloId = $linea->articulo_id ?? '';
    $sku = $linea->sku ?? optional($linea->articulo ?? null)->sku ?? '';
    $descripcion = $linea->descripcion ?? optional($linea->articulo ?? null)->descripcion ?? '';
@endphp
<div class="vianda-articulo-fila mb-2 item-vianda-articulo-dia" data-dia="{{ $dia }}">
    <input type="hidden" class="articulo_id" name="articulo_por_dia[{{ $dia }}][]"
           value="{{ old('articulo_por_dia.'.$dia.'.'.$idxLinea, $articuloId) }}">
    <div class="input-group input-group-sm mb-1">
        <div class="input-group-prepend">
            <button type="button" title="Consulta art&iacute;culos" class="btn btn-outline-primary btn-sm consultaarticulo">
                <i class="fa fa-search"></i>
            </button>
        </div>
        <input type="text" class="form-control form-control-sm codigoarticulo" name="codigoarticulos_dia[{{ $dia }}][]"
               value="{{ old('codigoarticulos_dia.'.$dia.'.'.$idxLinea, $sku) }}" placeholder="SKU">
    </div>
    <input type="text" class="form-control form-control-sm descripcionarticulo mb-1" name="descripcionarticulos_dia[{{ $dia }}][]"
           value="{{ old('descripcionarticulos_dia.'.$dia.'.'.$idxLinea, $descripcion) }}" readonly placeholder="Descripci&oacute;n">
    <button type="button" title="Quitar art&iacute;culo" class="btn btn-sm btn-link text-danger p-0 eliminar-articulo-dia">
        <i class="fa fa-times-circle"></i> Quitar
    </button>
</div>
