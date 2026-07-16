@php
    $estados = ($data->pagoproveedor_estados ?? collect());
@endphp
<div class="card form5" style="display: none">
    <h3>Historia</h3>
    <div class="card-body">
        <table class="table table-sm" id="pagoproveedor-historia-table">
            <thead>
                <tr>
                    <th style="width: 18%;">Fecha</th>
                    <th>Estado</th>
                    <th>Usuario</th>
                    <th>Observación</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($estados as $est)
                    <tr>
                        <td>{{ optional($est->fecha)->format('d/m/Y H:i') ?? $est->fecha }}</td>
                        <td>{{ $est->estado }}</td>
                        <td>{{ $est->usuarios->nombre ?? '' }}</td>
                        <td>{{ $est->observacion }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Sin historial</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
