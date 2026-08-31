{{--
    Campo cliente UIF: ID oculto + codigo editable + nombre readonly + lupa + enlace ABM.

    Variables:
    - $prefix (conservar|absorber|...)
    - $clienteId, $codigo, $descripcion
    - $label, $inputName, $inputId
    - $solo_lectura, $required
    - $col_label, $col_input
--}}
@php
    $prefix = $prefix ?? 'cliente_uif';
    $label = $label ?? 'Cliente UIF';
    $clienteId = $clienteId ?? '';
    $codigo = $codigo ?? ($clienteId !== '' ? (string) $clienteId : '');
    $descripcion = $descripcion ?? '';
    $inputName = $inputName ?? 'cliente_uif_id';
    $inputId = $inputId ?? ('cliente_uif_'.$prefix.'_id');
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? true;
    $colLabel = $col_label ?? 'col-lg-3';
    $colInput = $col_input ?? 'col-lg-9';
    $puedeAbrirAbm = can('editar-cliente-uif', false) || can('listar-cliente-uif', false);
    $editUrl = ((int) $clienteId > 0 && $puedeAbrirAbm)
        ? route('edita_cliente_uif', ['id' => (int) $clienteId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

<div class="form-group row tm-cliente-uif-campo mb-2" id="tm_cliente_uif_{{ $prefix }}" data-prefix="{{ $prefix }}">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} control-label text-right pr-2 {{ $required ? 'requerido' : '' }}">
        {{ $label }}
    </label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="cliente_uif_id"
                value="{{ $clienteId }}" data-prefix="{{ $prefix }}"
                @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control codigocliente_uif"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 6rem; flex-shrink: 0;">
                <input type="text" class="form-control descripcioncliente_uif text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consultar clientes UIF (F1)"
                    class="btn-accion-tabla consultacliente_uif flex-shrink-0" data-prefix="{{ $prefix }}">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-cliente-uif tooltipsC flex-shrink-0 {{ (int) $clienteId > 0 ? '' : 'd-none' }}"
                        title="Abrir cliente en ABM" data-prefix="{{ $prefix }}">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigocliente_uif"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    placeholder="ID" autocomplete="off" data-prefix="{{ $prefix }}"
                    style="width: 6rem; flex-shrink: 0;">
                <input type="text" class="form-control descripcioncliente_uif text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}"
                    placeholder="Nombre" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
    </div>
</div>
