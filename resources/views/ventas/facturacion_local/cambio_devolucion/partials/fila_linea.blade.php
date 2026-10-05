@php
    $idx = $idx ?? 0;
    $linea = $linea ?? [];
    $ro = $ro ?? false;
    $tipoLinea = (string) ($linea['tipo'] ?? 'devolver');
@endphp
<tr class="cdm-linea-row" data-modo="">
    <td>
        <select name="lineas[{{ $idx }}][tipo]" class="form-control form-control-sm cdm-tipo" {{ $ro ? 'disabled' : '' }}>
            @foreach ($tiposLinea as $tKey => $tLabel)
                <option value="{{ $tKey }}" {{ $tipoLinea === $tKey ? 'selected' : '' }}>{{ $tLabel }}</option>
            @endforeach
        </select>
        @if ($ro)
            <input type="hidden" name="lineas[{{ $idx }}][tipo]" value="{{ $tipoLinea }}">
        @endif
        <input type="hidden" name="lineas[{{ $idx }}][venta_emision_id]" value="{{ $linea['venta_emision_id'] ?? '' }}">
    </td>
    <td>
        <div class="tm-articulo-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
            <input type="hidden" name="lineas[{{ $idx }}][articulo_id]" class="articulo_id" value="{{ $linea['articulo_id'] ?? '' }}">
            @if (! $ro)
                <button type="button" title="Consulta artículos (F1)" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0" data-solo-facturable="1">
                    <i class="fa fa-search text-primary"></i>
                </button>
            @endif
            <input type="text"
                   name="lineas[{{ $idx }}][articulo_codigo]"
                   class="form-control form-control-sm codigoarticulo"
                   value="{{ $linea['articulo_codigo'] ?? '' }}"
                   placeholder="SKU"
                   autocomplete="off"
                   style="width:7rem;"
                   {{ $ro ? 'readonly' : '' }}>
            <input type="text"
                   name="lineas[{{ $idx }}][descripcion]"
                   class="form-control form-control-sm descripcionarticulo"
                   value="{{ $linea['descripcion'] ?? '' }}"
                   placeholder="Descripción"
                   readonly
                   tabindex="-1">
        </div>
    </td>
    <td class="cdm-celda-combinacion">
        <div class="tm-combinacion-campo cdm-var-box d-flex flex-nowrap align-items-center" style="gap:4px;">
            <input type="hidden" name="lineas[{{ $idx }}][combinacion_id]" class="combinacion_id" value="{{ $linea['combinacion_id'] ?? '' }}">
            @if (! $ro)
                <button type="button" title="Consulta combinaciones (F1)" class="btn-accion-tabla consultacombinacion flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
            @endif
            <input type="text" name="lineas[{{ $idx }}][combinacion_codigo]" class="form-control form-control-sm codigocombinacion" value="{{ $linea['combinacion_codigo'] ?? '' }}" placeholder="Cód." autocomplete="off" style="width:4.5rem;" {{ $ro ? 'readonly' : '' }}>
            <input type="text" name="lineas[{{ $idx }}][combinacion_nombre]" class="form-control form-control-sm descripcioncombinacion" value="{{ $linea['combinacion_nombre'] ?? '' }}" placeholder="Combinación" readonly tabindex="-1">
        </div>
        <span class="cdm-var-na text-muted small d-none">No aplica</span>
    </td>
    <td class="cdm-celda-color">
        <div class="tm-color-campo cdm-var-box d-flex flex-nowrap align-items-center" style="gap:4px;">
            <input type="hidden" name="lineas[{{ $idx }}][color_id]" class="color_id" value="{{ $linea['color_id'] ?? '' }}">
            @if (! $ro)
                <button type="button" title="Consulta colores (F1)" class="btn-accion-tabla consultacolor flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
            @endif
            <input type="text" name="lineas[{{ $idx }}][color_codigo]" class="form-control form-control-sm codigocolor" value="{{ $linea['color_codigo'] ?? '' }}" placeholder="Cód." autocomplete="off" style="width:4.5rem;" {{ $ro ? 'readonly' : '' }}>
            <input type="text" name="lineas[{{ $idx }}][color_nombre]" class="form-control form-control-sm descripcioncolor" value="{{ $linea['color_nombre'] ?? '' }}" placeholder="Color" readonly tabindex="-1">
        </div>
        <span class="cdm-var-na text-muted small d-none">No aplica</span>
    </td>
    <td class="cdm-celda-talle">
        <div class="tm-talle-campo cdm-var-box d-flex flex-nowrap align-items-center" style="gap:4px;">
            <input type="hidden" name="lineas[{{ $idx }}][talle_id]" class="talle_id" value="{{ $linea['talle_id'] ?? '' }}">
            @if (! $ro)
                <button type="button" title="Consulta talles (F1)" class="btn-accion-tabla consultatalle flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
            @endif
            <input type="text" name="lineas[{{ $idx }}][talle_codigo]" class="form-control form-control-sm codigotalle" value="{{ $linea['talle_codigo'] ?? '' }}" placeholder="Cód." autocomplete="off" style="width:4.5rem;" {{ $ro ? 'readonly' : '' }}>
            <input type="text" name="lineas[{{ $idx }}][talle_nombre]" class="form-control form-control-sm descripciontalle" value="{{ $linea['talle_nombre'] ?? '' }}" placeholder="Talle" readonly tabindex="-1">
        </div>
        <span class="cdm-var-na text-muted small d-none">No aplica</span>
    </td>
    <td>
        <input type="number" step="0.0001" min="0.0001" name="lineas[{{ $idx }}][cantidad]" class="form-control form-control-sm cdm-cantidad text-right"
               value="{{ $linea['cantidad'] ?? 1 }}" {{ $ro ? 'readonly' : '' }}>
    </td>
    <td>
        <input type="number" step="0.01" min="0" name="lineas[{{ $idx }}][precio_unitario]" class="form-control form-control-sm cdm-precio text-right"
               value="{{ $linea['precio_unitario'] ?? 0 }}" {{ $ro ? 'readonly' : '' }}>
    </td>
    @if (! $ro)
        <td class="text-center">
            <button type="button" class="btn-accion-tabla cdm-quitar-linea" title="Quitar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    @endif
</tr>
