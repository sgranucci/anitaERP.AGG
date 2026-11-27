@php
    $totalAh = $ahorros['total'];
    $fmtPesos = static fn ($v) => '$ '.number_format((float) $v, 0, ',', '.');
    $fmtPct = static fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '.').' %';
    $pctOcConAhorro = $totalAh['ocs_total'] > 0 ? $totalAh['ocs'] / $totalAh['ocs_total'] * 100.0 : null;
    $paramsExcel = $filtrosQuery + ['tab' => 'ahorros'];
    $sufijoExport = '?'.http_build_query(array_filter($paramsExcel, fn ($v) => $v !== null && $v !== ''));
@endphp

<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        @include('compras.tablero_oc.partials.kpi', [
            'etiqueta' => 'Ahorro total',
            'valor' => $fmtPesos($totalAh['ahorro']),
            'nota' => 'Neto de sobrecostos, en pesos',
        ])
    </div>
    <div class="col-6 col-md-3 mb-2">
        @include('compras.tablero_oc.partials.kpi', [
            'etiqueta' => 'Ahorro %',
            'valor' => $fmtPct($totalAh['pct']),
            'nota' => 'Sobre el precio anterior de las l&iacute;neas con ahorro ('.$fmtPesos($totalAh['base']).')',
        ])
    </div>
    <div class="col-6 col-md-3 mb-2">
        @include('compras.tablero_oc.partials.kpi', [
            'etiqueta' => 'L&iacute;neas con ahorro',
            'valor' => number_format($totalAh['lineas'], 0, ',', '.'),
            'nota' => 'en '.number_format($totalAh['ocs'], 0, ',', '.').' OC',
        ])
    </div>
    <div class="col-6 col-md-3 mb-2">
        @include('compras.tablero_oc.partials.kpi', [
            'etiqueta' => 'OC con ahorro cargado',
            'valor' => $fmtPct($pctOcConAhorro),
            'nota' => number_format($totalAh['ocs'], 0, ',', '.').' de '.number_format($totalAh['ocs_total'], 0, ',', '.').' OC del per&iacute;odo',
        ])
    </div>
</div>

@if ($totalAh['lineas'] === 0)
    <div class="alert alert-light border small">
        No hay l&iacute;neas de OC con ahorro cargado para los filtros elegidos. El ahorro se carga en cada l&iacute;nea de la
        requisici&oacute;n o de la OC (precio anterior, moneda y tipo de ahorro), tambi&eacute;n con la OC ya aprobada.
    </div>
@endif

<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Ahorro $ por comprador</span></div>
            <div class="card-body"><div class="tc-grafico tc-alto"><canvas id="tc-ah-comp"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Evoluci&oacute;n mensual por comprador (8 con m&aacute;s ahorro)</span></div>
            <div class="card-body"><div class="tc-grafico tc-alto"><canvas id="tc-ah-comp-mes"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Ahorro por tipo</span></div>
            <div class="card-body"><div class="tc-grafico"><canvas id="tc-ah-tipo"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Ahorro por proveedor (25 principales)</span></div>
            <div class="card-body"><div class="tc-grafico tc-alto"><canvas id="tc-ah-prov"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Ahorro $ por mes</span></div>
            <div class="card-body"><div class="tc-grafico"><canvas id="tc-ah-mes"></canvas></div></div>
        </div>
    </div>

    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-info mb-0">
            <div class="card-header"><span class="tc-card-titulo">Por comprador</span></div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                    <table class="table table-sm table-bordered mb-0 tc-tabla">
                        <thead>
                            <tr>
                                <th>Comprador</th>
                                <th class="text-right">L&iacute;neas</th>
                                <th class="text-right">Ahorro $</th>
                                <th class="text-right">Ahorro %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ahorros['compradores'] as $c)
                                <tr>
                                    <td>{{ $c['nombre'] }}</td>
                                    <td class="text-right">{{ number_format($c['lineas'], 0, ',', '.') }}</td>
                                    <td class="text-right">{{ $fmtPesos($c['ahorro']) }}</td>
                                    <td class="text-right">{{ $fmtPct($c['pct']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Sin datos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-info mb-0">
            <div class="card-header"><span class="tc-card-titulo">Por tipo de ahorro</span></div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                    <table class="table table-sm table-bordered mb-0 tc-tabla">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th class="text-right">L&iacute;neas</th>
                                <th class="text-right">Ahorro $</th>
                                <th class="text-right">Ahorro %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ahorros['tipos'] as $t)
                                <tr>
                                    <td>{{ $t['nombre'] }}</td>
                                    <td class="text-right">{{ number_format($t['lineas'], 0, ',', '.') }}</td>
                                    <td class="text-right">{{ $fmtPesos($t['ahorro']) }}</td>
                                    <td class="text-right">{{ $fmtPct($t['pct']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Sin datos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 mb-3">
        <div class="card card-outline card-info mb-0">
            <div class="card-header"><span class="tc-card-titulo">Por proveedor (25 con m&aacute;s ahorro)</span></div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                    <table class="table table-sm table-bordered mb-0 tc-tabla">
                        <thead>
                            <tr>
                                <th>Proveedor</th>
                                <th class="text-right">L&iacute;neas</th>
                                <th class="text-right">Ahorro $</th>
                                <th class="text-right">Ahorro %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ahorros['proveedores'] as $p)
                                <tr>
                                    <td>{{ $p['nombre'] }}</td>
                                    <td class="text-right">{{ number_format($p['lineas'], 0, ',', '.') }}</td>
                                    <td class="text-right">{{ $fmtPesos($p['ahorro']) }}</td>
                                    <td class="text-right">{{ $fmtPct($p['pct']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Sin datos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
    <span class="small font-weight-bold">Detalle de l&iacute;neas con ahorro</span>
    <span class="d-inline-flex align-items-center flex-nowrap ml-1" style="gap:.35rem;" role="group" aria-label="Exportar ahorros">
        <a href="{{ route('listar_tablero_compras', ['formato' => 'EXCEL']).$sufijoExport }}" class="btn btn-sm bg-success" title="Exportar Excel">
            <i class="fas fa-file-excel"></i> Excel
        </a>
        <a href="{{ route('listar_tablero_compras', ['formato' => 'CSV']).$sufijoExport }}" class="btn btn-sm bg-warning" title="Exportar CSV">
            <i class="fas fa-file-csv"></i> Csv
        </a>
    </span>
</div>
<div class="tabla-ancha-grilla" data-tabla-ancha>
    <div class="tabla-ancha-scroll-top" hidden>
        <div class="tabla-ancha-scroll-top-inner"></div>
    </div>
    <div class="tabla-ancha-wrap">
        <table class="table table-sm table-bordered table-hover mb-0 tabla-ancha tc-tabla" id="tabla-ahorros">
            @include('compras.tablero_oc.partials.tabla_ahorros', [
                'filas' => $ahorro_detalle->items(),
                'pantalla' => true,
                'puede_ver_ordencompra' => $puede_ver_ordencompra,
            ])
        </table>
    </div>
</div>
<div class="d-flex flex-wrap align-items-center justify-content-between mt-2">
    <span class="small text-muted mb-2 mb-md-0">
        @if ($ahorro_detalle->total() > 0)
            Mostrando {{ $ahorro_detalle->firstItem() }}&ndash;{{ $ahorro_detalle->lastItem() }} de {{ number_format($ahorro_detalle->total(), 0, ',', '.') }} l&iacute;neas
        @else
            Sin l&iacute;neas con ahorro.
        @endif
    </span>
    {{ $ahorro_detalle->appends($filtrosQuery + ['tab' => 'ahorros'])->links() }}
</div>
<p class="small text-muted mt-2 mb-0">
    Ahorro $ = (precio anterior &minus; precio final) &times; cantidad, en pesos con el tipo de cambio del ahorro. Un valor en rojo es sobrecosto.
    Ahorro % = ahorro / precio anterior de las mismas l&iacute;neas (no es un promedio de porcentajes). Solo cuentan las OC filtradas arriba.
</p>
