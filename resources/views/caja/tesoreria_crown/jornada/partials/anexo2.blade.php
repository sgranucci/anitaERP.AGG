@php
    $pesos = fn ($importe) => $importe === null || $importe === '' ? '' : number_format((float) $importe, 2, ',', '.');
    $pct = fn ($valor) => $valor === null ? '' : number_format((float) $valor, 2, ',', '.').' %';
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-table"></i> Anexo II — Resultado de mesas</h3>
    </div>
    <div class="card-body pb-2">
        @include('caja.tesoreria_crown.jornada.partials.anexos_tabs')
        <p class="text-muted small mb-2">
            Formato de <code>l-resmesa.c</code>: créditos, reposiciones, estado final, drop y resultado por mesa.
            <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=anexo2&amp;recalcular=1">Recalcular</a>
        </p>
        @if ($anexo2 === null)
            <div class="alert alert-warning mb-0">No se pudo armar el anexo II. {{ $anexo2Error }}</div>
        @else
            @foreach ($anexo2['avisos'] as $aviso)
                <div class="alert alert-warning py-2">{{ $aviso }}</div>
            @endforeach
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Juego</th>
                            <th>Mesa</th>
                            <th>Banca</th>
                            <th class="text-right">Créditos</th>
                            <th class="text-right">Reposiciones</th>
                            <th class="text-right">Estado final</th>
                            <th class="text-right">Drop</th>
                            <th class="text-right">Pagos man.</th>
                            <th class="text-right">Recomp. banca</th>
                            <th class="text-right">Caja empl.</th>
                            <th class="text-right">Resultado</th>
                            <th class="text-right">% Mesa</th>
                            <th class="text-right">% Prop.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($anexo2['filas'] as $fila)
                            <tr>
                                <td>{{ $fila['juego'] }}</td>
                                <td>{{ $fila['mesa'] }}</td>
                                <td>{{ $fila['banca'] }}</td>
                                <td class="text-right">{{ $pesos($fila['creditos']) }}</td>
                                <td class="text-right">{{ $pesos($fila['reposiciones']) }}</td>
                                <td class="text-right">{{ $pesos($fila['estado_final']) }}</td>
                                <td class="text-right">{{ $pesos($fila['drop']) }}</td>
                                <td class="text-right">{{ $pesos($fila['pagos_manuales']) }}</td>
                                <td class="text-right">{{ $pesos($fila['recomp_banca']) }}</td>
                                <td class="text-right">{{ $pesos($fila['caja_empleado']) }}</td>
                                <td class="text-right">{{ $pesos($fila['resultado']) }}</td>
                                <td class="text-right">{{ $pct($fila['pct_mesa']) }}</td>
                                <td class="text-right">{{ $pct($fila['pct_prop']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="text-center text-muted">Sin filas.</td></tr>
                        @endforelse
                        @if ($anexo2['filas'] !== [])
                            <tr style="font-weight:bold;background:#f5f5f5;">
                                <td colspan="3">Total</td>
                                <td class="text-right">{{ $pesos($anexo2['totales']['creditos']) }}</td>
                                <td class="text-right">{{ $pesos($anexo2['totales']['reposiciones']) }}</td>
                                <td class="text-right">{{ $pesos($anexo2['totales']['estado_final']) }}</td>
                                <td class="text-right">{{ $pesos($anexo2['totales']['drop']) }}</td>
                                <td class="text-right">{{ $pesos($anexo2['totales']['pagos_manuales']) }}</td>
                                <td class="text-right">{{ $pesos($anexo2['totales']['recomp_banca']) }}</td>
                                <td class="text-right">{{ $pesos($anexo2['totales']['caja_empleado']) }}</td>
                                <td class="text-right">{{ $pesos($anexo2['totales']['resultado']) }}</td>
                                <td></td><td></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
