@php
    $idx = $idx ?? 0;
    $linea = $linea ?? [];
    $ro = $ro ?? false;
@endphp
<tr class="ri-linea-row">
    <td>
        @if (! empty($linea['id']))
            <input type="hidden" name="lineas[{{ $idx }}][id]" value="{{ $linea['id'] }}">
        @endif
        <input type="hidden" name="lineas[{{ $idx }}][articulo_id]" class="ri-articulo-id" value="{{ $linea['articulo_id'] ?? '' }}">
        <input type="text" name="lineas[{{ $idx }}][articulo_codigo]" class="form-control form-control-sm ri-articulo-codigo"
               value="{{ $linea['articulo_codigo'] ?? '' }}"
               placeholder="SKU / Enter"
               autocomplete="off"
               {{ $ro ? 'readonly' : '' }}>
        <div class="ri-sugerencias list-group position-absolute d-none" style="z-index:20; max-height:180px; overflow:auto; min-width:220px;"></div>
    </td>
    <td>
        <input type="text" name="lineas[{{ $idx }}][descripcion]" class="form-control form-control-sm ri-descripcion"
               value="{{ $linea['descripcion'] ?? '' }}" readonly>
    </td>
    <td>
        <input type="hidden" name="lineas[{{ $idx }}][combinacion_id]" class="ri-combinacion-id" value="{{ $linea['combinacion_id'] ?? '' }}">
        <input type="hidden" name="lineas[{{ $idx }}][color_id]" class="ri-color-id" value="{{ $linea['color_id'] ?? '' }}">
        <select class="form-control form-control-sm ri-variante-select" {{ $ro ? 'disabled' : '' }}>
            @if (! empty($linea['combinacion_id']) || ! empty($linea['color_id']))
                <option value="{{ ! empty($linea['combinacion_id']) ? 'c:'.$linea['combinacion_id'] : 'col:'.$linea['color_id'] }}" selected>
                    {{ $linea['combinacion_label'] ?? '' }}
                </option>
            @else
                <option value="">—</option>
            @endif
        </select>
    </td>
    <td>
        <input type="hidden" name="lineas[{{ $idx }}][talle_id]" class="ri-talle-id" value="{{ $linea['talle_id'] ?? '' }}">
        <select class="form-control form-control-sm ri-talle-select" {{ $ro ? 'disabled' : '' }}>
            @if (! empty($linea['talle_id']))
                <option value="{{ $linea['talle_id'] }}" selected>{{ $linea['talle_label'] ?? $linea['talle_id'] }}</option>
            @else
                <option value="">—</option>
            @endif
        </select>
    </td>
    <td>
        <input type="number" step="0.0001" min="0.0001" name="lineas[{{ $idx }}][cantidad]"
               class="form-control form-control-sm ri-cantidad text-right"
               value="{{ $linea['cantidad'] ?? 1 }}"
               {{ $ro ? 'readonly' : 'required' }}>
    </td>
    <td class="text-center">
        @if (! $ro)
            <button type="button" class="btn-accion-tabla ri-quitar-linea" title="Quitar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        @endif
    </td>
</tr>
