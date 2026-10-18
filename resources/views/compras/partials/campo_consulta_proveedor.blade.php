{{--
    Proveedor: ID oculto + c&oacute;digo + nombre + lupa.
    Requiere includes.compras.modalconsultaproveedor + compras/proveedor/consulta.js
--}}
@php
    $prefix = $prefix ?? 'proveedor';
    $proveedorId = $proveedorId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $label = $label ?? 'Proveedor';
    $inputName = $inputName ?? 'proveedor_id';
    $inputId = $inputId ?? ('proveedor_'.$prefix.'_id');
    $required = ! empty($required);
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-proveedor', false) || can('listar-proveedor', false);
    $editUrl = ((int) $proveedorId > 0 && $puedeAbrirAbm)
        ? route('editar_proveedor', ['id' => (int) $proveedorId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="form-group row tm-proveedor-campo" data-proveedor-campo="1">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="proveedor_id"
                   value="{{ $proveedorId }}">
            <button type="button" title="Consulta proveedores (F1)"
                    class="btn-accion-tabla consultaproveedor flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-proveedor tooltipsC flex-shrink-0 {{ (int) $proveedorId > 0 ? '' : 'd-none' }}"
                   title="Abrir proveedor en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigoproveedor"
                   id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                   placeholder="C&oacute;d." autocomplete="off"
                   style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control nombreproveedor text-truncate"
                   id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                   placeholder="Nombre" readonly
                   style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
</div>
