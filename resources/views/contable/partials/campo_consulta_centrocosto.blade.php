{{--
    Campo centro de costo: ID oculto + codigo + descripcion + modal consulta (+ enlace ABM opcional).

    Variables:
    - $prefix
    - $centrocostoId, $codigo, $descripcion
    - $label, $inputName (default centrocosto_id), $inputId
    - $solo_lectura, $required (default true), $mostrar_editar (default true)
    - $layout: inline | form_row
    - $col_label, $col_input (solo form_row)
    - $ayuda (texto small opcional)

    Nota: textos visibles con entidades HTML (&oacute; etc.) para evitar corrupcion de encoding.
--}}
@php
    $prefix = $prefix ?? 'centrocosto';
    $label = $label ?? 'Centro de costo';
    $centrocostoId = $centrocostoId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $inputName = $inputName ?? 'centrocosto_id';
    $inputId = $inputId ?? ('centrocosto_'.$prefix.'_id');
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? true;
    $mostrarEditar = $mostrar_editar ?? true;
    $layout = $layout ?? 'inline';
    $colLabel = $col_label ?? 'col-lg-4';
    $colInput = $col_input ?? 'col-lg-7';
    $ayuda = $ayuda ?? null;
    $puedeAbrirAbmCc = can('editar-centro-costo', false) || can('listar-centro-costo', false);
    $editUrl = ((int) $centrocostoId > 0 && $puedeAbrirAbmCc)
        ? route('editar_centrocosto', ['id' => (int) $centrocostoId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

@if ($layout === 'form_row')
    <div class="form-group row tm-centrocosto-campo mb-2" id="tm_centrocosto_{{ $prefix }}">
        <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} control-label text-right pr-2 {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
        <div class="{{ $colInput }}">
            <div class="d-flex flex-nowrap align-items-center tm-centrocosto-campo-inputs w-100" style="gap: 4px;">
                <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="centrocosto_id"
                    value="{{ $centrocostoId }}" @if ($required && ! $soloLectura) required @endif>
                @if ($soloLectura)
                    <input type="text" class="form-control codigocentrocosto"
                        id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                    <input type="text" class="form-control descripcioncentrocosto text-truncate"
                        id="{{ $inputId }}_descripcion" value="{{ $descripcion }}" readonly
                        style="min-width: 0; flex: 1 1 auto;">
                @else
                    <button type="button" title="Consulta centros de costo (F1)" class="btn-accion-tabla consultacentrocosto flex-shrink-0">
                        <i class="fa fa-search text-primary"></i>
                    </button>
                    @if ($mostrarEditar && $puedeAbrirAbmCc)
                        <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                            class="btn-accion-tabla btn-link-editar-centrocosto tooltipsC flex-shrink-0 {{ (int) $centrocostoId > 0 ? '' : 'd-none' }}"
                            title="Abrir centro de costo en ABM">
                            <i class="fa fa-edit"></i>
                        </a>
                    @endif
                    <input type="text" class="form-control codigocentrocosto"
                        id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                        placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem; flex-shrink: 0;">
                    <input type="text" class="form-control descripcioncentrocosto text-truncate"
                        id="{{ $inputId }}_descripcion" value="{{ $descripcion }}"
                        placeholder="Descripci&oacute;n" readonly
                        style="min-width: 0; flex: 1 1 auto;">
                @endif
            </div>
            @if ($ayuda)
                <small class="text-muted">{{ $ayuda }}</small>
            @endif
        </div>
    </div>
@else
    <div class="form-group col-12 mb-2 tm-centrocosto-campo" id="tm_centrocosto_{{ $prefix }}">
        <label class="d-block {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
        <div class="d-flex flex-nowrap align-items-center tm-centrocosto-campo-inputs w-100" style="gap: 6px;">
            <input type="hidden" class="centrocosto_id" id="{{ $inputId }}"
                name="{{ $inputName }}" value="{{ $centrocostoId }}"
                @if ($required && ! $soloLectura) required @endif>
            @if (! $soloLectura)
                <button type="button" title="Consulta centros de costo (F1)" class="btn btn-outline-secondary btn-sm consultacentrocosto flex-shrink-0">
                    <i class="fa fa-search"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbmCc)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn btn-outline-secondary btn-sm btn-link-editar-centrocosto flex-shrink-0 {{ (int) $centrocostoId > 0 ? '' : 'd-none' }}"
                        title="Abrir centro de costo en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigocentrocosto flex-shrink-0"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem;">
                <input type="text" class="form-control descripcioncentrocosto text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}"
                    placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
            @else
                <input type="text" class="form-control codigocentrocosto flex-shrink-0"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem;">
                <input type="text" class="form-control descripcioncentrocosto text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
        @if ($ayuda)
            <small class="text-muted">{{ $ayuda }}</small>
        @endif
    </div>
@endif
