{{--
    Campo tarea (producci&oacute;n): ID oculto + c&oacute;digo (id) + nombre + lupa.
    El maestro no tiene c&oacute;digo aparte: el id Anita (tar_tarea) es el c&oacute;digo.

    Requiere fuera del form:
        @include('includes.produccion.modalconsultatarea')
        <script src="…/produccion/tarea/consulta.js"></script>
--}}
@php
    $prefix = $prefix ?? 'tarea';
    $label = $label ?? 'Tarea';
    $tareaId = $tareaId ?? '';
    $codigo = $codigo ?? (($tareaId !== '' && $tareaId !== null) ? (string) $tareaId : '');
    $descripcion = $descripcion ?? '';
    $inputName = $inputName ?? 'tarea_id';
    $inputId = $inputId ?? ('tarea_'.$prefix.'_id');
    $required = ! empty($required);
    $colLabel = $col_label ?? 'col-lg-2 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-4';
    $nextFocus = $next_focus ?? null;
    $help = $help ?? 'Vac&iacute;o = todos. F1 o lupa consulta; Enter resuelve por id.';
    $puedeAbrirAbm = can('editar-tareas', false) || can('listar-tareas', false);
    $editUrl = ((int) $tareaId > 0 && $puedeAbrirAbm)
        ? route('editar_tarea', ['id' => (int) $tareaId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="form-group row tm-tarea-campo mb-2" id="tm_tarea_{{ $prefix }}"
    @if ($nextFocus)
        data-next-focus="{{ $nextFocus }}"
    @endif
>
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }}{{ $required ? ' requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="tarea_id"
                value="{{ $tareaId }}" @if ($required) required @endif>
            <button type="button" title="Consulta tareas (F1)" class="btn-accion-tabla consultatarea flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                    class="btn-accion-tabla btn-link-editar-tarea tooltipsC flex-shrink-0 {{ (int) $tareaId > 0 ? '' : 'd-none' }}"
                    title="Abrir tarea en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigotarea" id="{{ $inputId }}_codigo"
                value="{{ $codigo }}" placeholder="Id" autocomplete="off"
                title="Id de tarea. F1 = consulta, Enter = resolver"
                style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control nombretarea text-truncate" id="{{ $inputId }}_nombre"
                value="{{ $descripcion }}" placeholder="Nombre" readonly
                style="min-width: 0; flex: 1 1 auto;">
        </div>
        @if ($help)
            <small class="form-text text-muted">{!! $help !!}</small>
        @endif
    </div>
</div>
