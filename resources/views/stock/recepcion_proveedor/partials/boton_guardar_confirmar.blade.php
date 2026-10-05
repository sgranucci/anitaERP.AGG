@php
    $puedeConfirmarRecepcion = can('confirmar-recepcion-proveedor', false);
    $abonoListoParaConfirmar = $validacionAbonoCompleta ?? true;
@endphp
@if ($puedeConfirmarRecepcion && $abonoListoParaConfirmar)
<button type="submit"
        class="btn {{ $claseBoton ?? 'btn-success mr-2 mb-2' }} js-guardar-confirmar-recepcion"
        form="form-recepcion-proveedor"
        name="accion"
        value="confirmar">
    <i class="fa fa-check"></i> Guardar y confirmar
</button>
@elseif ($puedeConfirmarRecepcion)
<button type="button"
        class="btn {{ $claseBoton ?? 'btn-success mr-2 mb-2' }}"
        disabled
        title="Completá la validación de abono">
    <i class="fa fa-lock"></i> Guardar y confirmar
</button>
@endif
