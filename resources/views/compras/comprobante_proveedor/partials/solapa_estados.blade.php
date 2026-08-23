@if (($data->anita_sync_estado ?? '') === \App\Support\Compras\ComprobanteProveedorAnitaSyncEstado::ERROR
    && filled($data->anita_sync_error ?? null))
<div class="alert alert-danger mb-3">
    <strong>Último error Anita</strong>
    <div>{{ $data->anita_sync_error }}</div>
    @if (filled($data->anita_sync_at ?? null))
    <small>{{ \Illuminate\Support\Carbon::parse($data->anita_sync_at)->format('d/m/Y H:i') }}</small>
    @endif
</div>
@endif
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
