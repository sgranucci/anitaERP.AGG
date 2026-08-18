@php
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $tipoSelector = $tipoSelector ?? '#tipo';
@endphp

<div class="form-group row tm-asociado-reporte-campo d-none"
     data-asociado-reporte-campo="1" data-tipo-selector="{{ $tipoSelector }}">
    <label for="asociado_codigo" class="{{ $colLabel }}">
        <span class="etiqueta-asociado-reporte">Asociado</span>
    </label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap:4px;">
            <button type="button" class="btn-accion-tabla consultaasociado_reporte flex-shrink-0"
                    title="Consultar obra social o sindicato (F1)">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" name="asociado_codigo" id="asociado_codigo"
                   class="form-control codigoasociado_reporte"
                   value="{{ $codigo }}" min="0" inputmode="numeric"
                   placeholder="C&oacute;d." autocomplete="off"
                   title="C&oacute;digo + Enter para validar; F1 o lupa para buscar"
                   style="width:6rem;flex-shrink:0;">
            <input type="text" class="form-control descripcionasociado_reporte text-truncate"
                   value="{{ $descripcion }}" placeholder="Descripci&oacute;n" readonly
                   style="min-width:0;flex:1 1 auto;">
        </div>
        <small class="form-text text-muted ayuda-asociado-reporte">
            Filtra el reporte por el c&oacute;digo seleccionado.
        </small>
    </div>
</div>
