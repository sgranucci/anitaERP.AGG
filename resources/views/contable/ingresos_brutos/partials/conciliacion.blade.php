@if (! empty($conciliacion['habilitada']))
    @php
        $mostrarControlPeriodo = ! empty($conciliacion['control_periodo']);
        $fmtDesde = ! empty($conciliacion['periodo_desde'])
            ? date('d/m/Y', strtotime((string) $conciliacion['periodo_desde']))
            : '';
        $fmtHasta = ! empty($conciliacion['periodo_hasta'])
            ? date('d/m/Y', strtotime((string) $conciliacion['periodo_hasta']))
            : '';
    @endphp
    <div class="card card-outline card-warning mt-3" id="iibb-conciliacion-panel">
        <div class="card-header d-flex align-items-center flex-wrap">
            <h3 class="card-title mb-0">Conciliación IIBB vs mayor contable</h3>
            @if ($periodo_texto ?? '')
                <span class="text-muted small ml-2">{{ $periodo_texto }}</span>
            @endif
            <div class="ml-auto d-flex align-items-center flex-wrap">
                <button type="button"
                    class="btn btn-sm {{ $mostrarControlPeriodo ? 'btn-warning' : 'btn-outline-warning' }} mr-2"
                    id="iibb-btn-control-periodo"
                    aria-pressed="{{ $mostrarControlPeriodo ? 'true' : 'false' }}"
                    title="Muestra un renglón extra: retenciones del período vs movimientos del mayor (sin saldo de ejercicio ni pagos ARBA)">
                    <i class="fa fa-calendar-check"></i>
                    Control del período
                </button>
                <button type="button"
                    class="btn btn-sm btn-outline-secondary"
                    data-toggle="collapse"
                    data-target="#iibb-conciliacion-movs"
                    aria-expanded="false"
                    aria-controls="iibb-conciliacion-movs">
                    Movimiento por movimiento
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0" id="iibb-tabla-conciliacion">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Cód.</th>
                            <th>Concepto</th>
                            <th>Cuentas</th>
                            <th class="text-right">Total IIBB</th>
                            <th class="text-right">Total mayor</th>
                            <th class="text-right">Diferencia</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($conciliacion['items'] ?? [] as $item)
                            @php
                                $totalIibb = $item['total_iibb'] ?? $item['total_sicore'] ?? 0;
                            @endphp
                            <tr @class([
                                'table-success' => ! empty($item['cuadra']),
                                'table-danger' => empty($item['cuadra']) && ($item['registros'] ?? 0) > 0,
                            ])>
                                <td>{{ $item['codigo_impuesto'] ?? '' }}</td>
                                <td>
                                    {{ $item['nombre'] ?? '' }}
                                    <div class="small text-muted">Saldo de ejercicio (col. P Anita)</div>
                                </td>
                                <td class="small">
                                    @forelse ($item['cuentas'] ?? [] as $cuenta)
                                        <div>{{ $cuenta['codigo'] ?? '' }} — {{ $cuenta['nombre'] ?? '' }}</div>
                                    @empty
                                        <span class="text-warning">Sin cuentas configuradas</span>
                                    @endforelse
                                </td>
                                <td class="text-right">
                                    {{ number_format((float) $totalIibb, 2, ',', '.') }}
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
                            <tr class="iibb-fila-control-periodo @if (! $mostrarControlPeriodo) d-none @endif @if (! empty($item['cuadra_periodo'])) table-success @else table-warning @endif">
                                <td>{{ $item['codigo_impuesto'] ?? '' }}</td>
                                <td>
                                    <span class="badge badge-warning mr-1">Período</span>
                                    Control retenciones emitidas
                                    <div class="small text-muted">
                                        Solo movimientos del mayor
                                        @if ($fmtDesde !== '' && $fmtHasta !== '')
                                            ({{ $fmtDesde }} — {{ $fmtHasta }})
                                        @endif
                                        · sin saldo de ejercicio
                                    </div>
                                </td>
                                <td class="small text-muted">
                                    Retenciones emitidas / anuladas / devoluciones del rango
                                    @if ((int) ($item['mayor_excluidos'] ?? 0) > 0)
                                        <div>
                                            {{ (int) $item['mayor_excluidos'] }} mov. del mayor excluidos
                                            (pagos ARBA/DDJJ, etc.)
                                            @if (isset($item['mayor_excluidos_neto']))
                                                · neto {{ number_format((float) $item['mayor_excluidos_neto'], 2, ',', '.') }}
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="text-right">
                                    {{ number_format((float) $totalIibb, 2, ',', '.') }}
                                </td>
                                <td class="text-right @if (($item['total_mayor_periodo'] ?? 0) < 0) text-danger @endif">
                                    {{ number_format((float) ($item['total_mayor_periodo'] ?? 0), 2, ',', '.') }}
                                </td>
                                <td class="text-right">{{ number_format((float) ($item['diferencia_periodo'] ?? 0), 2, ',', '.') }}</td>
                                <td class="text-center">
                                    @if (! empty($item['cuadra_periodo']))
                                        <span class="badge badge-success">OK</span>
                                    @elseif (($item['registros'] ?? 0) === 0 && ($item['total_mayor_periodo'] ?? 0) == 0)
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
                Total IIBB: suma de movimientos del período consultado (1ra quincena, 2da o mes).
                Total mayor (renglón principal): saldo acumulado (columna P del mayor Anita) al último movimiento de la quincena/mes, con el haber en positivo.
                <span class="iibb-ayuda-control-periodo @if (! $mostrarControlPeriodo) d-none @endif">
                    Control del período: misma suma IIBB vs solo movimientos del mayor en el rango (retenciones emitidas), sin arrastrar el saldo desde principio de ejercicio ni pagos ARBA.
                </span>
                Diferencia: IIBB − mayor. Si |diferencia| ≤ $ {{ number_format((float) ($conciliacion['tolerancia'] ?? 100), 2, ',', '.') }} → OK.
            </p>

            <div class="collapse border-top" id="iibb-conciliacion-movs">
                <div class="px-3 py-2">
                    @php
                        $hayDetalleMovimientos = false;
                    @endphp
                    @foreach ($conciliacion['items'] ?? [] as $item)
                        @php
                            $filasAud = $item['auditoria']['filas'] ?? [];
                            $resumenAud = $item['auditoria']['resumen'] ?? [];
                            $excluidosDet = $item['mayor_excluidos_detalle'] ?? [];
                        @endphp

                        @if ($excluidosDet !== [])
                            @php
                                $hayDetalleMovimientos = true;
                            @endphp
                            <p class="small mb-1 mt-2">
                                <strong>{{ $item['nombre'] ?? '' }}</strong>
                                — movimientos del mayor no comparables (pagos ARBA / DDJJ / reclasif.)
                            </p>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-2">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Asiento</th>
                                            <th>Motivo</th>
                                            <th>Detalle</th>
                                            <th class="text-right">Importe</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($excluidosDet as $exc)
                                            <tr>
                                                <td class="text-nowrap">{{ $exc['fecha'] ?? '' }}</td>
                                                <td>{{ $exc['asiento'] ?? '' }}</td>
                                                <td class="small">{{ $exc['motivo'] ?? '' }}</td>
                                                <td class="small">{{ $exc['detalle'] ?? '' }}</td>
                                                <td class="text-right">{{ number_format((float) ($exc['importe'] ?? 0), 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($filasAud !== [])
                            @php
                                $hayDetalleMovimientos = true;
                            @endphp
                            <p class="small mb-1 mt-2">
                                <strong>{{ $item['nombre'] ?? '' }}</strong>
                                <span class="text-muted">
                                    {{ (int) ($resumenAud['coinciden'] ?? 0) }} coinciden,
                                    {{ (int) ($resumenAud['solo_sicore'] ?? 0) }} solo IIBB,
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
                                            <th>IIBB</th>
                                            <th>Mayor</th>
                                            <th class="text-right">Importe IIBB</th>
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
                                                        <span class="badge badge-danger">Solo IIBB</span>
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
