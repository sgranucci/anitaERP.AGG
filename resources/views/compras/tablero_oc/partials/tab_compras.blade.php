@php
    $resumen = $tablero['resumen'];
    $ciclo = $tablero['ciclo'];
    $diarias = $tablero['diarias'];
    $fmtDias = static fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '.');
@endphp

<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        @include('compras.tablero_oc.partials.kpi', ['etiqueta' => 'Cantidad de OC', 'valor' => number_format($resumen['oc'], 0, ',', '.')])
    </div>
    <div class="col-6 col-md-3 mb-2">
        @include('compras.tablero_oc.partials.kpi', ['etiqueta' => 'Cantidad de l&iacute;neas', 'valor' => number_format($resumen['lineas'], 0, ',', '.')])
    </div>
    <div class="col-6 col-md-3 mb-2">
        @include('compras.tablero_oc.partials.kpi', [
            'etiqueta' => 'Aprobaci&oacute;n de la requisici&oacute;n',
            'valor' => $fmtDias($ciclo['aprobacion']).' d&iacute;as',
            'nota' => 'Alta &rarr; &uacute;ltima aprobaci&oacute;n del &aacute;rbol · n='.number_format($ciclo['n_aprobacion'], 0, ',', '.'),
        ])
    </div>
    <div class="col-6 col-md-3 mb-2">
        @include('compras.tablero_oc.partials.kpi', [
            'etiqueta' => 'Gesti&oacute;n del comprador',
            'valor' => $fmtDias($ciclo['gestion']).' d&iacute;as',
            'nota' => '&Uacute;ltima aprobaci&oacute;n de la requisici&oacute;n &rarr; emisi&oacute;n OC · n='.number_format($ciclo['n_gestion'], 0, ',', '.'),
        ])
    </div>
    <div class="col-6 col-md-3 mb-2">
        @include('compras.tablero_oc.partials.kpi', [
            'etiqueta' => 'Entrega del proveedor',
            'valor' => $fmtDias($ciclo['entrega']).' d&iacute;as',
            'nota' => 'Emisi&oacute;n OC &rarr; primera COM · n='.number_format($ciclo['n_entrega'], 0, ',', '.'),
        ])
    </div>
    <div class="col-6 col-md-3 mb-2">
        @include('compras.tablero_oc.partials.kpi', [
            'etiqueta' => 'Ciclo de vida',
            'valor' => $fmtDias($ciclo['ciclo']).' d&iacute;as',
            'nota' => 'Aprob. requisici&oacute;n &rarr; entrega · n='.number_format($ciclo['n_ciclo'], 0, ',', '.'),
        ])
    </div>
    <div class="col-6 col-md-3 mb-2">
        @include('compras.tablero_oc.partials.kpi', [
            'etiqueta' => 'Requisiciones por d&iacute;a',
            'valor' => $fmtDias($diarias['req_prom']),
            'nota' => 'Promedio por d&iacute;a h&aacute;bil ('.$diarias['habiles'].' d&iacute;as)',
        ])
    </div>
    <div class="col-6 col-md-3 mb-2">
        @include('compras.tablero_oc.partials.kpi', [
            'etiqueta' => 'OC por d&iacute;a',
            'valor' => $fmtDias($diarias['oc_prom']),
            'nota' => 'Promedio por d&iacute;a h&aacute;bil',
        ])
    </div>
    <div class="col-6 col-md-3 mb-2">
        @include('compras.tablero_oc.partials.kpi', [
            'etiqueta' => 'Monto comprado ($)',
            'valor' => '$ '.number_format($resumen['monto'], 0, ',', '.'),
            'nota' => 'Cantidad &times; precio &times; cotizaci&oacute;n',
        ])
    </div>
</div>

<div class="row">
    <div class="col-lg-4 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">OC por estado</span></div>
            <div class="card-body"><div class="tc-grafico"><canvas id="tc-estados"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-8 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">OC por estado y mes</span></div>
            <div class="card-body"><div class="tc-grafico"><canvas id="tc-estados-mes"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">OC pendientes: a tiempo vs. vencidas</span></div>
            <div class="card-body"><div class="tc-grafico"><canvas id="tc-pendientes"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-8 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">OC pendientes por mes (a tiempo / vencidas)</span></div>
            <div class="card-body"><div class="tc-grafico"><canvas id="tc-vencidas-mes"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-8 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Requisiciones y OC por d&iacute;a h&aacute;bil (promedio mensual)</span></div>
            <div class="card-body"><div class="tc-grafico"><canvas id="tc-diarias"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Requisiciones urgentes</span></div>
            <div class="card-body"><div class="tc-grafico"><canvas id="tc-urgentes"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Tiempos promedio (d&iacute;as)</span></div>
            <div class="card-body"><div class="tc-grafico" style="height: 160px;"><canvas id="tc-ciclo"></canvas></div>
                <small class="text-muted">Aprobaci&oacute;n de la requisici&oacute;n + gesti&oacute;n del comprador + entrega del proveedor, sobre el ciclo total (cada tramo con sus propias OC).</small>
            </div>
        </div>
    </div>
</div>
