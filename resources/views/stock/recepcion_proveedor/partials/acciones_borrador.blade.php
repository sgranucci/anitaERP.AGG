@php
    $puedeGuardar = can('actualizar-recepcion-proveedor', false);
    $puedeConfirmar = can('confirmar-recepcion-proveedor', false);
    $puedeEliminar = can('borrar-recepcion-proveedor', false);
@endphp
@if($puedeGuardar || $puedeConfirmar || $puedeEliminar || can('listar-recepcion-proveedor', false))
<div class="{{ $claseContenedor ?? 'd-flex flex-wrap align-items-center' }}">
    @include('stock.recepcion_proveedor.partials.boton_imprimir_com_pdf', [
        'recepcionId' => $recepcion->id ?? null,
        'clase' => 'btn btn-outline-danger mr-2 mb-2',
    ])
    @if($puedeGuardar)
    <button type="submit" class="btn btn-primary mr-2 mb-2" form="form-recepcion-proveedor">
        <i class="fa fa-save"></i> Guardar
    </button>
    @include('stock.recepcion_proveedor.partials.boton_guardar_confirmar')
    @endif
    @if($puedeConfirmar && ($validacionAbonoCompleta ?? true))
    <button type="submit" class="btn btn-success mr-2 mb-2" form="form-recepcion-confirmar"
            id="btn-confirmar-recepcion-proveedor">
        <i class="fa fa-check"></i> Confirmar recepción
    </button>
    @elseif($puedeConfirmar)
    <button type="button" class="btn btn-success mr-2 mb-2" disabled title="Completá la validación de abono">
        <i class="fa fa-lock"></i> Confirmar (bloqueado)
    </button>
    @endif
    @if($puedeEliminar)
    <button type="submit" class="btn btn-outline-danger mb-2" form="form-recepcion-eliminar"
            onclick="return confirm('¿Eliminar este borrador? Esta acción no se puede deshacer.');">
        <i class="fa fa-trash"></i> Eliminar borrador
    </button>
    @endif
</div>
@endif
