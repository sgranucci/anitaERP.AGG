@php
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceEstadosSupport;
    $estadosHist = $data->estados ?? collect();
@endphp
@if ($estadosHist->isEmpty())
    <p class="text-muted mb-0">Sin movimientos aún.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Usuario</th>
                    <th>Observación</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($estadosHist as $ev)
                <tr>
                    <td>{{ optional($ev->fecha)->format('d/m/Y H:i') }}</td>
                    <td>{{ CambioDevolucionMarketplaceEstadosSupport::etiqueta($ev->estado) }}</td>
                    <td>{{ $ev->usuario->nombre ?? $ev->usuario->usuario ?? '' }}</td>
                    <td>{{ $ev->observacion }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
