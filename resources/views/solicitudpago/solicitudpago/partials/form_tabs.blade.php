@php
    $cuotasSp = collect(($data ?? null)?->cuotas ?? []);
    $tienePlanCuotas = $cuotasSp->isNotEmpty();
    $cuotasConHija = $cuotasSp->filter(fn ($c) => (int) ($c->solicitudpago_hija_id ?? 0) > 0)->count();
    $esHijaSp = isset($data) && (int) ($data->solicitudpago_madre_id ?? 0) > 0;
@endphp
<div class="card-body">
    @include('includes.tabs-activas-estilos')
    @if ($tienePlanCuotas || $esHijaSp)
        <div class="alert alert-light border py-2 px-3 mb-3 d-flex flex-wrap align-items-center">
            @if ($tienePlanCuotas)
                <span class="text-muted small mr-2">
                    <i class="fa fa-calendar-check text-primary"></i>
                    Plan con <strong>{{ $cuotasSp->count() }}</strong> cuotas
                    ({{ $cuotasConHija }} con SP hija).
                </span>
                <a href="#tab-cuotas" class="btn btn-outline-primary btn-xs btn-sm py-0 px-2"
                   onclick="document.getElementById('tab-cuotas-link')?.click(); return false;">
                    Ver en Cuotas
                </a>
            @elseif ($esHijaSp && ($data->madre ?? null))
                <span class="text-muted small mr-2">
                    <i class="fa fa-link text-primary"></i>
                    Esta SP es hija del plan
                </span>
                <a href="{{ route('editar_solicitudpago', ['id' => $data->madre->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                   class="btn btn-outline-primary btn-xs btn-sm py-0 px-2"
                   target="_blank" rel="noopener"
                   title="Abrir SP madre en solapa de consulta">
                    #{{ $data->madre->codigo }}
                </a>
            @endif
        </div>
    @endif
    <div class="tabs-activas">
        <ul class="nav nav-tabs" id="tabs-solicitudpago" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-datos-link" data-toggle="tab" href="#tab-datos" role="tab">
                    <i class="fa fa-info-circle"></i> Datos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-cuentas-link" data-toggle="tab" href="#tab-cuentas" role="tab">
                    <i class="fa fa-book"></i> Cuentas
                    <span id="sp-badge-asiento" class="badge badge-warning ml-1 d-none" title="Asiento incompleto">!</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-cuotas-link" data-toggle="tab" href="#tab-cuotas" role="tab">
                    <i class="fa fa-calendar"></i> Cuotas
                    @if ($tienePlanCuotas)
                        <span class="badge badge-primary ml-1">{{ $cuotasConHija }}/{{ $cuotasSp->count() }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-archivos-link" data-toggle="tab" href="#tab-archivos" role="tab">
                    <i class="fa fa-paperclip"></i> Archivos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-arbol-link" data-toggle="tab" href="#tab-arbol" role="tab">
                    <i class="fa fa-sitemap"></i> &Aacute;rbol
                    @php
                        $spArbolPend = collect($arbolMovimientos ?? [])->filter(function ($m) {
                            return strcasecmp((string) ($m->estado ?? ''), 'Pendiente') === 0;
                        })->count();
                    @endphp
                    @if ($spArbolPend > 0)
                        <span class="badge badge-warning ml-1" title="Pendientes de aprobaci&oacute;n">{{ $spArbolPend }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-historial-link" data-toggle="tab" href="#tab-historial" role="tab">
                    <i class="fa fa-history"></i> Historial
                </a>
            </li>
        </ul>
    </div>
    <div class="tab-content pt-3">
        <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
            @include('solicitudpago.solicitudpago.form_datos')
        </div>
        <div class="tab-pane fade" id="tab-cuentas" role="tabpanel">
            @include('solicitudpago.solicitudpago.form_cuentas')
        </div>
        <div class="tab-pane fade" id="tab-cuotas" role="tabpanel">
            @include('solicitudpago.solicitudpago.form_cuotas')
        </div>
        <div class="tab-pane fade" id="tab-archivos" role="tabpanel">
            @include('solicitudpago.solicitudpago.form_archivos')
        </div>
        <div class="tab-pane fade" id="tab-arbol" role="tabpanel">
            @include('solicitudpago.solicitudpago.form_arbol')
        </div>
        <div class="tab-pane fade" id="tab-historial" role="tabpanel">
            @include('solicitudpago.solicitudpago.form_historial')
        </div>
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@include('includes.contable.modalconsultacuentacontable')
@include('includes.solicitudpago.modalconsultaconcepto_solicitudpago')
@if (request('tab') === 'archivos')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var link = document.getElementById('tab-archivos-link');
    if (link) {
        link.click();
    }
});
</script>
@elseif (request('tab') === 'arbol')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var link = document.getElementById('tab-arbol-link');
    if (link) {
        link.click();
    }
});
</script>
@endif
