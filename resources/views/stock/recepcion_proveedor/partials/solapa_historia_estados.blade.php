@php
    $historia = ($recepcion ?? null)
        ? $recepcion->recepcion_proveedor_estados->sortBy('fecha')
        : collect();
@endphp
<h5 class="mb-3">Historia de estados</h5>
<div class="table-responsive">
    <table class="table table-bordered table-sm" id="tabla-historia-estados-recepcion">
        <thead class="thead-light">
            <tr>
                <th>Fecha y hora</th>
                <th>Estado</th>
                <th>Observación</th>
                <th>Usuario</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($historia as $h)
            <tr>
                <td>{{ $h->fecha ? $h->fecha->format('d/m/Y H:i') : '—' }}</td>
                <td>{{ $h->estado }}</td>
                <td>{{ $h->observacion ?: '—' }}</td>
                <td>{{ optional($h->usuarios)->nombre ?? '—' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="text-center text-muted">Sin movimientos de estado registrados.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
