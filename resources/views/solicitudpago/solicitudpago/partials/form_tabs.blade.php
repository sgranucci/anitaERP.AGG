<div class="card-body">
    @include('includes.tabs-activas-estilos')
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
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-cuotas-link" data-toggle="tab" href="#tab-cuotas" role="tab">
                    <i class="fa fa-calendar"></i> Cuotas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-archivos-link" data-toggle="tab" href="#tab-archivos" role="tab">
                    <i class="fa fa-paperclip"></i> Archivos
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
        <div class="tab-pane fade" id="tab-historial" role="tabpanel">
            @include('solicitudpago.solicitudpago.form_historial')
        </div>
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@include('includes.contable.modalconsultacuentacontable')
