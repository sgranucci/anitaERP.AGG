@php
    $fmtDias = static fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '.');
    $proveedoresEntrega = $tablero['proveedores_entrega'];
@endphp

<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Tiempo de entrega por proveedor (d&iacute;as, 25 m&aacute;s lentos)</span></div>
            <div class="card-body"><div class="tc-grafico tc-alto"><canvas id="tc-prov-entrega"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Art&iacute;culos (l&iacute;neas) comprados por proveedor (25 principales)</span></div>
            <div class="card-body"><div class="tc-grafico tc-alto"><canvas id="tc-prov-lineas"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Monto $ por proveedor (25 principales)</span></div>
            <div class="card-body"><div class="tc-grafico tc-alto"><canvas id="tc-prov-monto"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Evoluci&oacute;n mensual</span></div>
            <div class="card-body">
                <div class="tc-grafico" style="height: 190px;"><canvas id="tc-monto-mes"></canvas></div>
                <div class="tc-grafico mt-3" style="height: 190px;"><canvas id="tc-prov-activos"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-12 mb-3">
        <div class="card card-outline card-info mb-0">
            <div class="card-header"><span class="tc-card-titulo">Tiempo de entrega (25 proveedores con entrega m&aacute;s lenta)</span></div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 340px; overflow-y: auto;">
                    <table class="table table-sm table-bordered mb-0 tc-tabla">
                        <thead>
                            <tr>
                                <th>Proveedor</th>
                                <th class="text-right">OC</th>
                                <th class="text-right">L&iacute;neas</th>
                                <th class="text-right">Monto $</th>
                                <th class="text-right">Entrega prom. (d&iacute;as)</th>
                                <th class="text-right">OC con COM (n)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($proveedoresEntrega as $p)
                                <tr>
                                    <td>{{ $p['codigo'] }} &mdash; {{ $p['nombre'] }}</td>
                                    <td class="text-right">{{ number_format($p['oc'], 0, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format($p['lineas'], 0, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format($p['monto'], 0, ',', '.') }}</td>
                                    <td class="text-right">{{ $fmtDias($p['entrega_prom']) }}</td>
                                    <td class="text-right">{{ number_format($p['entrega_n'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">Sin datos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<p class="small text-muted mb-0">
    Monto $ = cantidad &times; precio &times; cotizaci&oacute;n vigente de la OC (descuento de l&iacute;nea no incluido).
    Los proveedores con una sola entrega pueden mostrar promedios extremos: ver la columna n.
</p>
