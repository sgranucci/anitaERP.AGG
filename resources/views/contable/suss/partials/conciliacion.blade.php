@if (! empty($conciliacion['habilitada']))
    <div class="card card-outline card-warning mt-3" id="suss-conciliacion-panel">
        <div class="card-header">
            <h3 class="card-title">Conciliación SUSS vs mayor contable</h3>
            @if ($periodo_texto ?? '')
                <span class="text-muted small ml-2">{{ $periodo_texto }}</span>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Cód.</th>
                            <th>Concepto</th>
                            <th>Cuentas</th>
                            <th class="text-right">Total SUSS</th>
                            <th class="text-right">Total mayor</th>
                            <th class="text-right">Diferencia</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($conciliacion['items'] ?? [] as $item)
                            @php
                                $totalSuss = $item['total_suss'] ?? $item['total_sicore'] ?? 0;
                            @endphp
                            <tr @class([
                                'table-success' => ! empty($item['cuadra']),
                                'table-danger' => empty($item['cuadra']) && ($item['registros'] ?? 0) > 0,
                            ])>
                                <td>{{ $item['codigo_impuesto'] ?? '' }}</td>
                                <td>{{ $item['nombre'] ?? '' }}</td>
                                <td class="small">
                                    @forelse ($item['cuentas'] ?? [] as $cuenta)
                                        <div>{{ $cuenta['codigo'] ?? '' }} — {{ $cuenta['nombre'] ?? '' }}</div>
                                    @empty
                                        <span class="text-warning">Sin cuentas configuradas</span>
                                    @endforelse
                                </td>
                                <td class="text-right">
                                    {{ number_format((float) $totalSuss, 2, ',', '.') }}
                                </td>
                                <td class="text-right @if (($item['total_mayor'] ?? 0) < 0) text-danger @endif">
                                    {{ number_format((float) ($item['total_mayor'] ?? 0), 2, ',', '.') }}
                                </td>
                                <td class="text-right">{{ number_format((float) ($item['diferencia'] ?? 0), 2, ',', '.') }}</td>
                                <td class="text-center">
                                    @if (! empty($item['cuadra']))
                                        <span class="badge badge-success">OK</span>
                                    @elseif (($item['registros'] ?? 0) === 0 && ($item['total_mayor'] ?? 0) == 0)
                                        <span class="badge badge-secondary">Sin mov.</span>
                                    @else
                                        <span class="badge badge-danger">Diferencia</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="small text-muted px-3 py-2 mb-0 border-top">
                Total SUSS: suma de retenciones del período consultado (1ra quincena, 2da o mes).
                Total mayor: saldo acumulado (columna P del mayor plano) al último movimiento de la quincena/mes.
                Diferencia: SUSS − mayor. Si |diferencia| ≤ $ {{ number_format((float) ($conciliacion['tolerancia'] ?? 100), 2, ',', '.') }} → OK.
            </p>
        </div>
    </div>
@endif
