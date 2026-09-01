@php
    $identidad = $resultado['identidad'] ?? [];
    $totales = $resultado['totales'] ?? [];
    $conciliacion = $resultado['conciliacion'] ?? [];
    $filas = $resultado['filas'] ?? [];
    $cuadra = ! empty($conciliacion['cuadra']);
    $bingoEscalonado = ! empty($identidad['bingo_escalonado']);
@endphp

<div class="card card-outline card-secondary">
    <div class="card-header">
        <h3 class="card-title">
            {{ $identidad['nombre'] ?? '' }}
            @if (! empty($identidad['codigo']))
                <span class="badge badge-info ml-1">{{ $identidad['codigo'] }}</span>
            @endif
        </h3>
        <span class="text-muted small ml-2">
            CUIT {{ $identidad['cuit_formato'] ?? '' }}
            · Máquinas {{ $identidad['etiqueta_maquinas'] ?? '' }}
            · Bingo {{ $identidad['etiqueta_bingo'] ?? '' }}
            · {{ $identidad['cuenta_etiqueta'] ?? '215010-003' }}
        </span>
        @if ($periodo_texto ?? '')
            <span class="text-muted small ml-2">· {{ $periodo_texto }}</span>
        @endif
    </div>
</div>

<div class="card card-outline {{ $cuadra ? 'card-success' : 'card-danger' }} mt-3" id="canon-entidades-conciliacion-panel">
    <div class="card-header">
        <h3 class="card-title">
            Conciliación vs pasivo 215010-003
            @if ($cuadra)
                <span class="badge badge-success ml-2">Conforme</span>
            @else
                <span class="badge badge-danger ml-2">Desvío</span>
            @endif
        </h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead style="background-color:#85C1E9;color:#17202A;">
                    <tr>
                        <th class="text-right">Canon máquinas</th>
                        <th class="text-right">Canon bingo</th>
                        <th class="text-right">Total calculado</th>
                        <th class="text-right">Σ Haber MAQ</th>
                        <th class="text-right">Σ Haber BIN</th>
                        <th class="text-right">Σ Haber mayor</th>
                        <th class="text-right">Diferencia</th>
                        <th class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="{{ $cuadra ? 'table-success' : 'table-danger' }}">
                        <td class="text-right">{{ number_format((float) ($totales['canon_maq'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($totales['canon_bin'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right font-weight-bold">{{ number_format((float) ($totales['canon_total'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($conciliacion['haber_maq'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($conciliacion['haber_bin'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($conciliacion['haber_total'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($conciliacion['diferencia'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-center">
                            @if ($cuadra)
                                <span class="badge badge-success">Conforme</span>
                            @else
                                <span class="badge badge-danger">Desvío</span>
                            @endif
                            <div class="small text-muted">tol. ≤ $ {{ number_format((float) ($conciliacion['tolerancia'] ?? 1), 2, ',', '.') }}</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2 small text-muted">
            {{ $conciliacion['aviso_criterio'] ?? '' }}
            @if (($conciliacion['fuente_mayor'] ?? '') !== '')
                Fuente mayor: {{ $conciliacion['fuente_mayor'] }}.
            @endif
            @if (($conciliacion['haber_otros'] ?? 0) != 0)
                Hay Haber de otros tipos ({{ number_format((float) $conciliacion['haber_otros'], 2, ',', '.') }}) que no entra en la comparación.
            @endif
            @if (($conciliacion['saldo_ejercicio'] ?? null) !== null)
                Control opcional saldo acumulado del ejercicio:
                {{ number_format((float) $conciliacion['saldo_ejercicio'], 2, ',', '.') }}
                (dif. {{ number_format((float) ($conciliacion['diferencia_saldo'] ?? 0), 2, ',', '.') }}).
            @endif
        </div>
    </div>
</div>

<div class="card card-outline card-secondary mt-3">
    <div class="card-header">
        <h3 class="card-title">
            Distribución día por día
            <span class="badge badge-info ml-2">{{ count($filas) }}</span>
        </h3>
        <span class="text-muted small ml-2">
            Flash {{ (int) ($totales['dias_con_flash'] ?? 0) }}/{{ (int) ($totales['dias_rango'] ?? 0) }}
            · Win ≤ 0 excluidos {{ (int) ($totales['dias_excluidos_maq'] ?? 0) }}
            (no restan)
        </span>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-sm table-striped mb-0" id="tabla-paginada">
            <thead style="background-color:#85C1E9;color:#17202A;">
                <tr>
                    <th>Fecha</th>
                    <th class="text-right">Win Electrónico</th>
                    <th class="text-right">Canon máquinas</th>
                    <th class="text-right">Ventas bingo</th>
                    @if ($bingoEscalonado)
                        <th class="text-right">Bingo 2%</th>
                        <th class="text-right">Bingo 3,25%</th>
                    @endif
                    <th class="text-right">Canon bingo</th>
                    <th class="text-right">Total día</th>
                    <th class="text-right">Σ Haber día</th>
                    <th class="text-right">Dif. día</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($filas as $fila)
                    <tr @class([
                        'table-warning' => ! empty($fila['excluido_maq']) && ! empty($fila['tiene_flash']),
                        'table-danger' => abs((float) ($fila['dif_dia'] ?? 0)) > 1,
                        'text-muted' => empty($fila['tiene_flash']),
                    ])>
                        <td>{{ $fila['fecha'] ?? '' }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['win_electronico'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['canon_maq'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['ventas_bingo'] ?? 0), 2, ',', '.') }}</td>
                        @if ($bingoEscalonado)
                            <td class="text-right">{{ number_format((float) ($fila['bingo_tramo_2'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($fila['bingo_tramo_325'] ?? 0), 2, ',', '.') }}</td>
                        @endif
                        <td class="text-right">{{ number_format((float) ($fila['canon_bin'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right font-weight-bold">{{ number_format((float) ($fila['canon_total'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['haber_total'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['dif_dia'] ?? 0), 2, ',', '.') }}</td>
                        <td class="small">
                            @if (empty($fila['tiene_flash']))
                                <span class="badge badge-light border">Sin flash</span>
                            @elseif (! empty($fila['excluido_maq']))
                                <span class="badge badge-warning">Win ≤ 0 · excluido</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $bingoEscalonado ? 11 : 9 }}" class="text-center text-muted py-4">
                            Sin días en el período.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($filas !== [])
                <tfoot>
                    <tr class="font-weight-bold">
                        <td>Totales (suma de días)</td>
                        <td class="text-right">{{ number_format((float) ($totales['base_maq'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($totales['canon_maq'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($totales['base_bingo'] ?? 0), 2, ',', '.') }}</td>
                        @if ($bingoEscalonado)
                            <td></td>
                            <td></td>
                        @endif
                        <td class="text-right">{{ number_format((float) ($totales['canon_bin'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($totales['canon_total'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($conciliacion['haber_total'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($conciliacion['diferencia'] ?? 0), 2, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@if (! empty($conciliacion['movimientos']))
    <div class="card card-outline card-warning mt-3">
        <div class="card-header">
            <h3 class="card-title">
                Movimientos Haber MAQ + BIN
                <span class="badge badge-secondary ml-2">{{ count($conciliacion['movimientos']) }}</span>
            </h3>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead style="background-color:#F9E79F;color:#17202A;">
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Asiento</th>
                        <th>Cuenta</th>
                        <th class="text-right">Haber</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($conciliacion['movimientos'] as $mov)
                        <tr>
                            <td>{{ ! empty($mov['fecha']) ? \Carbon\Carbon::parse($mov['fecha'])->format('d/m/Y') : '' }}</td>
                            <td>{{ $mov['tipo'] ?? '' }}</td>
                            <td>{{ $mov['asiento_id'] ?? '' }}</td>
                            <td class="small">{{ $mov['cuenta_codigo'] ?? '' }}</td>
                            <td class="text-right">{{ number_format((float) ($mov['haber'] ?? 0), 2, ',', '.') }}</td>
                            <td class="small text-muted">{{ $mov['detalle'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
