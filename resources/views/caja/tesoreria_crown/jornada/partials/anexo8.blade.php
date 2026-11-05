@php
    $pesos = fn ($importe) => $importe === null || $importe === '' ? '' : number_format((float) $importe, 2, ',', '.');
    $cant = fn ($importe) => $importe === null || $importe === '' ? '' : number_format((float) $importe, 2, ',', '.');
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-university"></i> Anexo VIII — Estado de bóveda</h3>
    </div>
    <div class="card-body pb-2">
        @include('caja.tesoreria_crown.jornada.partials.anexos_tabs')
        <p class="text-muted small mb-2">
            Formato de <code>l-estboveda.c</code>: saldo de bóveda por denominación al cierre del día.
            <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=anexo8&amp;recalcular=1">Recalcular</a>
        </p>
        @if ($anexo8 === null)
            <div class="alert alert-warning mb-0">No se pudo armar el anexo VIII. {{ $anexo8Error }}</div>
        @else
            @foreach ($anexo8['avisos'] as $aviso)
                <div class="alert alert-warning py-2">{{ $aviso }}</div>
            @endforeach
            @forelse ($anexo8['lugares'] as $lugar)
                <h5 class="mt-2 mb-2">{{ $lugar['lugar'] }}</h5>
                @foreach ($lugar['grupos'] as $grupo)
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th colspan="3">{{ $grupo['titulo'] }}</th>
                                </tr>
                                <tr>
                                    <th>Denominación</th>
                                    <th class="text-right">Cantidad</th>
                                    <th class="text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grupo['filas'] as $fila)
                                    <tr>
                                        <td>{{ $fila['denominacion'] }}</td>
                                        <td class="text-right">{{ $cant($fila['cantidad']) }}</td>
                                        <td class="text-right">{{ $pesos($fila['monto']) }}</td>
                                    </tr>
                                @endforeach
                                <tr style="font-weight:bold;background:#f5f5f5;">
                                    <td>Subtotal {{ $grupo['titulo'] }}</td>
                                    <td></td>
                                    <td class="text-right">{{ $pesos($grupo['monto']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endforeach
                <p class="font-weight-bold">Total {{ $lugar['lugar'] }}: {{ $pesos($lugar['total']) }}</p>
            @empty
                <p class="text-muted mb-0">Sin lugares de bóveda.</p>
            @endforelse
            @if ($anexo8['lugares'] !== [])
                <p class="font-weight-bold mb-0">Total general: {{ $pesos($anexo8['total']) }}</p>
            @endif
        @endif
    </div>
</div>
