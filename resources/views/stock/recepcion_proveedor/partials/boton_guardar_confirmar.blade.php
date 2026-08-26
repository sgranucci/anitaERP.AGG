@php
    $mostrarGuardarConfirmar = can('confirmar-recepcion-proveedor', false)
        && ($validacionAbonoCompleta ?? true)
        && empty(optional($recepcion ?? null)->fl_precio_pendiente_aprobacion);
@endphp
@if ($mostrarGuardarConfirmar)
<button type="submit"
        class="btn {{ $claseBoton ?? 'btn-success mr-2 mb-2' }} js-guardar-confirmar-recepcion"
        form="form-recepcion-proveedor"
        name="accion"
        value="confirmar">
    <i class="fa fa-check"></i> Guardar y confirmar
</button>
@endif
