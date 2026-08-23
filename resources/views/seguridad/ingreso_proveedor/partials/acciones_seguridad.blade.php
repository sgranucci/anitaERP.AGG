@php
    use App\Support\Seguridad\IngresoProveedorEstados;
    $ticket = $ticket ?? $data ?? null;
    $puedeAutorizar = $puedeAutorizarSeguridad ?? can('autorizar-ingreso-proveedor', false);
    $estadoTicket = (string) ($ticket->estado ?? '');
    $mostrarGate = $ticket && $ticket->id && $puedeAutorizar
        && IngresoProveedorEstados::puedeAutorizarORechazar($estadoTicket);
@endphp
@if ($ticket && $ticket->id)
    @if ($estadoTicket === IngresoProveedorEstados::AUTORIZADO && ($ticket->autorizado_at || $ticket->usuarioAutorizo))
        <div class="alert alert-success py-2 mb-3">
            Autorizado
            @if ($ticket->usuarioAutorizo)
                por {{ $ticket->usuarioAutorizo->nombre }}
            @endif
            @if ($ticket->autorizado_at)
                el {{ $ticket->autorizado_at->format('d/m/Y H:i') }}
            @endif
            . Portería ya puede registrar el ingreso.
        </div>
    @elseif ($estadoTicket === IngresoProveedorEstados::RECHAZADO)
        <div class="alert alert-danger py-2 mb-3">
            Ticket rechazado.
            @if ($ticket->usuarioAutorizo)
                Por {{ $ticket->usuarioAutorizo->nombre }}.
            @endif
        </div>
    @endif
    @if ($mostrarGate)
        <div class="ingreso-acciones-seguridad mb-3" data-ticket-id="{{ $ticket->id }}">
            <form action="{{ route('autorizar_ingreso_proveedor', $ticket->id) }}" method="POST" class="d-inline js-ingreso-autorizar-form">
                @csrf
                @if (!empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="fa fa-check"></i> Autorizar
                </button>
            </form>
            <button type="button" class="btn btn-outline-danger btn-sm js-ingreso-abrir-rechazo"
                    data-ticket-id="{{ $ticket->id }}"
                    data-url="{{ route('rechazar_ingreso_proveedor', $ticket->id) }}">
                <i class="fa fa-ban"></i> Rechazar
            </button>
        </div>
    @endif
@endif
