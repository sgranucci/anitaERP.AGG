<h4 class="mb-3">Historia de estados</h4>
<table class="table table-striped table-bordered">
    <thead style="background-color:#85C1E9;color:#17202A;">
        <tr>
            <th>Fecha</th>
            <th>Estado</th>
            <th>Usuario</th>
            <th>Observación</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data->comprobante_proveedor_estados ?? [] as $hist)
            <tr>
                <td>{{ $hist->fecha ? $hist->fecha->format('d/m/Y') : '' }}</td>
                <td>{{ $hist->estado }}</td>
                <td>{{ $hist->usuarios->nombre ?? '' }}</td>
                <td><small>{{ $hist->observacion }}</small></td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-muted">Sin registros de estado.</td></tr>
        @endforelse
    </tbody>
</table>
