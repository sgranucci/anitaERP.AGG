<dl class="row mb-3">
    <dt class="col-sm-2">Número</dt>
    <dd class="col-sm-4">{{ $data->numeroasiento }}</dd>
    <dt class="col-sm-2">Fecha</dt>
    <dd class="col-sm-4">{{ optional($data->fecha)->format('d/m/Y') ?? $data->fecha }}</dd>
    <dt class="col-sm-2">Empresa</dt>
    <dd class="col-sm-4">{{ optional($data->empresas)->nombre }}</dd>
    <dt class="col-sm-2">Tipo</dt>
    <dd class="col-sm-4">{{ optional($data->tipoasientos)->nombre }}</dd>
    <dt class="col-sm-2">Usuario</dt>
    <dd class="col-sm-4">{{ optional($data->usuarios)->nombre }}</dd>
    <dt class="col-sm-2">Observación</dt>
    <dd class="col-sm-10">{{ $data->observacion }}</dd>
</dl>

@php
    $cuentasPendientes = \App\Support\Contable\AsientoCuentaUsuarioSupport::detalleCuentas($data->cuentasNoAutorizadasIds());
@endphp
@if ($cuentasPendientes !== [])
    <div class="alert alert-warning py-2">
        <strong>Cuentas fuera de la lista del usuario:</strong>
        <ul class="mb-0">
            @foreach ($cuentasPendientes as $cuenta)
                <li>{{ $cuenta['codigo'] }} — {{ $cuenta['nombre'] }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="table-responsive">
    <table class="table table-bordered table-sm">
        <thead style="background:#85C1E9; color:#17202A;">
            <tr>
                <th>Cuenta</th>
                <th>C.C.</th>
                <th>Moneda</th>
                <th class="text-right">Debe</th>
                <th class="text-right">Haber</th>
                <th>Obs.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data->asiento_movimientos as $mov)
                @php $monto = (float) ($mov->monto ?? 0); @endphp
                <tr>
                    <td>{{ optional($mov->cuentacontables)->codigo }} {{ optional($mov->cuentacontables)->nombre }}</td>
                    <td>{{ optional($mov->centrocostos)->codigo }}</td>
                    <td>{{ optional($mov->monedas)->abreviatura }}</td>
                    <td class="text-right">{{ $monto > 0 ? number_format($monto, 2, ',', '.') : '' }}</td>
                    <td class="text-right">{{ $monto < 0 ? number_format(abs($monto), 2, ',', '.') : '' }}</td>
                    <td>{{ $mov->observacion }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
