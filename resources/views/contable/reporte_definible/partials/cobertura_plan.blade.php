@php
    $c = $cobertura ?? [
        'total_plan' => 0,
        'asignadas' => 0,
        'pct' => 0,
        'huerfanas' => [],
        'huerfanas_total' => 0,
        'duplicadas' => [],
        'sin_cuenta_erp' => [],
    ];
@endphp
<div class="rd-help-box">
    <strong>Cobertura del plan (estilo FSV «unassigned»).</strong>
    Compara las cuentas imputables de sus empresas con las asignadas a este informe
    (solo origen real). Las duplicadas están permitidas; se listan para revisión.
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="border rounded p-3 text-center">
            <div class="h3 mb-0">{{ number_format($c['pct'], 1) }}%</div>
            <div class="small text-muted">Cobertura</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="border rounded p-3 text-center">
            <div class="h3 mb-0">{{ $c['asignadas'] }}</div>
            <div class="small text-muted">Asignadas en el plan</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="border rounded p-3 text-center">
            <div class="h3 mb-0">{{ $c['huerfanas_total'] }}</div>
            <div class="small text-muted">Sin asignar (huérfanas)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="border rounded p-3 text-center">
            <div class="h3 mb-0">{{ count($c['duplicadas']) }}</div>
            <div class="small text-muted">En más de un rubro</div>
        </div>
    </div>
</div>

<div class="progress mb-4" style="height:10px">
    <div class="progress-bar bg-info" role="progressbar" style="width: {{ min(100, $c['pct']) }}%"></div>
</div>

<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-secondary">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <div>
                    <strong>Cuentas no asignadas</strong>
                    <span class="text-muted small">(muestra hasta 80 de {{ $c['huerfanas_total'] }})</span>
                </div>
                @if (!empty($puede_actualizar) && !empty($c['huerfanas']))
                    <button type="button" class="btn btn-outline-primary btn-sm" id="rd-btn-cobertura-add"
                            title="Agrega las huérfanas visibles al rubro seleccionado en Estructura">
                        + Al rubro seleccionado
                    </button>
                @endif
            </div>
            <div class="card-body p-0" style="max-height:320px;overflow:auto">
                <table class="table table-sm mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr><th>Código</th><th>Nombre</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($c['huerfanas'] as $h)
                            <tr>
                                <td>{{ $h['codigo_fmt'] }}</td>
                                <td>{{ $h['nombre'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted text-center">Todas las imputables están asignadas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card card-outline card-secondary">
            <div class="card-header py-2"><strong>Cuentas en varios rubros</strong></div>
            <div class="card-body p-0" style="max-height:320px;overflow:auto">
                <table class="table table-sm mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr><th>Código</th><th>Nombre</th><th>Rubros</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($c['duplicadas'] as $d)
                            <tr>
                                <td>{{ $d['codigo_fmt'] }}</td>
                                <td>{{ $d['nombre'] ?? '' }}</td>
                                <td class="small">{{ implode(' · ', $d['rubros']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center">Ninguna duplicada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if (!empty($c['sin_cuenta_erp']))
            <div class="alert alert-warning mt-2">
                <strong>{{ count($c['sin_cuenta_erp']) }}</strong> códigos del informe no están en el plan ERP
                (revisar importación de cuentas).
            </div>
        @endif
    </div>
</div>
