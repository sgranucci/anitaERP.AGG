@php
    $idxLabel = is_numeric($idx) ? ((int) $idx + 1) : $idx;
@endphp
<tr class="fila-item-pedido-if">
    <td>
        <input type="text" name="items[{{ $idx }}][numeroitem]" class="form-control form-control-sm item-numero"
               value="{{ $item['numeroitem'] ?? $idxLabel }}" readonly style="width:3rem;">
    </td>
    <td style="min-width:9rem;">
        <input type="hidden" name="items[{{ $idx }}][articulo_id]" class="articulo_id" value="{{ $item['articulo_id'] ?? '' }}">
        <div class="input-group input-group-sm">
            <input type="text" class="form-control codigoarticulo" value="{{ $item['codigo_articulo'] ?? '' }}"
                   placeholder="C&oacute;d." title="SKU; Enter valida; F1 consulta" autocomplete="off">
            <div class="input-group-append">
                <button type="button" class="btn btn-outline-secondary consultaarticulo" title="Consulta art&iacute;culos (F1)">
                    <i class="fa fa-search"></i>
                </button>
            </div>
        </div>
    </td>
    <td>
        <input type="text" class="form-control form-control-sm descripcionarticulo" readonly
               value="{{ $item['desc_articulo'] ?? '' }}">
        <input type="hidden" name="items[{{ $idx }}][descripcion_aux]" value="{{ $item['descripcion_aux'] ?? '' }}">
    </td>
    <td>
        <input type="text" name="items[{{ $idx }}][articulo_cliente]" class="form-control form-control-sm"
               value="{{ $item['articulo_cliente'] ?? '' }}" maxlength="16">
    </td>
    <td>
        <input type="number" step="0.000001" name="items[{{ $idx }}][cantidad]" class="form-control form-control-sm"
               value="{{ $item['cantidad'] ?? '' }}" required>
    </td>
    <td>
        <select name="items[{{ $idx }}][unidadmedida_id]" class="form-control form-control-sm">
            <option value="">--</option>
            @foreach ($unidadmedida_query as $um)
                <option value="{{ $um->id }}" @if ((int) ($item['unidadmedida_id'] ?? 0) === (int) $um->id) selected @endif>
                    {{ $um->abreviatura ?? $um->nombre }}
                </option>
            @endforeach
        </select>
        <input type="hidden" name="items[{{ $idx }}][unidadmedida_alter_id]" value="{{ $item['unidadmedida_alter_id'] ?? '' }}">
    </td>
    <td>
        <input type="number" step="0.000001" name="items[{{ $idx }}][cantidad_alter]" class="form-control form-control-sm"
               value="{{ $item['cantidad_alter'] ?? '' }}">
    </td>
    <td>
        <input type="date" name="items[{{ $idx }}][fechaentrega]" class="form-control form-control-sm"
               value="{{ $item['fechaentrega'] ?? '' }}">
    </td>
    <td>
        <input type="number" step="0.0001" name="items[{{ $idx }}][porc_fason]" class="form-control form-control-sm"
               value="{{ $item['porc_fason'] ?? 0 }}">
        <input type="hidden" name="items[{{ $idx }}][precio_fason]" value="{{ $item['precio_fason'] ?? 0 }}">
        <input type="hidden" name="items[{{ $idx }}][partida]" value="{{ $item['partida'] ?? 0 }}">
    </td>
    <td>
        <input type="number" step="0.000001" name="items[{{ $idx }}][precio]" class="form-control form-control-sm"
               value="{{ $item['precio'] ?? '' }}">
        <input type="hidden" name="items[{{ $idx }}][moneda_id]" value="{{ $item['moneda_id'] ?? '' }}">
        <input type="hidden" name="items[{{ $idx }}][listaprecio_id]" value="{{ $item['listaprecio_id'] ?? '' }}">
        <input type="hidden" name="items[{{ $idx }}][incluyeimpuesto]" value="{{ $item['incluyeimpuesto'] ?? 'N' }}">
        <input type="hidden" name="items[{{ $idx }}][estado]" value="{{ $item['estado'] ?? 'P' }}">
        <input type="hidden" name="items[{{ $idx }}][orden_compra]" value="{{ $item['orden_compra'] ?? '' }}">
    </td>
    <td>
        <input type="number" step="0.01" name="items[{{ $idx }}][descuento]" class="form-control form-control-sm"
               value="{{ $item['descuento'] ?? 0 }}">
    </td>
    <td>
        <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-item-pedido-if" title="Quitar">
            <i class="fa fa-times"></i>
        </button>
    </td>
</tr>
