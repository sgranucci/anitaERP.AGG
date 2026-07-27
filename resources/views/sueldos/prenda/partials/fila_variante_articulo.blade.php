@php
    $idx = $idx ?? 0;
    $colorId = $colorId ?? null;
    $talleId = $talleId ?? null;
    $sku = (string) ($sku ?? '');
    $articuloId = (int) ($articuloId ?? 0);
    $descripcionArticulo = (string) ($descripcionArticulo ?? '');
    $puedeConsultarArticulo = (bool) ($puedeConsultarArticulo ?? false);
@endphp
<tr class="variante-row">
    <td>
        <select name="variantes[{{ $idx }}][color_id]" class="form-control form-control-sm">
            <option value="">— Color —</option>
            @foreach ($colores as $color)
                <option value="{{ $color->id }}" {{ (int) $colorId === (int) $color->id ? 'selected' : '' }}>{{ $color->codigo }} - {{ $color->nombre }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="variantes[{{ $idx }}][talle_id]" class="form-control form-control-sm">
            <option value="">— Talle —</option>
            @foreach ($talles as $talle)
                <option value="{{ $talle->id }}" {{ (int) $talleId === (int) $talle->id ? 'selected' : '' }}>{{ $talle->nombre }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <div class="d-flex align-items-center flex-nowrap">
            @if ($puedeConsultarArticulo)
                <button type="button" title="Consulta art&iacute;culos (F1)" class="btn-accion-tabla consultaarticulo flex-shrink-0" style="padding:1px 4px;">
                    <i class="fa fa-search text-primary"></i>
                </button>
            @endif
            <a href="{{ $articuloId > 0 ? \App\Support\Stock\ArticuloConsultaDesdeModal::urlEditar($articuloId) : '#' }}"
               class="btn btn-xs btn-link-articulo {{ $articuloId > 0 ? '' : 'd-none' }} flex-shrink-0"
               target="_blank" rel="noopener" title="Consultar art&iacute;culo">
                <i class="fa fa-edit text-primary"></i>
            </a>
            <input type="hidden" class="articulo_id" value="{{ $articuloId > 0 ? $articuloId : '' }}">
            <input type="text"
                   name="variantes[{{ $idx }}][sku]"
                   class="codigoarticulo form-control form-control-sm flex-shrink-0"
                   style="width: 110px; max-width: 28%;"
                   maxlength="20"
                   value="{{ $sku }}"
                   autocomplete="off"
                   placeholder="SKU">
            <input type="text" class="descripcionarticulo form-control form-control-sm flex-grow-1 ml-1" value="{{ $descripcionArticulo }}" readonly
                   placeholder="Descripci&oacute;n" title="{{ $descripcionArticulo }}">
        </div>
    </td>
    <td class="text-center align-middle">
        <button type="button" class="btn btn-sm btn-link text-danger btn-quitar-variante"><i class="fa fa-times-circle"></i></button>
    </td>
</tr>
