@php
    $puedeGuardar = can('actualizar-requisicion', false);
    $puedeConfirmar = !empty($puede_confirmar_provisorio);
    $puedeEliminar = can('actualizar-requisicion', false) && empty($tiene_ordencompra_asociada);
@endphp
@if($puedeGuardar || $puedeConfirmar || $puedeEliminar)
<div class="{{ $claseContenedor ?? 'd-flex flex-wrap align-items-center gap-2' }}">
    @if($puedeGuardar)
    <button type="button" id="botonform0" class="btn btn-primary">
        <i class="fa fa-save"></i> Guardar provisorio
    </button>
    @endif
    @if($puedeConfirmar)
    <button type="submit" class="btn btn-success" form="form-requisicion-confirmar"
            id="btn-confirmar-requisicion-provisorio">
        <i class="fa fa-check"></i> Confirmar requisición
    </button>
    @endif
    @if($puedeEliminar)
    <button type="submit" class="btn btn-outline-danger" form="form-requisicion-eliminar-provisorio"
            onclick="return confirm('¿Eliminar este provisorio? Esta acción no se puede deshacer.');">
        <i class="fa fa-trash"></i> Eliminar provisorio
    </button>
    @endif
</div>
@endif
