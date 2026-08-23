@php
    use App\Support\Seguridad\IngresoProveedorEstados;
    $tickets = $tickets ?? collect();
@endphp
<div class="table-responsive ingreso-solapa-grilla">
    <table class="table table-sm table-bordered table-hover mb-0">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Empresa</th>
                <th>Proveedor / Visitante</th>
                <th>OC</th>
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
                        {{ \App\Support\Seguridad\IngresoProveedorVisitanteSupport::etiquetaOrigen($ticket) }}
                        @if (\App\Support\Seguridad\IngresoProveedorVisitanteSupport::esVisitante($ticket))
                            <span class="badge badge-secondary">Visitante</span>
                        @endif
                    </td>
                    <td>{{ $ticket->ordencompras->numeroordencompra ?? '' }}</td>
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
                            <button type="button" class="btn btn-link btn-sm p-0 text-primary js-ingreso-ticket-ver" data-id="{{ $ticket->id }}">Ver</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                        <td colspan="12" class="text-center text-muted py-4">No hay tickets de ingreso todavía.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
