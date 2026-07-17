{{--
    Campo tipo transaccion stock: ID oculto + abreviatura + nombre + modal consulta (+ enlace ABM opcional).
--}}
@php
    $prefix = $prefix ?? 'movimientostock';
    $label = $label ?? 'Tipo de transacci&oacute;n';
    $tipoId = (int) ($tipoId ?? 0);
    $abreviatura = $abreviatura ?? '';
    $nombre = $nombre ?? '';
    $inputName = $inputName ?? 'tipotransaccion_stock_id';
    $inputId = $inputId ?? 'tipotransaccion_stock_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? true;
    $mostrarEditar = $mostrar_editar ?? true;
    $layout = $layout ?? 'form_row';
    $colLabel = $col_label ?? 'col-lg-4 col-form-label';
    $colInput = $col_input ?? 'col-lg-8';
    $operacion = $operacion ?? '';
    $manejaContabilidad = (bool) ($maneja_contabilidad ?? false);
    $origenBienUso = (bool) ($origen_bien_uso ?? false);
    $destinoBienUso = (bool) ($destino_bien_uso ?? false);
    $requiereAprobacion = (bool) ($requiere_aprobacion ?? false);
    $avisoOpcional = (bool) ($aviso_opcional ?? false);
    $bajaNpu = (bool) ($baja_npu ?? false);
    $puedeAbrirAbm = can('editar-tipos-transaccion-stock', false) || can('listar-tipos-transaccion-stock', false);
    $editUrl = ($tipoId > 0 && $puedeAbrirAbm)
        ? route('editar_tipotransaccion_stock', ['id' => $tipoId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

@if ($layout === 'form_row')
<div class="form-group row mb-2 tm-tipotransaccion-stock-campo" id="tm_tipotransaccion_{{ $prefix }}">
    <label for="{{ $inputId }}_abreviatura" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{!! $label !!}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center tm-tipotransaccion-stock-campo-inputs w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="tipotransaccion_stock_id"
                value="{{ $tipoId > 0 ? $tipoId : '' }}"
                data-operacion="{{ $operacion }}"
                data-maneja-contabilidad="{{ $manejaContabilidad ? '1' : '0' }}"
                data-origen-bien-uso="{{ $origenBienUso ? '1' : '0' }}"
                data-destino-bien-uso="{{ $destinoBienUso ? '1' : '0' }}"
                data-requiere-aprobacion="{{ $requiereAprobacion ? '1' : '0' }}"
                data-aviso-opcional="{{ $avisoOpcional ? '1' : '0' }}"
                data-baja-npu="{{ $bajaNpu ? '1' : '0' }}"
                @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control abreviaturatipotransaccionstock"
                    id="{{ $inputId }}_abreviatura" value="{{ $abreviatura }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombretipotransaccionstock text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $nombre }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta tipos de transacci&oacute;n (F1)" class="btn-accion-tabla consultatipotransaccionstock flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-tipotransaccion-stock tooltipsC flex-shrink-0 {{ $tipoId > 0 ? '' : 'd-none' }}"
                        title="Abrir tipo de transacci&oacute;n en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control abreviaturatipotransaccionstock"
                    id="{{ $inputId }}_abreviatura" value="{{ $abreviatura }}"
                    placeholder="Abrev." title="Abreviatura; Enter valida; F1 consulta" autocomplete="off" style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombretipotransaccionstock text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $nombre }}"
                    placeholder="Descripci&oacute;n" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
    </div>
</div>
@endif
