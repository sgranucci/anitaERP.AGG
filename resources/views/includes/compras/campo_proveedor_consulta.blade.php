@php
    $proveedorIdVal = old('proveedor_id', $proveedor_id ?? '');
    $codigoVal = old('codigoproveedor', $codigo_proveedor ?? '');
    $nombreVal = old('nombreproveedor', $nombre_proveedor ?? '');
    $colLabel = $col_label ?? 'col-lg-3';
    $colInput = $col_input ?? 'col-lg-8';
    $labelRequerido = ! empty($requerido);
    $mostrarAyuda = $mostrar_ayuda ?? true;
    $estiloContenedor = $estilo_contenedor ?? '';
@endphp
<div class="form-group row align-items-center" id="div-proveedor"@if ($estiloContenedor !== '') style="{{ $estiloContenedor }}"@endif>
    <label for="codigoproveedor" class="{{ $colLabel }} col-form-label{{ $labelRequerido ? ' requerido' : '' }}">Proveedor</label>
    <div class="{{ $colInput }}">
        <input type="hidden" id="proveedor_id" name="proveedor_id" class="proveedor_id"
            @if ($labelRequerido) required @endif
            value="{{ $proveedorIdVal }}">
        <div class="d-flex flex-wrap align-items-center">
            <input type="text" class="form-control codigoproveedor mr-2" id="codigoproveedor" name="codigoproveedor"
                value="{{ $codigoVal }}" style="width: 6rem;" autocomplete="off"
                @if (! empty($solo_lectura_codigo)) readonly @endif>
            <input type="text" class="form-control nombreproveedor mr-2" id="nombreproveedor" name="nombreproveedor"
                value="{{ $nombreVal }}" readonly style="min-width: 8rem; flex: 1;">
            @if (empty($solo_lectura_codigo))
            <button type="button" title="Consulta proveedores (F1)" class="btn btn-outline-primary btn-sm consultaproveedor tooltipsC flex-shrink-0 ml-1">
                <i class="fa fa-search"></i>
            </button>
            @endif
            <a href="{{ route('editar_proveedor', ['id' => (int) $proveedorIdVal]) }}"
               class="btn-accion-tabla tooltipsC editarproveedor ml-1 flex-shrink-0" title="Editar proveedor" target="_blank" rel="noopener">
                <i class="fa fa-edit"></i>
            </a>
        </div>
        @if ($mostrarAyuda && empty($solo_lectura_codigo))
        <small class="text-muted d-block mt-1">Código + Enter o Tab · <kbd>F1</kbd> o lupa para consultar</small>
        @endif
        @if (! empty($mostrar_aviso_cuenta))
        <span id="cp-aviso-proveedor-cuenta" class="small text-danger d-none d-block mt-1"></span>
        @endif
    </div>
</div>
