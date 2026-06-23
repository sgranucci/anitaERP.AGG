@extends("theme.$theme.layout")
@section('titulo')
    Exporta Operaciones UIF
@endsection

@section("scripts")

<script src="{{asset("assets/pages/scripts/configuracion/salida.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/configurar_salida.js")}}" type="text/javascript"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>
<script>
window.seteoSalidaPrograma = @json(\App\Support\Configuracion\SeteoSalidaProgramaSupport::UIF_EXPORTA_OPERACION);
window.seteoSalidaConfigurarUrl = @json(route('configurar_salida', ['programa' => ':programa']));

$(function () {
    $('.periodo').datepicker({
        format: 'yyyy-mm',
        minViewMode: 'months',
        autoclose: true,
        changeMonth: true,
        changeYear: true,
        clearBtn: false,
        todayHighlight: true,
    });
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
                <h3 class="card-title">Datos Exportación de Operaciones UIF</h3>
				@include('includes.configurar-salida')
            </div>
            <form action="{{route('generar_exporta_operacion')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('uif.exportaoperacion.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
							<input type="submit" name="extension" id="extension" class="btn-sm btn-info" value="Exporta Operaciones"></input>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
