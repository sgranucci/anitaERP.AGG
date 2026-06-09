@extends("theme.$theme.layout")
@section('titulo')
    Etiquetas de OT
@endsection

@section("styles")

<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css" rel="stylesheet"/>

@endsection

@section("scripts")

<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>
<script src="{{asset("assets/pages/scripts/configuracion/salida.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/configurar_salida.js")}}" type="text/javascript"></script>

<script>
    window.resolverSeteoSalidaPrograma = function () {
        var tipo = $('#tipoetiqueta').val() || '';

        if (!tipo) {
            return @json(\App\Support\Configuracion\SeteoSalidaProgramaSupport::VENTAS_REPETIQUETAOT);
        }

        return @json(\App\Support\Configuracion\SeteoSalidaProgramaSupport::VENTAS_REPETIQUETAOT) + '_' + String(tipo).toLowerCase().replace(/[^a-z0-9]+/gi, '_').replace(/^_+|_+$/g, '');
    };

    window.seteoSalidaConfigurarUrl = @json(route('configurar_salida', ['programa' => ':programa']));

    $(function () {
        imprimirSalida();

        $('#tipoetiqueta').on('change', function (event) {
            event.preventDefault();
            imprimirSalida();
        });
    });

    jQuery(document).ready(function($){
        $(document).ready(function() {
            $('.mi-selector').select2();
        });
    });
    $("#ordenestrabajo").focus();
</script>

@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Datos Emisi&oacuten de etiquetas OT</h3>
                <h3 class="card-title" id="nombresalida" style="color:black;"></h3>
                <div class="card-tools">
                    <a href="#" onclick="configurarSalida()" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-cog"></i> Configura salida
                    </a>
                    <a href="{{route('genera_zpl')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Genera Foto para Etiqueta
                    </a>
                    <a href="{{route('generaetiquetaprueba')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Genera Etiqueta Prueba
                    </a>
            	</div>
            </div>
            <form action="{{route('crear_repetiquetaot')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('ventas.repetiquetaot.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
							<input type="submit" name="extension" id="extension" class="btn-sm btn-info" value="Emite Etiquetas"></input>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
