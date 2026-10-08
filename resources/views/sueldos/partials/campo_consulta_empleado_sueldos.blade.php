{{--
    Selector operativo de empleado: legajo + nombre + F1/lupa.
    layouts: compact (filtros) | form_row (ABM).
    Si idInputName viene cargado, el hidden envía el id (ej. empleado_id) y el legajo no se postea.
--}}
@php
    $prefix = $prefix ?? 'empleado';
    $inputName = $inputName ?? 'legajo';
    $idInputName = $idInputName ?? '';
    $legajo = $legajo ?? '';
    $empleadoId = $empleadoId ?? '';
    $nombre = $nombre ?? '';
    $label = $label ?? 'Empleado';
    $nextFocus = $nextFocus ?? '';
    $layout = $layout ?? 'compact';
    $required = ! empty($required);
    $colLabel = $col_label ?? 'col-lg-3 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-6';
    $titleCodigo = 'Legajo + Enter para validar y avanzar; F1 o lupa para buscar';
    $hiddenId = $idInputName !== '' ? $idInputName : $prefix.'_id';
    $puedeAbrirAbm = can('editar-empleado-sueldos', false) || can('listar-empleado-sueldos', false);
    $editUrl = ((int) $empleadoId > 0 && $puedeAbrirAbm)
        ? route('editar_empleado_sueldos', [
            'id' => (int) $empleadoId,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ])
        : '#';
    $mostrarLinkAbm = (int) $empleadoId > 0 && $puedeAbrirAbm;
    $esFormRow = $layout === 'form_row';
    $attrNameHidden = $idInputName !== '' ? 'name="'.e($idInputName).'"' : '';
    $attrNameLegajo = $idInputName === '' ? 'name="'.e($inputName).'"' : '';
    $attrRequired = $required ? 'required' : '';
@endphp

<div class="{{ $esFormRow ? 'form-group row' : 'form-group mb-0' }} tm-empleado-sueldos-campo"
     data-next-focus="{{ $nextFocus }}">
    @if ($esFormRow)
        <label class="{{ $colLabel }}{{ $required ? ' requerido' : '' }}" for="{{ $prefix }}_legajo" title="{{ $titleCodigo }}">{{ $label }}</label>
        <div class="{{ $colInput }}">
    @else
        <label class="small mb-1 d-block" for="{{ $prefix }}_legajo" title="{{ $titleCodigo }}">
            {{ $label }}
            @if ($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif
    <div class="d-flex flex-nowrap align-items-center w-100" style="gap:4px;">
        <input type="hidden" class="empleado_sueldos_id" id="{{ $hiddenId }}" value="{{ $empleadoId }}" {!! $attrNameHidden !!}>
        <button type="button" class="btn-accion-tabla consultaempleado_sueldos flex-shrink-0"
                title="Consultar empleados (F1)">
            <i class="fa fa-search text-primary"></i>
        </button>
        <a href="{{ $editUrl }}" target="_blank" rel="noopener"
           class="btn-accion-tabla btn-link-editar-empleado-sueldos flex-shrink-0 {{ $mostrarLinkAbm ? '' : 'd-none' }}"
           title="Consultar empleado">
            <i class="fa fa-eye"></i>
        </a>
        <input type="text" inputmode="numeric" pattern="[0-9]*"
               {!! $attrNameLegajo !!}
               id="{{ $prefix }}_legajo"
               class="form-control {{ $esFormRow ? '' : 'form-control-sm' }} codigoempleado_sueldos"
               value="{{ $legajo }}" placeholder="Legajo" autocomplete="off"
               title="{{ $titleCodigo }}" style="width:6rem;flex-shrink:0;"
               {!! $attrRequired !!}>
        <input type="text" id="{{ $prefix }}_nombre"
               class="form-control {{ $esFormRow ? '' : 'form-control-sm' }} nombreempleado_sueldos text-truncate"
               value="{{ $nombre }}" placeholder="Nombre del empleado" readonly
               style="min-width:0;flex:1 1 auto;">
    </div>
    @if ($esFormRow)
        </div>
    @endif
</div>
