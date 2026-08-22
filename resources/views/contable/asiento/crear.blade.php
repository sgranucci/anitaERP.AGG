@extends("theme.$theme.layout")
@section('titulo')
    Asientos contables
@endsection

@section("scripts")
<link rel="stylesheet" href="{{ asset('assets/pages/scripts/contable/asiento/referencias.css') }}">
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/asiento/montos_formato.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/asiento/referencias.js")}}" type="text/javascript"></script>
<script>
    // Ruta relativa con carpetaBase (APP_URL no incluye /anitaERP/public).
    window.asientoValidarFechaCierreUrl = (window.carpetaBase || '').replace(/\/$/, '')
        + '/contable/cierre-periodo/validar-fecha';
</script>
<script src="{{ asset('assets/pages/scripts/contable/asiento/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/asiento/crear.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/asiento/validar_fecha_cierre.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/asiento/validar_fecha_cierre.js')) ?: time() }}" type="text/javascript"></script>
<script>
    window.usuarioTieneRestriccionCuentas = @json($usuarioTieneRestriccionCuentas ?? false);
</script>
<script>
$( "#botonform0" ).click(function() {
    if (typeof window.asientoFechaCierrePermitidaSync === 'function'
        && !window.asientoFechaCierrePermitidaSync()) {
        return;
    }

    let flError = false;

    $("#tbody-cuenta-table .moneda").each(function() {
        if ($(this).val() === '')
        {
            alert("Debe ingresar moneda");
            flError = true;
        }
    });

    if (!flError) {
        var monedaRef = null;
        $("#tbody-cuenta-table tr.item-cuenta").each(function () {
            var parseM = window.AsientoMontosFormato
                ? AsientoMontosFormato.parseDecimal.bind(AsientoMontosFormato)
                : function (v) { return parseFloat(String(v || '').replace(/\./g, '').replace(',', '.')) || 0; };
            var debe = parseM($(this).find('.debe').val());
            var haber = parseM($(this).find('.haber').val());
            if (debe <= 0 && haber <= 0) {
                return;
            }
            var mon = String($(this).find('.moneda').val() || '');
            if (monedaRef === null) {
                monedaRef = mon;
                return;
            }
            if (mon !== monedaRef) {
                alert("El asiento no puede mezclar monedas. La moneda la fija el primer movimiento.");
                flError = true;
                return false;
            }
        });
    }

    let totDebe = 0;
    let totHaber = 0;
    let parseMonto = window.AsientoMontosFormato
        ? AsientoMontosFormato.parseDecimal.bind(AsientoMontosFormato)
        : function (v) {
            if (v == null || v === '') return 0;
            var t = String(v).trim().replace(/\s/g, '');
            if (t.indexOf(',') >= 0) {
                t = t.replace(/\./g, '').replace(',', '.');
            } else if (/^\d{1,3}(\.\d{3})+$/.test(t)) {
                t = t.replace(/\./g, '');
            }
            var n = parseFloat(t);
            return isNaN(n) ? 0 : Math.round(n * 100) / 100;
        };

    $("#tbody-cuenta-table .debe").each(function() {
        let valor = parseMonto($(this).val());
        if (valor >= 0) totDebe += valor;
    });

    $("#tbody-cuenta-table .haber").each(function() {
        let valor = parseMonto($(this).val());
        if (valor >= 0) totHaber += valor;
    });

    if (totDebe - totHaber > 0.009 || totHaber - totDebe > 0.009)
    {
        alert("No coincide el debe con el haber, diferencia " + (totDebe - totHaber));
        flError = true;
    }

    if (!flError)
        $( "#form-general" ).submit();
});
</script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('asiento', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear Asiento</h3>
                <div class="card-tools">
                    @if (can('crear-asiento', false))
                        <a href="{{ route('crear_importacion_asiento') }}" class="btn btn-outline-success btn-sm" title="Importar asientos desde Excel">
                            <i class="fa fa-fw fa-file-excel"></i> Importar Excel
                        </a>
                    @endif
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_asiento')}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('includes.tabs-activas-estilos')
                    <div class="tabs-activas">
                        <ul class="nav nav-tabs" id="tabs-asiento" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="tab-asiento-datos-link" data-toggle="tab" href="#tab-asiento-datos" role="tab">
                                    <i class="fa fa-book"></i> Datos principales
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="tab-asiento-archivos-link" data-toggle="tab" href="#tab-asiento-archivos" role="tab">
                                    <i class="fa fa-paperclip"></i> Archivos asociados
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="tab-asiento-datos" role="tabpanel">
                            @include('contable.asiento.form')
                        </div>
                        <div class="tab-pane fade" id="tab-asiento-archivos" role="tabpanel">
                            @include('contable.asiento.form2')
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
							<button type="button" id="botonform0" class="btn btn-success">
						     	<i class="fa fa-save"></i> Guardar
							</button>
                    	</div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@include('contable.asiento.partials.modal_aprobacion_cuentas')
@include('contable.asiento.partials.modal_periodo_cerrado')
@endsection
