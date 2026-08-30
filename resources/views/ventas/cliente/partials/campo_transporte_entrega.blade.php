@php
    $transporteId = $transporteId ?? '';
    $transporteCodigo = $transporteCodigo ?? '';
    $transporteNombre = $transporteNombre ?? '';
@endphp
<div class="tm-transporte-campo">
    <input type="hidden" class="transporte_id" name="transportes_id[]" value="{{ $transporteId }}">
    <div class="d-flex flex-nowrap align-items-center" style="gap: 2px;">
        <button type="button" title="Consulta repartos (F1)" class="btn-accion-tabla consultatransporte tooltipsC flex-shrink-0">
            <i class="fa fa-search text-primary"></i>
        </button>
        <input type="text" class="form-control form-control-sm codigotransporte flex-shrink-0"
            value="{{ $transporteCodigo }}" placeholder="Cód." autocomplete="off" style="width: 4rem;">
        <input type="text" class="form-control form-control-sm nombretransporte"
            value="{{ $transporteNombre }}" placeholder="Reparto" readonly style="min-width: 0; flex: 1 1 auto;">
    </div>
</div>
