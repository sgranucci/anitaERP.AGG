@php
    $tickets = $tickets ?? collect();
    $puedeCrear = can('crear-ingreso-proveedor', false);
    $contexto = $ingresoContexto ?? [];
@endphp
<div class="ingreso-solapa-vinculo"
     data-empresa-id="{{ $contexto['empresa_id'] ?? '' }}"
     data-proveedor-id="{{ $contexto['proveedor_id'] ?? '' }}"
     data-ordencompra-id="{{ $contexto['ordencompra_id'] ?? '' }}">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-1"><i class="fa fa-id-badge text-info"></i> Tickets de ingreso</h5>
            <p class="text-muted small mb-0">Visitas solicitadas y movimientos de planta vinculados a este documento.</p>
        </div>
        @if ($puedeCrear && ! empty($url_nuevo_ticket_ingreso))
            <button type="button" class="btn btn-primary btn-sm js-ingreso-ticket-nuevo">
                <i class="fa fa-plus"></i> Solicitar ticket de ingreso
            </button>
        @elseif ($puedeCrear)
            <span class="small text-muted">Solo se pueden solicitar personas si el contrato está activo.</span>
        @endif
    </div>

    @include('seguridad.ingreso_proveedor.partials.solapa_vinculada_grilla', ['tickets' => $tickets])
</div>
