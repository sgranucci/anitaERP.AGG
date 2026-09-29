{{--
    Campo tipo transaccion ventas: ID oculto + abreviatura + nombre + modal consulta.
--}}
@php
    $prefix = $prefix ?? 'tipotransaccion';
    $label = $label ?? 'Tipo de transacci&oacute;n';
    $tipoId = (int) ($tipoId ?? 0);
    $abreviatura = $abreviatura ?? '';
    $nombre = $nombre ?? '';
    $inputName = $inputName ?? 'tipotransaccion_id';
    $inputId = $inputId ?? 'tipotransaccion_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? false;
    $mostrarEditar = $mostrar_editar ?? true;
    $layout = $layout ?? 'form_row';
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-tipos-transacciones', false) || can('listar-tipos-transacciones', false);
    $editUrl = ($tipoId > 0 && $puedeAbrirAbm)
        ? route('editar_tipotransaccion', ['id' => $tipoId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
    $codigoAfip = (string) ($codigo ?? '');
    $operacionTipo = (string) ($operacion ?? '');
    $conceptoVentaId = (string) ($concepto_venta_id ?? '');
    $conceptoCodigo = (string) ($concepto_codigo ?? '');
    $conceptoNombre = (string) ($concepto_nombre ?? '');
    $conceptoDescripcion = (string) ($concepto_descripcion ?? '');
    $conceptoImpuestoId = (string) ($concepto_impuesto_id ?? '');
    $avisoId = $aviso_id ?? '';
@endphp

@if ($layout === 'form_row')
<div class="form-group row mb-2 tm-tipotransaccion-venta-campo" id="tm_tipotransaccion_{{ $prefix }}">
    <label for="{{ $inputId }}_abreviatura" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{!! $label !!}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center tm-tipotransaccion-venta-campo-inputs w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="tipotransaccion_venta_id"
                value="{{ $tipoId > 0 ? $tipoId : '' }}"
                data-abreviatura="{{ $abreviatura }}"
                data-codigo="{{ $codigoAfip }}"
                data-operacion="{{ $operacionTipo }}"
                data-usa-concepto="{{ (int) $conceptoVentaId > 0 ? '1' : '0' }}"
                data-concepto-venta-id="{{ $conceptoVentaId }}"
                data-concepto-codigo="{{ $conceptoCodigo }}"
                data-concepto-nombre="{{ $conceptoNombre }}"
                data-concepto-descripcion="{{ $conceptoDescripcion }}"
                data-concepto-impuesto-id="{{ $conceptoImpuestoId }}"
                @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control abreviaturatipotransaccionventa"
                    id="{{ $inputId }}_abreviatura" value="{{ $abreviatura }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombretipotransaccionventa text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $nombre }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta tipos de transacci&oacute;n (F1)" class="btn-accion-tabla consultatipotransaccionventa flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-tipotransaccion-venta tooltipsC flex-shrink-0 {{ $tipoId > 0 ? '' : 'd-none' }}"
                        title="Abrir tipo de transacci&oacute;n en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control abreviaturatipotransaccionventa"
                    id="{{ $inputId }}_abreviatura" value="{{ $abreviatura }}"
                    placeholder="Abrev." title="Abreviatura; Enter valida; F1 consulta" autocomplete="off" style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombretipotransaccionventa text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $nombre }}"
                    placeholder="Descripci&oacute;n" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
        @if (! empty($ayuda))
            <small class="form-text text-muted">{{ $ayuda }}</small>
        @endif
        @if ($avisoId !== '')
            <small id="{{ $avisoId }}" class="form-text text-info d-none"></small>
        @endif
    </div>
</div>
@endif
