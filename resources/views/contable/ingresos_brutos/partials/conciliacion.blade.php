@if (! empty($conciliacion['habilitada']))
    <div class="card card-outline card-warning mt-3" id="iibb-conciliacion-panel">
        <div class="card-header">
            <h3 class="card-title">Conciliación IIBB vs mayor contable</h3>
            @if ($periodo_texto ?? '')
                <span class="text-muted small ml-2">{{ $periodo_texto }}</span>
            @endif
            @php
                $saldoDesde = $conciliacion['saldo_ejercicio_desde'] ?? '2026-01-01';
                $saldoHasta = $conciliacion['saldo_ejercicio_hasta'] ?? '';
            @endphp
            @if ($saldoHasta !== '')
                <div class="small text-muted mt-1">
                    Saldo ejerc. (col. O/P mayor plano): {{ \Carbon\Carbon::parse($saldoDesde)->format('d/m/Y') }}
                    &rarr; {{ \Carbon\Carbon::parse($saldoHasta)->format('d/m/Y') }}
                    <span class="text-muted">— signo debe−haber (tal cual en el mayor); Dif. vs saldo compara en neto haber.</span>
                </div>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="width:2rem;"></th>
                            <th>Cód.</th>
                            <th>Concepto</th>
                            <th>Cuentas</th>
                            <th class="text-right">Total IIBB</th>
                            <th class="text-right">Total mayor</th>
                            <th class="text-right">Diferencia</th>
                            <th class="text-center">Estado</th>
                            <th class="text-right" title="Saldo de ejercicio del mayor plano (columna O/P, debe−haber)">Saldo ejerc.</th>
                            <th class="text-right">Dif. vs saldo</th>
                            <th class="text-center">Estado saldo</th>
                            <th class="text-center">Reg.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($conciliacion['items'] ?? [] as $idx => $item)
                            @php
                                $auditId = 'iibb-audit-'.$idx;
                                $resumen = $item['auditoria']['resumen'] ?? [];
                                $tieneDetalle = ($item['registros'] ?? 0) > 0 || ($item['movimientos_mayor'] ?? []) !== [];
                                $totalIibb = $item['total_iibb'] ?? $item['total_sicore'] ?? 0;
                                $difSaldo = $item['diferencia_iibb_saldo'] ?? $item['diferencia_sicore_saldo'] ?? 0;
                            @endphp
                            <tr @class([
                                'table-success' => ! empty($item['cuadra']),
                                'table-danger' => empty($item['cuadra']) && ($item['registros'] ?? 0) > 0,
                            ])>
                                <td class="text-center align-middle">
                                    @if ($tieneDetalle)
                                        <button type="button"
                                            class="btn btn-xs btn-outline-secondary js-iibb-audit-toggle"
                                            data-toggle="collapse"
                                            data-target="#{{ $auditId }}"
                                            aria-expanded="false"
                                            aria-controls="{{ $auditId }}"
                                            title="Ver auditoría operación por operación">
                                            <i class="fa fa-chevron-down"></i>
                                        </button>
                                    @endif
                                </td>
                                <td>{{ $item['codigo_impuesto'] ?? '' }}</td>
                                <td>
                                    {{ $item['nombre'] ?? '' }}
                                    <div class="small text-muted">{{ $item['criterio'] ?? '' }}</div>
                                    @if (! empty($item['cuenta_inversa']))
                                        <span class="badge badge-light border small">Pasivo · neto firmado</span>
                                    @endif
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
                                    <div class="small text-muted" title="Solo generación de retención (sin pago DDJJ / compensación / reclas)">
                                        comparable
                                    </div>
                                    @if (! empty($item['movimientos_mayor_excluidos']))
                                        <div class="small text-muted"
                                            title="Movimientos excluidos del saldo comparable">
                                            excl. {{ number_format((float) ($item['total_mayor_excluido'] ?? 0), 2, ',', '.') }}
                                            ({{ count($item['movimientos_mayor_excluidos']) }})
                                        </div>
                                    @endif
                                </td>
                                <td class="text-right">{{ number_format((float) ($item['diferencia'] ?? 0), 2, ',', '.') }}</td>
                                <td class="text-center">
                                    @if (! empty($item['cuadra']))
                                        <span class="badge badge-success">Cuadra</span>
                                    @elseif (($item['registros'] ?? 0) === 0 && ($item['total_mayor'] ?? 0) == 0)
                                        <span class="badge badge-secondary">Sin mov.</span>
                                    @else
                                        <span class="badge badge-danger">Diferencia</span>
                                    @endif
                                </td>
                                <td class="text-right @if (($item['saldo_ejercicio'] ?? 0) < 0) text-danger @endif">
                                    {{ number_format((float) ($item['saldo_ejercicio'] ?? 0), 2, ',', '.') }}
                                </td>
                                <td class="text-right">{{ number_format((float) $difSaldo, 2, ',', '.') }}</td>
                                <td class="text-center">
                                    @if (! empty($item['cuadra_saldo']))
                                        <span class="badge badge-success">Cuadra</span>
                                    @else
                                        <span class="badge badge-warning">Dif. saldo</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $item['registros'] ?? 0 }}</td>
                            </tr>
                            @if ($tieneDetalle)
                                <tr class="p-0 border-0">
                                    <td colspan="12" class="p-0 border-0">
                                        <div id="{{ $auditId }}" class="collapse border-top bg-light">
                                            <div class="px-3 py-3">
                                                @php
                                                    $fuente = $item['fuente_mayor'] ?? 'ninguna';
                                                    $exp = $item['explicacion_diferencia'] ?? [];
                                                @endphp
                                                @if (($resumen['coinciden'] ?? 0) > 0 || ($resumen['solo_mayor'] ?? 0) > 0 || ($resumen['solo_sicore'] ?? 0) > 0)
                                                    <div class="alert alert-light border small mb-3 py-2">
                                                        <strong>Desglose de la diferencia</strong>
                                                        <ul class="mb-0 pl-3">
                                                            <li>
                                                                Operaciones que <strong>cuadran</strong> ({{ $resumen['coinciden'] ?? 0 }}):
                                                                IIBB {{ number_format((float) ($resumen['sum_coincidentes_sicore'] ?? 0), 2, ',', '.') }}
                                                                = mayor {{ number_format((float) ($resumen['sum_coincidentes_mayor'] ?? 0), 2, ',', '.') }}
                                                            </li>
                                                            @if (($resumen['solo_mayor'] ?? 0) > 0)
                                                                <li>
                                                                    Solo en mayor ({{ $resumen['solo_mayor'] }} mov.):
                                                                    {{ number_format((float) ($resumen['sum_solo_mayor'] ?? 0), 2, ',', '.') }}
                                                                    <span class="text-muted">— no están en el IIBB a presentar</span>
                                                                </li>
                                                            @endif
                                                            @if (($resumen['solo_sicore'] ?? 0) > 0)
                                                                <li>
                                                                    Solo en IIBB ({{ $resumen['solo_sicore'] }}):
                                                                    {{ number_format((float) ($resumen['sum_solo_sicore'] ?? 0), 2, ',', '.') }}
                                                                </li>
                                                            @endif
                                                            <li>
                                                                <strong>Diferencia</strong> = IIBB − mayor neto =
                                                                {{ number_format((float) $totalIibb, 2, ',', '.') }}
                                                                − ({{ number_format((float) ($item['total_mayor'] ?? 0), 2, ',', '.') }})
                                                                = {{ number_format((float) ($item['diferencia'] ?? 0), 2, ',', '.') }}
                                                            </li>
                                                        </ul>
                                                        @if (! empty($exp['diferencia_explicada_por_solo_mayor']))
                                                            <p class="mb-0 mt-2 text-muted">
                                                                Todo el IIBB cuadra operación por operación; la diferencia total coincide con los movimientos <em>solo mayor</em>
                                                                (retenciones en mayor sin par en el archivo).
                                                            </p>
                                                        @endif
                                                        @if (! empty($item['movimientos_mayor_excluidos']))
                                                            <p class="mb-0 mt-2 text-muted">
                                                                Del saldo comparable se excluyeron
                                                                {{ count($item['movimientos_mayor_excluidos']) }} movimiento(s)
                                                                (pago DDJJ, compensación o reclasificación):
                                                                neto {{ number_format((float) ($item['total_mayor_excluido'] ?? 0), 2, ',', '.') }}.
                                                            </p>
                                                        @endif
                                                    </div>
                                                @endif
                                                <div class="d-flex flex-wrap align-items-center mb-2">
                                                    <h6 class="mb-0 mr-3">Auditoría operación por operación</h6>
                                                    @if ($fuente === 'erp')
                                                        <span class="badge badge-light border">Mayor AnitaERP</span>
                                                    @elseif ($fuente === 'anita')
                                                        <span class="badge badge-light border">Mayor Anita (subdiario + ctamov)</span>
                                                    @endif
                                                    <span class="badge badge-success ml-1">{{ $resumen['coinciden'] ?? 0 }} cuadran</span>
                                                    @if (($resumen['solo_sicore'] ?? 0) > 0)
                                                        <span class="badge badge-warning ml-1">{{ $resumen['solo_sicore'] }} solo IIBB</span>
                                                    @endif
                                                    @if (($resumen['solo_mayor'] ?? 0) > 0)
                                                        <span class="badge badge-info ml-1">{{ $resumen['solo_mayor'] }} solo mayor</span>
                                                    @endif
                                                    @if (! empty($item['movimientos_mayor_excluidos']))
                                                        <span class="badge badge-secondary ml-1">{{ count($item['movimientos_mayor_excluidos']) }} excl. comparable</span>
                                                    @endif
                                                </div>

                                                <div class="table-responsive mb-3">
                                                    <table class="table table-sm table-bordered mb-0 bg-white" style="font-size:0.78rem;">
                                                        <thead>
                                                            <tr style="background-color:#85C1E9;color:#17202A;">
                                                                <th>Estado</th>
                                                                <th>Fecha</th>
                                                                <th>Referencia IIBB</th>
                                                                <th>Proveedor</th>
                                                                <th class="text-right">Imp. IIBB</th>
                                                                <th>Mayor</th>
                                                                <th class="text-right">Imp. mayor</th>
                                                                <th class="text-right">Dif.</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @forelse ($item['auditoria']['filas'] ?? [] as $fila)
                                                                @php
                                                                    $tipo = $fila['tipo'] ?? '';
                                                                    $rowClass = match ($tipo) {
                                                                        'coincide' => 'table-success',
                                                                        'solo_sicore' => 'table-warning',
                                                                        'solo_mayor' => 'table-info',
                                                                        default => '',
                                                                    };
                                                                    $estadoLabel = match ($tipo) {
                                                                        'coincide' => 'Cuadra',
                                                                        'solo_sicore' => 'Sin mayor',
                                                                        'solo_mayor' => 'Sin IIBB',
                                                                        default => '—',
                                                                    };
                                                                @endphp
                                                                <tr class="{{ $rowClass }}">
                                                                    <td>
                                                                        @if ($tipo === 'coincide')
                                                                            <span class="badge badge-success">{{ $estadoLabel }}</span>
                                                                        @elseif ($tipo === 'solo_sicore')
                                                                            <span class="badge badge-warning">{{ $estadoLabel }}</span>
                                                                        @else
                                                                            <span class="badge badge-info">{{ $estadoLabel }}</span>
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        @php $fechaFila = $fila['fecha'] ?: ($fila['mayor_fecha'] ?? ''); @endphp
                                                                        {{ $fechaFila !== '' ? date('d/m/Y', strtotime($fechaFila)) : '' }}
                                                                    </td>
                                                                    <td>{{ $fila['referencia_sicore'] ?? '' }}</td>
                                                                    <td>
                                                                        @if (! empty($fila['proveedor']))
                                                                            {{ $fila['proveedor'] }}
                                                                            @if (! empty($fila['codigo_proveedor']))
                                                                                <div class="small text-muted">{{ $fila['codigo_proveedor'] }}</div>
                                                                            @endif
                                                                        @endif
                                                                    </td>
                                                                    <td class="text-right @if (($fila['importe_sicore'] ?? 0) < 0) text-danger @endif">
                                                                        @if ($fila['importe_sicore'] !== null)
                                                                            {{ number_format((float) $fila['importe_sicore'], 2, ',', '.') }}
                                                                        @endif
                                                                    </td>
                                                                    <td class="small">
                                                                        @if (! empty($fila['mayor_asiento']))
                                                                            As. {{ $fila['mayor_asiento'] }}
                                                                        @endif
                                                                        @if (! empty($fila['mayor_detalle']))
                                                                            <div class="text-muted">{{ $fila['mayor_detalle'] }}</div>
                                                                        @endif
                                                                    </td>
                                                                    <td class="text-right @if (($fila['importe_mayor'] ?? 0) < 0) text-danger @endif">
                                                                        @if ($fila['importe_mayor'] !== null)
                                                                            {{ number_format((float) $fila['importe_mayor'], 2, ',', '.') }}
                                                                        @endif
                                                                    </td>
                                                                    <td class="text-right">
                                                                        @if ($fila['diferencia'] !== null)
                                                                            {{ number_format((float) $fila['diferencia'], 2, ',', '.') }}
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td colspan="8" class="text-muted text-center">Sin operaciones para auditar.</td>
                                                                </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>

                                                @php
                                                    $movs = $item['movimientos_mayor'] ?? [];
                                                    $excluidos = $item['movimientos_mayor_excluidos'] ?? [];
                                                @endphp
                                                @if ($excluidos !== [])
                                                    <p class="mb-1">
                                                        <a class="small" data-toggle="collapse" href="#{{ $auditId }}-excluidos" role="button" aria-expanded="false">
                                                            <i class="fa fa-filter"></i> Excluidos del comparable ({{ count($excluidos) }} líneas)
                                                        </a>
                                                    </p>
                                                    <div class="collapse mb-2" id="{{ $auditId }}-excluidos">
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-bordered mb-0 bg-white" style="font-size:0.78rem;">
                                                                <thead>
                                                                    <tr style="background-color:#d5d8dc;color:#17202A;">
                                                                        <th>Fecha</th>
                                                                        <th>Asiento</th>
                                                                        <th>Motivo</th>
                                                                        <th>Detalle</th>
                                                                        <th class="text-right">Debe</th>
                                                                        <th class="text-right">Haber</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach ($excluidos as $mov)
                                                                        @php
                                                                            $motivoLabel = match ($mov['motivo_exclusion'] ?? '') {
                                                                                'pago_sicore' => 'Pago DDJJ SICORE',
                                                                                'pago_arba' => 'Pago liquidación ARBA',
                                                                                'compensacion_sicore' => 'Compensación',
                                                                                'reclasificacion' => 'Reclasificación',
                                                                                default => 'Excluido',
                                                                            };
                                                                        @endphp
                                                                        <tr class="table-secondary">
                                                                            <td>{{ ! empty($mov['fecha']) ? date('d/m/Y', strtotime($mov['fecha'])) : '' }}</td>
                                                                            <td>{{ $mov['asiento_id'] ?? '' }}</td>
                                                                            <td>{{ $motivoLabel }}</td>
                                                                            <td>{{ $mov['detalle'] ?? '' }}</td>
                                                                            <td class="text-right">
                                                                                @if (($mov['debe'] ?? null) !== null)
                                                                                    {{ number_format((float) $mov['debe'], 2, ',', '.') }}
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-right">
                                                                                @if (($mov['haber'] ?? null) !== null)
                                                                                    {{ number_format((float) $mov['haber'], 2, ',', '.') }}
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @endif
                                                @if ($movs !== [])
                                                    <p class="mb-1">
                                                        <a class="small" data-toggle="collapse" href="#{{ $auditId }}-mayor" role="button" aria-expanded="false">
                                                            <i class="fa fa-list"></i> Mayor analítico completo ({{ count($movs) }} líneas)
                                                        </a>
                                                    </p>
                                                    <div class="collapse" id="{{ $auditId }}-mayor">
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-striped table-bordered mb-0 bg-white" style="font-size:0.78rem;">
                                                                <thead>
                                                                    <tr style="background-color:#85C1E9;color:#17202A;">
                                                                        <th>Fecha</th>
                                                                        <th>Asiento</th>
                                                                        <th>Cuenta</th>
                                                                        <th>Detalle</th>
                                                                        <th class="text-center">Comp.</th>
                                                                        <th class="text-right">Debe</th>
                                                                        <th class="text-right">Haber</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach ($movs as $mov)
                                                                        @php
                                                                            $excluidoComp = ! empty($mov['excluido_comparable']);
                                                                        @endphp
                                                                        <tr @class(['table-secondary' => $excluidoComp])>
                                                                            <td>{{ ! empty($mov['fecha']) ? date('d/m/Y', strtotime($mov['fecha'])) : '' }}</td>
                                                                            <td>{{ $mov['asiento_id'] ?? '' }}</td>
                                                                            <td>
                                                                                {{ $mov['cuenta_codigo'] ?? '' }}
                                                                                @if (! empty($mov['cuenta_nombre']))
                                                                                    <div class="small text-muted">{{ $mov['cuenta_nombre'] }}</div>
                                                                                @endif
                                                                            </td>
                                                                            <td>{{ $mov['detalle'] ?? '' }}</td>
                                                                            <td class="text-center">
                                                                                @if ($excluidoComp)
                                                                                    <span class="badge badge-secondary" title="Excluido del saldo comparable">No</span>
                                                                                @else
                                                                                    <span class="badge badge-success">Sí</span>
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-right">
                                                                                @if (($mov['debe'] ?? null) !== null)
                                                                                    {{ number_format((float) $mov['debe'], 2, ',', '.') }}
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-right">
                                                                                @if (($mov['haber'] ?? null) !== null)
                                                                                    {{ number_format((float) $mov['haber'], 2, ',', '.') }}
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @else
                                                    <p class="text-muted small mb-0">
                                                        Sin movimientos contables en el período para las cuentas configuradas.
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="small text-muted px-3 py-2 mb-0 border-top">
                Tolerancia: {{ number_format((float) ($conciliacion['tolerancia'] ?? 0.05), 2, ',', '.') }}.
                Total IIBB: neto a presentar.
                Total mayor: neto firmado solo de generación de retención (haber +, debe −); se excluyen pago DDJJ, compensación y reclasificaciones.
                La diferencia es IIBB − mayor comparable.
            </p>
        </div>
    </div>

    <script>
    (function () {
        var panel = document.getElementById('iibb-conciliacion-panel');
        if (!panel || typeof window.jQuery === 'undefined') {
            return;
        }
        window.jQuery(panel).on('show.bs.collapse', '.collapse', function () {
            window.jQuery('[data-target="#' + this.id + '"] i', panel)
                .removeClass('fa-chevron-down').addClass('fa-chevron-up');
        }).on('hide.bs.collapse', '.collapse', function () {
            window.jQuery('[data-target="#' + this.id + '"] i', panel)
                .removeClass('fa-chevron-up').addClass('fa-chevron-down');
        });
    })();
    </script>
@endif
