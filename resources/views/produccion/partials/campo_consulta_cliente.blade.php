{{--
    Campo cliente para reportes (rango desde/hasta): hidden id + c&oacute;digo + nombre + lupa.
    Usa el modal compartido includes.ventas.modalconsultacliente + cliente/consulta.js.
    Wrapper .tm-cliente-campo (y .gastro-campo-consulta para compat con resolverPtr).
--}}
@php
    $prefix = $prefix ?? 'cliente';
    $label = $label ?? 'Cliente';
    $clienteId = $clienteId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $inputName = $inputName ?? 'cliente_id';
    $inputId = $inputId ?? ('cliente_'.$prefix.'_id');
    $required = ! empty($required);
    $colLabel = $col_label ?? 'col-lg-2 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-4';
    $nextFocus = $next_focus ?? null;
    $help = $help ?? 'Vac&iacute;o = todos. F1 o lupa consulta; Enter resuelve por c&oacute;digo.';
@endphp
<div class="form-group row tm-cliente-campo gastro-campo-consulta mb-2" id="tm_cliente_{{ $prefix }}"
    @if ($nextFocus)
        data-next-focus="{{ $nextFocus }}"
    @endif
>
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }}{{ $required ? ' requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="cliente_id"
                value="{{ $clienteId }}" @if ($required) required @endif>
            <button type="button" title="Consulta clientes (F1)" class="btn-accion-tabla consultacliente flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="form-control codigocliente" id="{{ $inputId }}_codigo"
                value="{{ $codigo }}" placeholder="C&oacute;d." autocomplete="off"
                title="C&oacute;digo de cliente. F1 = consulta, Enter = resolver"
                style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control nombrecliente text-truncate" id="{{ $inputId }}_nombre"
                value="{{ $descripcion }}" placeholder="Nombre" readonly
                style="min-width: 0; flex: 1 1 auto;">
        </div>
        @if ($help)
            <small class="form-text text-muted">{!! $help !!}</small>
        @endif
    </div>
</div>
