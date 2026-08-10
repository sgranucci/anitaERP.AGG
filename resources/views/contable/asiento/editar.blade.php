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
<script src="{{asset("assets/pages/scripts/contable/asiento/crear.js")}}" type="text/javascript"></script>
<script>
    $( "#botonform0" ).click(function() {
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
    $asientoConOrigenProceso = \App\Support\Contable\AsientoOrigenProcesoSupport::tieneOrigenProceso($data ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar Asiento — {{ $data->tipoasientos->nombre ?? '' }} N° {{ $data->numeroasiento }}</h3>
                <div class="card-tools">
                    @if (can('crear-asiento', false))
                        <a href="{{ route('crear_importacion_asiento') }}" class="btn btn-outline-success btn-sm" title="Importar asientos desde Excel">
                            <i class="fa fa-fw fa-file-excel"></i> Importar Excel
                        </a>
                    @endif
                    @if (can('listar-asiento', false) || can('editar-asiento', false))
                    <a href="{{ route('imprimir_pdf_asiento', ['id' => $data->id]) }}" class="btn btn-outline-danger btn-sm" title="Emitir asiento en PDF" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-file-pdf"></i> Emitir PDF
                    </a>
                    <a href="{{ route('imprimir_excel_asiento', ['id' => $data->id]) }}" class="btn btn-outline-success btn-sm" title="Emitir asiento en Excel" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-file-excel"></i> Emitir Excel
                    </a>
                    @endif
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    <button type="button" id="botonform3" class="btn btn-outline-secondary btn-sm">
                        <span class="fa fa-copy"></span> Copia asiento
                    </button>
                    @if ($asientoConOrigenProceso)
                        <button type="button" class="btn btn-secondary btn-sm" disabled
                                title="{{ \App\Support\Contable\AsientoOrigenProcesoSupport::mensajeBloqueo($data ?? [], 'revertir') }}">
                            <span class="fa fa-history"></span> Revierte asiento
                        </button>
                    @else
                        <button type="button" id="botonform4" class="btn btn-outline-secondary btn-sm">
                            <span class="fa fa-history"></span> Revierte asiento
                        </button>
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_asiento', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf @method("put")
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
                                    @if (($data->asiento_archivos->count() ?? 0) > 0)
                                        <span class="badge badge-info">{{ $data->asiento_archivos->count() }}</span>
                                    @endif
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
                                <i class="fa fa-save"></i> Actualizar
                            </button>
                    	</div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
