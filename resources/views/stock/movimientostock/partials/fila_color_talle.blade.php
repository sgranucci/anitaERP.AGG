@php
    $colorIdSel = (int) ($colorId ?? 0);
    $talleIdSel = (int) ($talleId ?? 0);
    $manejaColorTalle = (bool) ($manejaColorTalle ?? false);
@endphp
        <td class="align-middle ms-col-color-talle" @unless($manejaColorTalle) style="display:none;" @endunless>
            <select name="colores_id[]"
                    class="form-control form-control-sm ms-color-id"
                    data-selected="{{ $colorIdSel > 0 ? $colorIdSel : '' }}">
                <option value="">— Color —</option>
            </select>
        </td>
        <td class="align-middle ms-col-color-talle" @unless($manejaColorTalle) style="display:none;" @endunless>
            <select name="talles_id[]"
                    class="form-control form-control-sm ms-talle-id"
                    data-selected="{{ $talleIdSel > 0 ? $talleIdSel : '' }}">
                <option value="">— Talle —</option>
            </select>
        </td>
