@php
    $zonavtaId = $zonavtaId ?? '';
    $zonavtaCodigo = $zonavtaCodigo ?? '';
    $zonavtaNombre = $zonavtaNombre ?? '';
@endphp
<div class="tm-zonavta-campo">
    <input type="hidden" class="zonavta_id" name="zonavtas_id[]" value="{{ $zonavtaId }}">
    <div class="d-flex flex-nowrap align-items-center" style="gap: 2px;">
        <button type="button" title="Consulta zona de venta (F1)" class="btn-accion-tabla consultazonavta tooltipsC flex-shrink-0">
            <i class="fa fa-search text-primary"></i>
        </button>
        <input type="text" class="form-control form-control-sm codigozonavta flex-shrink-0"
            value="{{ $zonavtaCodigo }}" placeholder="Cód." autocomplete="off" style="width: 4rem;">
        <input type="text" class="form-control form-control-sm nombrezonavta"
            value="{{ $zonavtaNombre }}" placeholder="Zona" readonly style="min-width: 0; flex: 1 1 auto;">
    </div>
</div>
