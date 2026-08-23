@php
    $ctx = $ingresoContexto ?? [];
@endphp
<div id="ingreso-ticket-contexto"
     data-empresa-id="{{ $ctx['empresa_id'] ?? '' }}"
     data-proveedor-id="{{ $ctx['proveedor_id'] ?? '' }}"
     data-ordencompra-id="{{ $ctx['ordencompra_id'] ?? '' }}"
     data-url-form="{{ route('formulario_modal_ingreso_proveedor') }}"
     data-url-guardar="{{ route('guardar_ingreso_proveedor') }}"
     data-url-actualizar="{{ url('seguridad/ingreso-proveedor') }}"
     data-url-grilla="{{ route('grilla_vinculada_ingreso_proveedor') }}"
     hidden></div>

<div class="modal fade" id="ingresoProveedorModal" tabindex="-1" role="dialog" aria-labelledby="ingresoProveedorModalTitulo" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ingresoProveedorModalTitulo">Ticket de ingreso</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="ingresoProveedorModalBody">
                <p class="text-muted mb-0">Cargando…</p>
            </div>
        </div>
    </div>
</div>
