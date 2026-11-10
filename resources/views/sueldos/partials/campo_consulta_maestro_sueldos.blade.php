{{--
    Consulta estándar de maestros de sueldos (categoría, agrupamiento, etc.).
    F1 / lupa = modal. Código + Enter = valida y pasa a data-siguiente.
--}}
@php
    $maestro = $maestro ?? '';
    $registroId = $registroId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $label = $label ?? 'Maestro';
    $inputName = $inputName ?? ($maestro.'_id');
    $inputId = $inputId ?? ($maestro.'_id');
    $required = ! empty($required);
    $siguiente = $siguiente ?? '';
    $extraAttrs = $extraAttrs ?? [];
    $titleCodigo = 'Código + Enter para validar; F1 o lupa para buscar';
    $cfg = \App\Support\Sueldos\ConsultaMaestroSueldosCatalogo::get($maestro);
    $puedeAbrirAbm = $cfg
        ? (can($cfg['permiso_editar'], false) || can($cfg['permiso_listar'], false))
        : false;
    $editUrl = ((int) $registroId > 0 && $puedeAbrirAbm && $cfg)
        ? route($cfg['ruta_editar'], [
            'id' => (int) $registroId,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ])
        : '#';
    $colLabel = $col_label ?? 'col-lg-4';
    $colInput = $col_input ?? 'col-lg-8';
@endphp
<div class="form-group row tm-maestro-sueldos-campo" data-maestro-sueldos-campo="1" data-maestro="{{ $maestro }}">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} control-label text-right pr-2" title="{{ $titleCodigo }}">
        {{ $label }}
        @if ($required)
            <span class="text-danger">*</span>
        @endif
    </label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="maestro_sueldos_id"
                   value="{{ $registroId }}" {{ $required ? 'required' : '' }}
                   @foreach ($extraAttrs as $attr => $val)
                       data-{{ $attr }}="{{ $val }}"
                   @endforeach
            >
            <button type="button" title="Consulta {{ $label }} (F1)"
                    class="btn-accion-tabla consultamaestro_sueldos flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-maestro-sueldos tooltipsC flex-shrink-0 {{ (int) $registroId > 0 ? '' : 'd-none' }}"
                   title="Abrir en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigomaestro_sueldos"
                   id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                   placeholder="Cód." autocomplete="off" title="{{ $titleCodigo }}"
                   @if ($siguiente !== '') data-siguiente="{{ $siguiente }}" @endif
                   style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control nombremaestro_sueldos text-truncate"
                   id="{{ $inputId }}_nombre" value="{{ $descripcion }}"
                   placeholder="Descripción" readonly
                   style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
</div>
