@php
    $idxLabel = is_numeric($idx) ? ((int) $idx + 1) : $idx;
    $umSelectedId = (int) ($item['unidadmedida_id'] ?? 0);
    $umAbrItem = strtoupper(trim((string) ($item['umd_abreviatura'] ?? '')));
    $umAltAbr = strtoupper(trim((string) ($item['umd_alter_abreviatura'] ?? '')));
    $descArticulo = (string) ($item['desc_articulo'] ?? '');
    $cantidadShow = $item['cantidad'] ?? '';
    if ($cantidadShow !== '' && $cantidadShow !== null && is_numeric($cantidadShow)) {
        $cantidadShow = rtrim(rtrim(number_format((float) $cantidadShow, 6, '.', ''), '0'), '.');
    }
    $cantAlterShow = $item['cantidad_alter'] ?? '';
    if ($cantAlterShow !== '' && $cantAlterShow !== null && is_numeric($cantAlterShow)) {
        $cantAlterShow = rtrim(rtrim(number_format((float) $cantAlterShow, 6, '.', ''), '0'), '.');
    }
    $fmtPrecio3 = static function ($valor) {
        if ($valor === '' || $valor === null || ! is_numeric($valor)) {
            return $valor ?? '';
        }

        return rtrim(rtrim(number_format((float) $valor, 3, '.', ''), '0'), '.');
    };
    $porcFasonShow = $fmtPrecio3($item['porc_fason'] ?? 0);
    if ($porcFasonShow === '') {
        $porcFasonShow = '0';
    }
    $precioFasonShow = $fmtPrecio3($item['precio_fason'] ?? 0);
    if ($precioFasonShow === '') {
        $precioFasonShow = '0';
    }
    $precioShow = $fmtPrecio3($item['precio'] ?? '');
@endphp
<tr class="fila-item-pedido-if">
    <td class="align-middle">
        <input type="text" name="items[{{ $idx }}][numeroitem]" class="form-control form-control-sm item-numero"
               value="{{ $item['numeroitem'] ?? $idxLabel }}" readonly style="width:2.5rem;">
    </td>
    <td class="align-middle" style="min-width:9rem;">
        <input type="hidden" name="items[{{ $idx }}][articulo_id]" class="articulo_id" value="{{ $item['articulo_id'] ?? '' }}">
        <input type="hidden" name="items[{{ $idx }}][articulo_cliente]" value="{{ $item['articulo_cliente'] ?? '' }}">
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
    <td class="align-middle" style="min-width:22rem;">
        <input type="text" class="form-control form-control-sm descripcionarticulo" readonly
               value="{{ $descArticulo }}" title="{{ $descArticulo }}">
        <input type="hidden" name="items[{{ $idx }}][descripcion_aux]" value="{{ $item['descripcion_aux'] ?? '' }}">
    </td>
    <td class="align-middle" style="min-width:7.5rem;">
        <input type="number" step="0.001" inputmode="decimal" name="items[{{ $idx }}][cantidad]"
               class="form-control form-control-sm cantidad text-right" value="{{ $cantidadShow }}" required>
        <input type="hidden" class="coeficienteconversion" value="{{ $item['coeficienteconversion'] ?? '' }}">
        <input type="hidden" class="umd_abreviatura" value="{{ $item['umd_abreviatura'] ?? '' }}">
    </td>
    <td class="align-middle" style="min-width:4.5rem;">
        <select name="items[{{ $idx }}][unidadmedida_id]" class="form-control form-control-sm unidadmedida_id">
            <option value="">--</option>
            @foreach ($unidadmedida_query as $um)
                @php
                    $umAbrOpt = strtoupper(trim((string) ($um->abreviatura ?? '')));
                    $umMatch = $umSelectedId === (int) $um->id
                        || ($umAbrItem !== '' && $umAbrOpt === $umAbrItem);
                @endphp
                <option value="{{ $um->id }}"
                    data-abreviatura="{{ $um->abreviatura ?? '' }}"
                    @if ($umMatch) selected @endif>
                    {{ $um->abreviatura ?? $um->nombre }}
                </option>
            @endforeach
        </select>
        <input type="hidden" name="items[{{ $idx }}][unidadmedida_alter_id]" class="unidadmedida_alter_id"
               value="{{ $item['unidadmedida_alter_id'] ?? '' }}">
        <input type="hidden" class="umd_alter_abreviatura" value="{{ $item['umd_alter_abreviatura'] ?? '' }}">
    </td>
    <td class="align-middle" style="min-width:8rem;">
        <div class="input-group input-group-sm">
            <input type="number" step="0.001" inputmode="decimal" name="items[{{ $idx }}][cantidad_alter]"
                   class="form-control form-control-sm cantidad_alter text-right" value="{{ $cantAlterShow }}">
            <div class="input-group-append">
                <span class="input-group-text umd-alter-label" title="Unidad alternativa del art&iacute;culo">{{ $umAltAbr !== '' ? $umAltAbr : '—' }}</span>
            </div>
        </div>
    </td>
    <td class="align-middle" style="min-width:9rem;">
        <input type="date" name="items[{{ $idx }}][fechaentrega]" class="form-control form-control-sm"
               value="{{ $item['fechaentrega'] ?? '' }}">
    </td>
    <td class="align-middle" style="min-width:7rem;">
        <div class="input-group input-group-sm">
            <input type="number" step="0.001" inputmode="decimal" name="items[{{ $idx }}][porc_fason]"
                   class="form-control form-control-sm text-right porc_fason"
                   value="{{ $porcFasonShow }}" title="% de la cantidad a fas&oacute;n (no modifica el precio)">
            <div class="input-group-append">
                <span class="input-group-text">%</span>
            </div>
        </div>
        <input type="number" step="0.001" inputmode="decimal" name="items[{{ $idx }}][precio_fason]"
               class="form-control form-control-sm text-right precio_fason mt-1"
               value="{{ $precioFasonShow }}" readonly
               title="Precio de lista Fason (si no hay precio en la lista queda 0)">
        <input type="hidden" name="items[{{ $idx }}][partida]" value="{{ $item['partida'] ?? 0 }}">
        <input type="hidden" name="items[{{ $idx }}][moneda_fason_id]" class="moneda_fason_id"
               value="{{ $item['moneda_fason_id'] ?? '' }}">
    </td>
    <td class="align-middle" style="min-width:7rem;">
        <input type="number" step="0.001" inputmode="decimal" name="items[{{ $idx }}][precio]"
               class="form-control form-control-sm precio text-right" value="{{ $precioShow }}">
        <input type="hidden" name="items[{{ $idx }}][moneda_id]" class="moneda_id" value="{{ $item['moneda_id'] ?? '' }}">
        <input type="hidden" name="items[{{ $idx }}][listaprecio_id]" class="listaprecio_id"
               value="{{ $item['listaprecio_id'] ?? '' }}">
        <input type="hidden" name="items[{{ $idx }}][incluyeimpuesto]" class="incluyeimpuesto"
               value="{{ $item['incluyeimpuesto'] ?? 'N' }}">
        <input type="hidden" name="items[{{ $idx }}][orden_compra]" value="{{ $item['orden_compra'] ?? '' }}">
    </td>
    <td class="align-middle" style="min-width:4.5rem;">
        <input type="number" step="0.01" name="items[{{ $idx }}][descuento]" class="form-control form-control-sm text-right"
               value="{{ $item['descuento'] ?? 0 }}">
    </td>
    <td class="align-middle text-nowrap">
        @php
            $estadoItem = (string) ($item['estado'] ?? 'P');
            $etiquetaItem = \App\Support\Ventas\PedidoEstadosInterforming::etiquetaItem($estadoItem);
            if (in_array($estadoItem, ['A', 'D', 'E'], true)) {
                $badgeItem = 'badge badge-success';
            } elseif ($estadoItem === 'R') {
                $badgeItem = 'badge badge-danger';
            } elseif ($estadoItem === 'C') {
                $badgeItem = 'badge badge-info';
            } else {
                $badgeItem = 'badge badge-warning';
            }
        @endphp
        <span class="{{ $badgeItem }}" title="Estado de aprobación del ítem">{{ $etiquetaItem }}</span>
        <input type="hidden" name="items[{{ $idx }}][estado]" class="item-estado-aprobacion" value="{{ $estadoItem }}">
    </td>
    <td class="align-middle">
        <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-item-pedido-if" title="Quitar">
            <i class="fa fa-times"></i>
        </button>
    </td>
</tr>
