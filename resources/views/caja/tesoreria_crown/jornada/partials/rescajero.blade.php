@php
    $pesos = function ($importe) {
        if ($importe === null || $importe === '' || abs((float) $importe) < 0.001) {
            return '';
        }

        return number_format((float) $importe, 2, ',', '.');
    };
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-users"></i> Resumen de cajeros</h3>
    </div>
    <div class="card-body pb-2">
        @include('caja.tesoreria_crown.jornada.partials.anexos_tabs')
        <p class="text-muted small mb-2">
            Formato de <code>l-rescajero.c</code>: agrupado por tipo de caja. Ingresos = efectivo rendido (sin fichas ni cashless).
           
        </p>
        @if ($rescajero === null)
            <div class="alert alert-warning mb-0">No se pudo armar el resumen. {{ $rescajeroError }}</div>
        @else
            @foreach ($rescajero['avisos'] as $aviso)
                <div class="alert alert-warning py-2">{{ $aviso }}</div>
            @endforeach
            <div class="tc-orden-wrap">
                @include('caja.tesoreria_crown.jornada.partials.anexo_orden_barra')
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 tc-tabla-ordenable" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Caja</th>
                                <th>Cajero</th>
                                <th class="text-right">Egresos</th>
                                <th class="text-right">Ingresos</th>
                                <th class="text-right">Fichas</th>
                                <th class="text-right">Pago cashless</th>
                                <th class="text-right">Vta. cashless</th>
                                <th class="text-right">Pago voucher</th>
                                <th class="text-right">Venta voucher</th>
                                <th class="text-right">Online</th>
                                <th class="text-right">Resultado</th>
                                <th class="text-right">Dif. cajero</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rescajero['grupos'] as $grupo)
                                <tr style="background:#D6EAF8;font-weight:bold;" data-tc-fija="1">
                                    <td colspan="12">{{ $grupo['titulo'] }}</td>
                                </tr>
                                @foreach ($grupo['filas'] as $fila)
                                    <tr>
                                        <td>{{ $fila['caja'] }}</td>
                                        <td>{{ $fila['cajero'] }}</td>
                                        <td class="text-right">{{ $pesos($fila['egresos']) }}</td>
                                        <td class="text-right">{{ $pesos($fila['ingresos']) }}</td>
                                        <td class="text-right">{{ $pesos($fila['fichas_casino']) }}</td>
                                        <td class="text-right">{{ $pesos($fila['pago_cashless']) }}</td>
                                        <td class="text-right">{{ $pesos($fila['vta_cashless']) }}</td>
                                        <td class="text-right">{{ $pesos($fila['pago_voucher']) }}</td>
                                        <td class="text-right">{{ $pesos($fila['venta_voucher']) }}</td>
                                        <td class="text-right">{{ $pesos($fila['online']) }}</td>
                                        <td class="text-right">{{ $pesos($fila['resultado']) }}</td>
                                        <td class="text-right">{{ $pesos($fila['diferencia']) }}</td>
                                    </tr>
                                @endforeach
                                <tr style="font-weight:bold;background:#f5f5f5;" data-tc-fija="1">
                                    <td colspan="2">Total {{ $grupo['titulo'] }}</td>
                                    <td class="text-right">{{ $pesos($grupo['totales']['egresos']) }}</td>
                                    <td class="text-right">{{ $pesos($grupo['totales']['ingresos']) }}</td>
                                    <td class="text-right">{{ $pesos($grupo['totales']['fichas_casino']) }}</td>
                                    <td class="text-right">{{ $pesos($grupo['totales']['pago_cashless']) }}</td>
                                    <td class="text-right">{{ $pesos($grupo['totales']['vta_cashless']) }}</td>
                                    <td class="text-right">{{ $pesos($grupo['totales']['pago_voucher']) }}</td>
                                    <td class="text-right">{{ $pesos($grupo['totales']['venta_voucher']) }}</td>
                                    <td class="text-right">{{ $pesos($grupo['totales']['online']) }}</td>
                                    <td class="text-right">{{ $pesos($grupo['totales']['resultado']) }}</td>
                                    <td class="text-right">{{ $pesos($grupo['totales']['diferencia']) }}</td>
                                </tr>
                            @empty
                                <tr data-tc-fija="1"><td colspan="12" class="text-center text-muted">Sin filas.</td></tr>
                            @endforelse
                            @if (($rescajero['grupos'] ?? []) !== [])
                                <tr style="font-weight:bold;background:#D5F5E3;" data-tc-fija="1">
                                    <td colspan="2">Total general</td>
                                    <td class="text-right">{{ $pesos($rescajero['totales']['egresos']) }}</td>
                                    <td class="text-right">{{ $pesos($rescajero['totales']['ingresos']) }}</td>
                                    <td class="text-right">{{ $pesos($rescajero['totales']['fichas_casino']) }}</td>
                                    <td class="text-right">{{ $pesos($rescajero['totales']['pago_cashless']) }}</td>
                                    <td class="text-right">{{ $pesos($rescajero['totales']['vta_cashless']) }}</td>
                                    <td class="text-right">{{ $pesos($rescajero['totales']['pago_voucher']) }}</td>
                                    <td class="text-right">{{ $pesos($rescajero['totales']['venta_voucher']) }}</td>
                                    <td class="text-right">{{ $pesos($rescajero['totales']['online']) }}</td>
                                    <td class="text-right">{{ $pesos($rescajero['totales']['resultado']) }}</td>
                                    <td class="text-right">{{ $pesos($rescajero['totales']['diferencia']) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
