@php
    $visualizar = ! empty($visualizar);
    $esModelo = $linea instanceof \App\Models\Compras\Listaprecio_Proveedor_Articulo;
    $lineaId = $esModelo ? ($linea->id ?? '') : ($linea->id ?? '');
    $articuloId = $esModelo ? ($linea->articulo_id ?? '') : ($linea->articulo_id ?? '');
    $sku = $esModelo ? (optional($linea->articulos)->sku ?? '') : ($linea->sku ?? '');
    $descripcion = $esModelo ? (optional($linea->articulos)->descripcion ?? '') : ($linea->descripcion ?? '');
    $precio = $esModelo ? ($linea->precio ?? '') : ($linea->precio ?? '');
    $descuento = $esModelo ? (isset($linea->descuento) ? $linea->descuento : '0') : ($linea->descuento ?? '0');
    $codProv = $esModelo ? ($linea->codigo_articulo_proveedor ?? '') : ($linea->codigo_articulo_proveedor ?? '');
    $fechaVig = $esModelo
        ? ((isset($linea->fechavigencia) && $linea->fechavigencia) ? substr($linea->fechavigencia, 0, 10) : date('Y-m-d'))
        : ($linea->fechavigencia ?? date('Y-m-d'));
@endphp
<tr class="item-listaprecio-articulo">
    <td>
        <input type="hidden" class="linea_id" name="linea_ids[]" value="{{ $lineaId }}">
        <div class="form-group celda-articulo-listaprecio mb-0 d-flex align-items-center flex-nowrap">
            <input type="hidden" class="articulo_id" name="articulo_ids[]" value="{{ $articuloId }}">
            <button type="button" title="Consulta art&iacute;culos (F1)" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0" {{ $visualizar ? 'disabled' : '' }}>
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="codigoarticulo codigoarticulolocal form-control flex-shrink-0" style="width: 140px; max-width: 15vw; height: 38px;" name="codigoarticulos[]" value="{{ $sku }}" {{ $visualizar ? 'readonly' : '' }} placeholder="SKU" title="F1 consulta; Enter resuelve el c&oacute;digo">
        </div>
    </td>
    <td>
        <input type="text" class="descripcionarticulo form-control form-control-sm" name="descripcionarticulos[]" value="{{ $descripcion }}" readonly>
    </td>
    <td>
        <input type="number" step="0.000001" name="precios[]" class="form-control form-control-sm text-right" value="{{ $precio }}" {{ $visualizar ? 'readonly' : '' }}>
    </td>
    <td>
        <input type="number" step="0.01" name="descuentos[]" class="form-control form-control-sm text-right" value="{{ $descuento }}" {{ $visualizar ? 'readonly' : '' }}>
    </td>
    <td>
        <input type="text" name="codigos_articulo_proveedor[]" class="form-control form-control-sm" maxlength="100" value="{{ $codProv }}" {{ $visualizar ? 'readonly' : '' }}>
    </td>
    <td>
        <input type="date" name="fechavigencias[]" class="form-control form-control-sm" value="{{ $fechaVig }}" {{ $visualizar ? 'readonly' : '' }} required>
    </td>
    @if (! $visualizar)
    <td class="text-center">
        <button type="button" title="Eliminar l&iacute;nea" class="btn-accion-tabla eliminar_listaprecio_articulo tooltipsC">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
    @endif
</tr>
