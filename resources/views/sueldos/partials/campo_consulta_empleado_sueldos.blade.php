{{--
    Selector operativo de empleado: legajo + nombre + F1/lupa.
    Pensado para rangos y formularios compactos.
--}}
@php
    $prefix = $prefix ?? 'empleado';
    $inputName = $inputName ?? 'legajo';
    $legajo = $legajo ?? '';
    $empleadoId = $empleadoId ?? '';
    $nombre = $nombre ?? '';
    $label = $label ?? 'Empleado';
    $nextFocus = $nextFocus ?? '';
    $titleCodigo = 'Legajo + Enter para validar y avanzar; F1 o lupa para buscar';
@endphp

<div class="form-group mb-0 tm-empleado-sueldos-campo"
     data-next-focus="{{ $nextFocus }}">
    <label class="small mb-1 d-block" for="{{ $prefix }}_legajo" title="{{ $titleCodigo }}">
        {{ $label }}
    </label>
    <div class="d-flex flex-nowrap align-items-center w-100" style="gap:4px;">
        <input type="hidden" class="empleado_sueldos_id" value="{{ $empleadoId }}">
        <button type="button" class="btn-accion-tabla consultaempleado_sueldos flex-shrink-0"
                title="Consultar empleados (F1)">
            <i class="fa fa-search text-primary"></i>
        </button>
        <a href="#" target="_blank" rel="noopener"
           class="btn-accion-tabla btn-link-editar-empleado-sueldos flex-shrink-0 {{ (int) $empleadoId > 0 ? '' : 'd-none' }}"
           title="Consultar empleado">
            <i class="fa fa-eye"></i>
        </a>
        <input type="text" inputmode="numeric" pattern="[0-9]*"
               name="{{ $inputName }}" id="{{ $prefix }}_legajo"
               class="form-control form-control-sm codigoempleado_sueldos"
               value="{{ $legajo }}" placeholder="Legajo" autocomplete="off"
               title="{{ $titleCodigo }}" style="width:6rem;flex-shrink:0;">
        <input type="text" id="{{ $prefix }}_nombre"
               class="form-control form-control-sm nombreempleado_sueldos text-truncate"
               value="{{ $nombre }}" placeholder="Nombre del empleado" readonly
               style="min-width:0;flex:1 1 auto;">
    </div>
</div>
