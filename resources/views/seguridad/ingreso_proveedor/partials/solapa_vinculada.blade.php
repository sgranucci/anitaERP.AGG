@php
    use App\Support\Seguridad\IngresoProveedorEstados;
    $tickets = $tickets ?? collect();
    $puedeCrear = can('crear-ingreso-proveedor', false);
    $urlNuevo = $urlNuevo ?? null;
@endphp
<div class="ingreso-solapa-vinculo">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-1"><i class="fa fa-id-badge text-info"></i> Tickets de ingreso</h5>
            <p class="text-muted small mb-0">Visitas solicitadas y movimientos de planta vinculados a este documento.</p>
        </div>
        @if ($puedeCrear && $urlNuevo)
            <a href="{{ $urlNuevo }}" class="btn btn-primary btn-sm" target="_blank" rel="noopener" title="Abre el alta del ticket en una solapa nueva, sin menú">
                <i class="fa fa-plus"></i> Solicitar ticket de ingreso
            </a>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Empresa</th>
                    <th>Personas / DNI</th>
                    <th>Motivo</th>
                    <th>Punto</th>
                    <th>Estado</th>
                    <th>Ingreso</th>
                    <th>Egreso</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr>
                        <td>{{ $ticket->id }}</td>
                        <td>{{ optional($ticket->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $ticket->empresas->nombre ?? '' }}</td>
                        <td>
                            @foreach ($ticket->personas as $persona)
                                <div>{{ $persona->nombre }} <small class="text-muted">{{ $persona->documento }}</small></div>
                            @endforeach
                        </td>
                        <td>{{ $ticket->motivos->nombre ?? '' }}</td>
                        <td>{{ $ticket->puntos->nombre ?? '' }}</td>
                        <td>
                            <span class="badge badge-{{ IngresoProveedorEstados::badge((string) $ticket->estado) }}">
                                {{ IngresoProveedorEstados::etiqueta((string) $ticket->estado) }}
                            </span>
                        </td>
                        <td>{{ $ticket->hora_ingreso ? substr((string) $ticket->hora_ingreso, 0, 5) : '' }}</td>
                        <td>{{ $ticket->hora_egreso ? substr((string) $ticket->hora_egreso, 0, 5) : '' }}</td>
                        <td>
                            @if (can('editar-ingreso-proveedor', false))
                                <a href="{{ route('editar_ingreso_proveedor', $ticket->id) }}" class="text-primary" target="_blank" rel="noopener">Ver</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No hay tickets de ingreso todavía.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
