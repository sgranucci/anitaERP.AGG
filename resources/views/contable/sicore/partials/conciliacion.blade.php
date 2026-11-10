@if (! empty($conciliacion['habilitada']))
    <div class="card card-outline card-warning mt-3" id="sicore-conciliacion-panel">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title mb-0">Conciliación SICORE vs mayor contable</h3>
            @if ($periodo_texto ?? '')
                <span class="text-muted small ml-2">{{ $periodo_texto }}</span>
            @endif
            <button type="button"
                class="btn btn-sm btn-outline-secondary ml-auto"
                data-toggle="collapse"
                data-target="#sicore-conciliacion-movs"
                aria-expanded="false"
                aria-controls="sicore-conciliacion-movs">
                Movimiento por movimiento
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Cód.</th>
                            <th>Concepto</th>
                            <th>Cuentas</th>
                            <th class="text-right">Total SICORE</th>
                            <th class="text-right">Total mayor</th>
                            <th class="text-right">Diferencia</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($conciliacion['items'] ?? [] as $item)
                            <tr @class([
                                'table-success' => ! empty($item['cuadra']),
                                'table-danger' => empty($item['cuadra']),
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
                                    {{ number_format((float) ($item['total_sicore'] ?? 0), 2, ',', '.') }}
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
                Total SICORE: suma de movimientos del período consultado (1ra quincena, 2da o mes).
                Total mayor: saldo acumulado (columna P del mayor) al último movimiento de la quincena/mes, con el haber en positivo.
                Diferencia: SICORE − mayor. Si |diferencia| ≤ $ {{ number_format((float) ($conciliacion['tolerancia'] ?? 100), 2, ',', '.') }} → OK.
            </p>

            <div class="collapse border-top" id="sicore-conciliacion-movs">
                <div class="px-3 py-2">
                    @php
                        $hayDetalleMovimientos = false;
                    @endphp
                    @foreach ($conciliacion['items'] ?? [] as $item)
                        @php
                            $filasAud = $item['auditoria']['filas'] ?? [];
                            $resumenAud = $item['auditoria']['resumen'] ?? [];
                        @endphp
                        @if ($filasAud !== [])
                            @php
                                $hayDetalleMovimientos = true;
                            @endphp
                                <p class="small mb-1 mt-2">
                                    <strong>{{ $item['nombre'] ?? '' }}</strong>
                                    <span class="text-muted">
                                        {{ (int) ($resumenAud['coinciden'] ?? 0) }} coinciden,
                                        {{ (int) ($resumenAud['solo_sicore'] ?? 0) }} solo SICORE,
                                        {{ (int) ($resumenAud['solo_mayor'] ?? 0) }} solo mayor
                                        @if ((int) ($item['mayor_excluidos'] ?? 0) > 0)
                                            , {{ (int) $item['mayor_excluidos'] }} del mayor no son retención
                                        @endif
                                    </span>
                                </p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-2">
                                        <thead style="background:#85C1E9;color:#17202A;">
                                            <tr>
                                                <th>Estado</th>
                                                <th>Fecha</th>
                                                <th>SICORE</th>
                                                <th>Mayor</th>
                                                <th class="text-right">Importe SICORE</th>
                                                <th class="text-right">Importe mayor</th>
                                                <th class="text-right">Diferencia</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($filasAud as $fila)
                                                <tr>
                                                    <td class="text-nowrap">
                                                        @if (($fila['tipo'] ?? '') === 'coincide')
                                                            <span class="badge badge-success">Coincide</span>
                                                        @elseif (($fila['tipo'] ?? '') === 'solo_mayor')
                                                            <span class="badge badge-warning">Solo mayor</span>
                                                        @else
                                                            <span class="badge badge-danger">Solo SICORE</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-nowrap">{{ $fila['fecha'] ?? '' }}</td>
                                                    <td class="small">
                                                        {{ $fila['referencia_sicore'] ?? '' }}
                                                        @if (($fila['proveedor'] ?? '') !== '')
                                                            <div class="text-muted">{{ $fila['proveedor'] }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="small">{{ $fila['mayor_detalle'] ?? '' }}</td>
                                                    <td class="text-right">
                                                        @if ($fila['importe_sicore'] !== null)
                                                            {{ number_format((float) $fila['importe_sicore'], 2, ',', '.') }}
                                                        @endif
                                                    </td>
                                                    <td class="text-right">
                                                        @if ($fila['importe_mayor'] !== null)
                                                            {{ number_format((float) $fila['importe_mayor'], 2, ',', '.') }}
                                                        @endif
                                                    </td>
                                                    <td class="text-right">{{ number_format((float) ($fila['diferencia'] ?? 0), 2, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                        @endif
                    @endforeach
                    @if (! $hayDetalleMovimientos)
                        <p class="small text-muted mb-0">No hay movimientos para cruzar en este período.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
