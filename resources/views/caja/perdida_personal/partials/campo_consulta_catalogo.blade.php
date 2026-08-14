@php
    $tipo = $tipo ?? 'concepto';
    $label = $label ?? 'Concepto';
    $inputName = $inputName ?? ($tipo.'_id');
    $inputId = $inputId ?? $inputName;
    $registroId = (int) ($registroId ?? 0);
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $required = $required ?? true;
    $requiereEmpresa = in_array($tipo, ['imputacion', 'empleado'], true);
    $puedeAbrirAbm = match ($tipo) {
        'concepto' => can('editar-concepto-perdida', false),
        'imputacion' => can('editar-imputacion-perdida', false),
        'empleado' => can('editar-empleado-sueldos', false),
        default => false,
    };
    $editUrl = '#';
    if ($registroId > 0 && $puedeAbrirAbm) {
        $editUrl = match ($tipo) {
            'concepto' => route('editar_concepto_perdida', [
                'id' => $registroId,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]),
            'imputacion' => route('editar_imputacion_perdida', [
                'id' => $registroId,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]),
            'empleado' => route('editar_empleado_sueldos', ['id' => $registroId]),
            default => '#',
        };
    }
@endphp

<div class="form-group row tm-perdida-catalogo-campo"
     data-tipo="{{ $tipo }}"
     data-requiere-empresa="{{ $requiereEmpresa ? '1' : '0' }}">
    <label for="{{ $inputId }}_codigo"
           class="col-lg-3 control-label text-right pr-2 {{ $required ? 'requerido' : '' }}">
        {!! $label !!}
    </label>
    <div class="col-lg-6">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}"
                   class="perdida-catalogo-id"
                   value="{{ $registroId > 0 ? $registroId : '' }}"
                   data-codigo="{{ $codigo }}"
                   {{ $required ? 'required' : '' }}>
            <button type="button" class="btn-accion-tabla consulta-perdida-catalogo flex-shrink-0"
                    title="Buscar (F1)">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-perdida-catalogo tooltipsC flex-shrink-0 {{ $registroId > 0 ? '' : 'd-none' }}"
                   title="Abrir registro en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" id="{{ $inputId }}_codigo"
                   class="form-control perdida-catalogo-codigo"
                   value="{{ $codigo }}" placeholder="{!! $tipo === 'empleado' ? 'Legajo' : 'C&oacute;d.' !!}"
                   autocomplete="off" style="width: 6.5rem; flex-shrink: 0;"
                   title="C&oacute;digo + Enter para validar; F1 o lupa para buscar">
            <input type="text" id="{{ $inputId }}_descripcion"
                   class="form-control perdida-catalogo-descripcion text-truncate"
                   value="{{ $descripcion }}" placeholder="Descripci&oacute;n" readonly
                   style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
</div>
