@php
    $prefix = $prefix ?? 'oc';
@endphp
<div class="form-group d-none js-oc-bloque-factura-legajo" id="{{ $prefix }}_bloque_factura_legajo">
    <label class="font-weight-bold">Factura del legajo (obligatoria para Cuentas a pagar)</label>
    <div class="alert alert-info py-2 mb-2 js-oc-gate-ok d-none">
        <i class="fa fa-check-circle"></i> Hay factura (precarga/PDF) asignada al legajo.
        <span class="js-oc-gate-com-ok"></span>
    </div>
    <div class="alert alert-warning py-2 mb-2 js-oc-gate-errores d-none"></div>
    <div class="js-oc-pdf-upload">
        <label for="{{ $prefix }}_factura_pdf">Adjuntar PDF de factura</label>
        <input type="file" name="factura_pdf" id="{{ $prefix }}_factura_pdf" class="form-control-file" accept="application/pdf,.pdf">
        <small class="form-text text-muted">Si no hay precarga con PDF, adjunte el PDF aquí para asignarlo al legajo.</small>
    </div>
</div>
