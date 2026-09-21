{{--
    Campo deposito: ID oculto + codigo + descripcion + modal consulta (+ enlace ABM opcional).

    Variables:
    - $prefix (salida|entrada|recuento|...)
    - $depositoId, $codigo, $descripcion
    - $label, $inputName (default deposito_id), $inputId
    - $solo_lectura, $required (default true), $mostrar_editar (default true)
    - $layout: inline (transferencia) | form_row (CRUD con label a la derecha)
    - $col_label, $col_input (solo form_row)
    - $wrap_row: true envuelve label+campo en form-group row; false solo columnas (compartir renglon)

    Nota: textos visibles con entidades HTML (&oacute; etc.) para evitar corrupcion de encoding.
--}}
@php
    $prefix = $prefix ?? 'deposito';
    $label = $label ?? 'Depósito';
    $depositoId = $depositoId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $inputName = $inputName ?? 'deposito_id';
    $inputId = $inputId ?? ('deposito_'.$prefix.'_id');
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? true;
    $mostrarEditar = $mostrar_editar ?? true;
    $layout = $layout ?? 'inline';
    $colLabel = $col_label ?? 'col-lg-4';
    $colInput = $col_input ?? 'col-lg-7';
    $wrapRow = $wrap_row ?? true;
    $tipodeposito = $tipodeposito ?? '';
    $codigoExtraClass = trim((string) ($codigoExtraClass ?? ''));
    $puedeAbrirAbmDeposito = can('editar-depositos', false) || can('listar-depositos', false);
    $editUrl = ((int) $depositoId > 0 && $puedeAbrirAbmDeposito)
        ? route('editar_depmae', ['id' => (int) $depositoId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

@if ($layout === 'form_row')
    @if ($wrapRow)
    <div class="form-group row tm-deposito-campo mb-2" id="tm_deposito_{{ $prefix }}" data-tipodeposito="{{ $tipodeposito }}">
    @endif
        <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} control-label text-right pr-2 {{ $required ? 'requerido' : '' }}">{{ $label }}@if(!empty($ayuda_tooltip)) <i class="fa fa-question-circle text-muted tooltipsC ml-1" title="{{ $ayuda_tooltip }}"></i>@endif</label>
        @if ($wrapRow)
        <div class="{{ $colInput }}">
        @else
        <div class="{{ $colInput }} tm-deposito-campo" id="tm_deposito_{{ $prefix }}" data-tipodeposito="{{ $tipodeposito }}">
        @endif
            <div class="d-flex flex-nowrap align-items-center tm-deposito-campo-inputs w-100" style="gap: 4px;">
                <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="deposito_id"
                    value="{{ $depositoId }}" @if ($required && ! $soloLectura) required @endif>
                @if ($soloLectura)
                    <input type="text" class="form-control codigodeposito"
                        id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                    <input type="text" class="form-control descripciondeposito text-truncate"
                        id="{{ $inputId }}_descripcion" value="{{ $descripcion }}" readonly
                        style="min-width: 0; flex: 1 1 auto;">
                @else
                    <button type="button" title="Consulta dep&oacute;sitos (F1)" class="btn-accion-tabla consultadeposito flex-shrink-0">
                        <i class="fa fa-search text-primary"></i>
                    </button>
                    @if ($mostrarEditar && $puedeAbrirAbmDeposito)
                        <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                            class="btn-accion-tabla btn-link-editar-deposito tooltipsC flex-shrink-0 {{ (int) $depositoId > 0 ? '' : 'd-none' }}"
                            title="Abrir dep&oacute;sito en ABM">
                            <i class="fa fa-edit"></i>
                        </a>
                    @endif
                    <input type="text" class="form-control codigodeposito{{ $codigoExtraClass !== '' ? ' '.$codigoExtraClass : '' }}"
                        id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                        placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off"
                        style="width: 5.5rem; flex-shrink: 0;">
                    <input type="text" class="form-control descripciondeposito text-truncate"
                        id="{{ $inputId }}_descripcion" value="{{ $descripcion }}"
                        placeholder="Descripci&oacute;n" readonly
                        style="min-width: 0; flex: 1 1 auto;">
                @endif
            </div>
        </div>
    @if ($wrapRow)
    </div>
    @endif
@else
    {{-- inline / compact (tablas): sin label si $label === '' --}}
    <div class="tm-deposito-campo d-flex flex-nowrap align-items-center w-100 {{ trim((string) $label) !== '' ? 'form-group col-12 mb-2 flex-column align-items-stretch' : '' }}"
        id="tm_deposito_{{ $prefix }}" data-tipodeposito="{{ $tipodeposito }}" style="gap: 4px;">
        @if (trim((string) $label) !== '')
            <label class="d-block mb-1">{{ $label }}</label>
        @endif
        <div class="d-flex flex-nowrap align-items-center tm-deposito-campo-inputs w-100" style="gap: 4px;">
            <input type="hidden" class="deposito_id" id="{{ $inputId }}"
                name="{{ $inputName }}" value="{{ $depositoId }}"
                @if ($required && ! $soloLectura) required @endif>
            @if (! $soloLectura)
                <button type="button" title="Consulta dep&oacute;sitos (F1)" class="btn-accion-tabla consultadeposito flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbmDeposito)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-deposito flex-shrink-0 {{ (int) $depositoId > 0 ? '' : 'd-none' }}"
                        title="Abrir dep&oacute;sito en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control form-control-sm codigodeposito flex-shrink-0"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off"
                    style="width: 5.5rem;">
                <input type="text" class="form-control form-control-sm descripciondeposito text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}"
                    placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
            @else
                <input type="text" class="form-control form-control-sm codigodeposito flex-shrink-0"
                    value="{{ $codigo }}" readonly style="width: 5.5rem;">
                <input type="text" class="form-control form-control-sm descripciondeposito text-truncate"
                    value="{{ $descripcion }}" readonly style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
    </div>
@endif
