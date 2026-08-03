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

        // Valida montos
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

            if (valor >= 0)
                totDebe += valor;
        });

        $("#tbody-cuenta-table .haber").each(function() {
            let valor = parseMonto($(this).val());

            if (valor >= 0)
                totHaber += valor;
        });

        if (totDebe - totHaber > 0.009 || totHaber - totDebe > 0.009)
        {
            diferencia = totDebe - totHaber;
            
            alert("No coincide el debe con el haber, diferencia "+diferencia);
            flError = true;
        }

        if (!flError)
            $( "#form-general" ).submit();
    });
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar Asiento - {{$data->tipoasientos->nombre}}- Numero {{$data->numeroasiento}}</h3>
                <div class="card-tools">
                    @if (can('listar-asiento', false) || can('editar-asiento', false))
                    <a href="{{ route('imprimir_pdf_asiento', ['id' => $data->id]) }}" class="btn btn-primary btn-sm" title="Emitir asiento en PDF" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-file-pdf"></i> Emitir PDF
                    </a>
                    @endif
                    <a href="{{route('asiento')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Copia asiento
                    </button>
                    @php
                        $asientoConOrigenProceso = \App\Support\Contable\AsientoOrigenProcesoSupport::tieneOrigenProceso($data ?? []);
                    @endphp
                    @if ($asientoConOrigenProceso)
                        <button type="button" class="btn btn-secondary btn-sm" disabled
                                title="{{ \App\Support\Contable\AsientoOrigenProcesoSupport::mensajeBloqueo($data ?? [], 'revertir') }}">
                            <span class="fa fa-history"></span> Revierte asiento
                        </button>
                    @else
                        <button type="button" id="botonform4" class="btn btn-info btn-sm">
                            <span class="fa fa-history"></span> Revierte asiento
                        </button>
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_asiento', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf @method("put")
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Archivos asociados
                    </button>
                </div>
                <div class="card-body">
                    @include('contable.asiento.form')
                    @include('contable.asiento.form2')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-4">
                        	<button type="button" id="botonform0" class="btn btn-success">Actualizar</button>
                    	</div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
