{{--
    Campo cuenta de caja: ID oculto + codigo + nombre + modal consulta (+ enlace ABM opcional).

    Variables:
    - $prefix (default cuentacaja)
    - $cuentacajaId, $codigo, $nombre
    - $label, $inputName (default cuentacaja_id), $inputId
    - $solo_lectura, $required (default false), $mostrar_editar (default true)
    - $layout: form_row | inline
    - $col_label, $col_input (solo form_row)
    - $ayuda (texto small opcional)
--}}
@php
    $prefix = $prefix ?? 'cuentacaja';
    $label = $label ?? 'Cuenta de caja';
    $cuentacajaId = $cuentacajaId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $inputName = $inputName ?? 'cuentacaja_id';
    $inputId = $inputId ?? 'cuentacaja_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? false;
    $mostrarEditar = $mostrar_editar ?? true;
    $layout = $layout ?? 'form_row';
    $colLabel = $col_label ?? 'col-lg-3';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-cuentas-de-caja', false) || can('listar-cuentas-de-caja', false);
    $editUrl = ((int) $cuentacajaId > 0 && $puedeAbrirAbm)
        ? route('editar_cuentacaja', ['id' => (int) $cuentacajaId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

@if ($layout === 'form_row')
<div class="form-group row tm-cuentacaja-campo" id="tm_cuentacaja_{{ $prefix }}">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} col-form-label {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center tm-cuentacaja-campo-inputs w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="cuentacaja_id"
                value="{{ $cuentacajaId }}" @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control codigocuentacaja"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control descripcioncuentacaja text-truncate"
                    id="{{ $inputId }}_nombre" value="{{ $nombre }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta cuentas de caja (F1)" class="btn-accion-tabla consultacuentacaja flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-cuentacaja tooltipsC flex-shrink-0 {{ (int) $cuentacajaId > 0 ? '' : 'd-none' }}"
                        title="Abrir cuenta de caja en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigocuentacaja"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off"
                    style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control descripcioncuentacaja text-truncate"
                    id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                    placeholder="Descripci&oacute;n" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
        @if (! empty($ayuda))
            <small class="form-text text-muted">{{ $ayuda }}</small>
        @endif
    </div>
</div>
@else
{{-- inline / compact (tablas): sin label si $label === '' --}}
<div class="tm-cuentacaja-campo d-flex flex-nowrap align-items-center w-100 {{ trim((string) $label) !== '' ? 'form-group col-12 mb-2 flex-column align-items-stretch' : '' }}"
    id="tm_cuentacaja_{{ $prefix }}" style="gap: 4px;">
    @if (trim((string) $label) !== '')
        <label class="d-block mb-1 {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    @endif
    <div class="d-flex flex-nowrap align-items-center tm-cuentacaja-campo-inputs w-100" style="gap: 4px;">
        <input type="hidden" class="cuentacaja_id" id="{{ $inputId }}"
            name="{{ $inputName }}" value="{{ $cuentacajaId }}"
            @if ($required && ! $soloLectura) required @endif>
        @if (! $soloLectura)
            <button type="button" title="Consulta cuentas de caja (F1)" class="btn-accion-tabla consultacuentacaja flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($mostrarEditar && $puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                    class="btn-accion-tabla btn-link-editar-cuentacaja flex-shrink-0 {{ (int) $cuentacajaId > 0 ? '' : 'd-none' }}"
                    title="Abrir cuenta de caja en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control form-control-sm codigocuentacaja flex-shrink-0"
                id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off"
                style="width: 5.5rem;">
            <input type="text" class="form-control form-control-sm descripcioncuentacaja text-truncate"
                id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
        @else
            <input type="text" class="form-control form-control-sm codigocuentacaja flex-shrink-0"
                value="{{ $codigo }}" readonly style="width: 5.5rem;">
            <input type="text" class="form-control form-control-sm descripcioncuentacaja text-truncate"
                value="{{ $nombre }}" readonly style="min-width: 0; flex: 1 1 auto;">
        @endif
    </div>
    @if (! empty($ayuda))
        <small class="form-text text-muted">{{ $ayuda }}</small>
    @endif
</div>
@endif
