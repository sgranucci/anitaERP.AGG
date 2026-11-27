@php
    $compradores = $tablero['compradores'];
    $fmtDias = static fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '.');
@endphp

<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">OC por comprador</span></div>
            <div class="card-body"><div class="tc-grafico tc-alto"><canvas id="tc-comp-oc"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Tiempo de gesti&oacute;n por comprador (d&iacute;as)</span></div>
            <div class="card-body"><div class="tc-grafico tc-alto"><canvas id="tc-comp-gestion"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">OC por mes (8 compradores con m&aacute;s OC)</span></div>
            <div class="card-body"><div class="tc-grafico"><canvas id="tc-comp-oc-mes"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Tiempo de gesti&oacute;n por mes (d&iacute;as)</span></div>
            <div class="card-body"><div class="tc-grafico"><canvas id="tc-comp-gestion-mes"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">OC por empresa</span></div>
            <div class="card-body"><div class="tc-grafico"><canvas id="tc-empresas"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-8 mb-3">
        <div class="card card-outline card-info h-100 mb-0">
            <div class="card-header"><span class="tc-card-titulo">Detalle por comprador</span></div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 340px; overflow-y: auto;">
                    <table class="table table-sm table-bordered mb-0 tc-tabla">
                        <thead>
                            <tr>
                                <th>Comprador</th>
                                <th class="text-right">OC</th>
                                <th class="text-right">Art&iacute;culos (l&iacute;neas)</th>
                                <th class="text-right">Gesti&oacute;n prom. (d&iacute;as)</th>
                                <th class="text-right">Monto $</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($compradores as $c)
                                <tr>
                                    <td>{{ $c['nombre'] }}</td>
                                    <td class="text-right">{{ number_format($c['oc'], 0, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format($c['lineas'], 0, ',', '.') }}</td>
                                    <td class="text-right">{{ $fmtDias($c['gestion_prom']) }}</td>
                                    <td class="text-right">{{ number_format($c['monto'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">Sin datos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<p class="small text-muted mb-0">
    Comprador = quien carg&oacute; la OC; en las OC que vienen de Anita, el usuario Anita vinculado por logname.
    Sin v&iacute;nculo figura como &laquo;Usuario Anita N&raquo;.
</p>
