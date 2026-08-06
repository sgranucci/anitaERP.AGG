<div class="card-header" style="background:#85C1E9;color:#17202A;">
    <h3 class="card-title">
        {{ $proveedor->nombre }}
        <small class="ml-2">CUIT {{ $proveedor->nroinscripcion ?: 'sin informar' }}</small>
    </h3>
    <div class="card-tools">
        @if (($moduloActivo ?? '') === 'facturas')
            <span class="badge badge-secondary mr-1">Canal 1: PDF</span>
            @if (!empty(($canalMail['habilitado'] ?? false)))
                <span class="badge badge-success mr-2">Canal 2: Mail</span>
            @endif
            @if (!empty($pdfIaHabilitado) && can('cargar-portal-proveedores', false))
            <button type="button"
                    class="btn btn-success btn-sm"
                    data-toggle="modal"
                    data-target="#modal-precarga-pdf-ia">
                <i class="fa fa-upload"></i> Presentar factura PDF
            </button>
            @endif
        @elseif (($moduloActivo ?? '') === 'ordenes')
            <span class="badge badge-light text-dark">OC activas · facturas · pagos</span>
        @elseif (($moduloActivo ?? '') === 'pagos')
            <span class="badge badge-light text-dark">Órdenes de pago y certificados</span>
        @elseif (($moduloActivo ?? '') === 'retenciones')
            <span class="badge badge-light text-dark">Certificados G / IVA / SUSS / IIBB</span>
        @endif
    </div>
</div>
