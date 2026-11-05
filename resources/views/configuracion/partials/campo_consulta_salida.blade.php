{{--
    Campo impresora (salida): ID oculto + nombre editable + descripcion readonly + modal.
--}}
@php
    $prefix = $prefix ?? 'salida';
    $label = $label ?? 'Impresora';
    $salidaId = $salidaId ?? '';
    $nombre = $nombre ?? '';
    $descripcion = $descripcion ?? '';
    $inputName = $inputName ?? 'salida_id';
    $inputId = $inputId ?? 'salida_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? false;
    $mostrarEditar = $mostrar_editar ?? true;
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-7';
    $ayuda = $ayuda ?? '';
    $puedeAbrirAbm = can('editar-salidas', false) || can('listar-salidas', false);
    $editUrl = ((int) $salidaId > 0 && $puedeAbrirAbm)
        ? route('editar_salida', ['id' => (int) $salidaId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="form-group row tm-salida-campo" id="tm_salida_{{ $prefix }}">
    <label for="{{ $inputId }}_nombre" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="salida_id"
                value="{{ $salidaId !== '' && (int) $salidaId > 0 ? (int) $salidaId : '' }}"
                @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control nombresalida" id="{{ $inputId }}_nombre" value="{{ $nombre }}" readonly>
                <input type="text" class="form-control descripcionsalida text-truncate" id="{{ $inputId }}_descripcion" value="{{ $descripcion }}" readonly>
            @else
                <button type="button" title="Consulta impresoras (F1)" class="btn-accion-tabla consultasalida flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-salida tooltipsC flex-shrink-0 {{ (int) $salidaId > 0 ? '' : 'd-none' }}"
                        title="Abrir impresora en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control nombresalida"
                    id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                    placeholder="Nombre" title="Nombre; Enter avanza; F1 consulta" autocomplete="off"
                    style="min-width: 8rem; flex: 1 1 40%;"
                    data-nombre-resuelto="{{ $nombre }}">
                <input type="text" class="form-control descripcionsalida text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}"
                    placeholder="Ubicaci&oacute;n" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
        @if ($ayuda !== '')
            <small class="form-text text-muted">{{ $ayuda }}</small>
        @endif
    </div>
</div>
