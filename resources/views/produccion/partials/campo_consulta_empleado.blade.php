{{--
    Campo empleado (producci&oacute;n): ID oculto + legajo (id) + nombre + lupa.
    El id Anita (emp_legajo) es el legajo/c&oacute;digo.

    Requiere fuera del form:
        @include('includes.produccion.modalconsultaempleado')
        <script src="…/produccion/empleado/consulta.js"></script>
--}}
@php
    $prefix = $prefix ?? 'empleado';
    $label = $label ?? 'Empleado';
    $empleadoId = $empleadoId ?? '';
    $codigo = $codigo ?? (($empleadoId !== '' && $empleadoId !== null) ? (string) $empleadoId : '');
    $descripcion = $descripcion ?? '';
    $inputName = $inputName ?? 'empleado_id';
    $inputId = $inputId ?? ('empleado_'.$prefix.'_id');
    $required = ! empty($required);
    $colLabel = $col_label ?? 'col-lg-2 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-4';
    $nextFocus = $next_focus ?? null;
    $help = $help ?? 'Vac&iacute;o = todos. F1 o lupa consulta; Enter resuelve por legajo.';
    $puedeAbrirAbm = can('editar-empleados', false) || can('listar-empleados', false);
    $editUrl = ((int) $empleadoId > 0 && $puedeAbrirAbm)
        ? route('editar_empleado', ['id' => (int) $empleadoId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="form-group row tm-empleado-campo mb-2" id="tm_empleado_{{ $prefix }}"
    @if ($nextFocus)
        data-next-focus="{{ $nextFocus }}"
    @endif
>
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }}{{ $required ? ' requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="empleado_id"
                value="{{ $empleadoId }}" @if ($required) required @endif>
            <button type="button" title="Consulta empleados (F1)" class="btn-accion-tabla consultaempleado flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                    class="btn-accion-tabla btn-link-editar-empleado tooltipsC flex-shrink-0 {{ (int) $empleadoId > 0 ? '' : 'd-none' }}"
                    title="Abrir empleado en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigoempleado" id="{{ $inputId }}_codigo"
                value="{{ $codigo }}" placeholder="Legajo" autocomplete="off"
                title="Legajo. F1 = consulta, Enter = resolver"
                style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control nombreempleado text-truncate" id="{{ $inputId }}_nombre"
                value="{{ $descripcion }}" placeholder="Nombre" readonly
                style="min-width: 0; flex: 1 1 auto;">
        </div>
        @if ($help)
            <small class="form-text text-muted">{!! $help !!}</small>
        @endif
    </div>
</div>
