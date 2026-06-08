<h5 class="mb-3">Historia de estados</h5>
@if (isset($recuento) && $recuento->estados->count())
    <table class="table table-bordered table-sm table-striped">
        <thead>
            <tr>
                <th>Fecha y hora</th>
                <th>Estado anterior</th>
                <th>Estado nuevo</th>
                <th>Usuario</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($recuento->estados as $hist)
                <tr>
                    <td>{{ optional($hist->ocurrio_el)->format('d/m/Y H:i') }}</td>
                    <td>{{ $hist->estado_anterior ? \App\Models\Stock\Recuento::etiquetaEstado($hist->estado_anterior) : '—' }}</td>
                    <td>{{ \App\Models\Stock\Recuento::etiquetaEstado($hist->estado_nuevo) }}</td>
                    <td>{{ optional($hist->usuarios)->nombre }}</td>
                    <td>{{ $hist->observaciones }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="text-muted mb-0">
        @if (isset($recuento))
            No hay registros de cambio de estado.
        @else
            La historia de estados estará disponible después de grabar el recuento.
        @endif
    </p>
@endif
