@extends("theme.$theme.layout")
@section('titulo')
    Configurar Salidas
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/salida.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/salida-configurar.js")}}" type="text/javascript"></script>
<script>

    $(function () {
        buscarSalida($("#programa").val());
    });

	function actualizar()
	{
        var programa = $("#programa").val();
        var salida_id = $("#salida_id").val();
        var urlRetorno = $("#urlretorno").val();

        if (!salida_id) {
            alert('Seleccione una impresora.');
            return;
        }

        var listarUri = carpetaBase + '/configuracion/setearsalida/'
            + encodeURIComponent(programa)
            + '/'
            + encodeURIComponent(salida_id);
        if ($('#disparar_al_grabar').length) {
            listarUri += '?disparar_al_grabar=' + ($('#disparar_al_grabar').is(':checked') ? '1' : '0');
        }

        $.get(listarUri, function(){
            volverConfigurarSalida();
        });
    }

    function volverConfigurarSalida()
    {
        if (document.body.classList.contains('modo-consulta')) {
            if (typeof window.cerrarSolapaConsulta === 'function') {
                window.cerrarSolapaConsulta();
            } else {
                window.close();
            }
            return false;
        }
        var urlRetorno = ($('#urlretorno').val() || '').trim();
        if (urlRetorno) {
            window.location.href = urlRetorno;
            return false;
        }
        if (window.history.length > 1) {
            window.history.back();
            return false;
        }
        window.close();
        return false;
    }

</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Configurar impresora — {{ $programaEtiqueta ?? $programa }}</h3>
                <div class="card-tools">
                    @if (request()->input('vista') === 'consulta')
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="window.close()">
                            <i class="fa fa-fw fa-times"></i> Cerrar solapa
                        </button>
                    @else
                        <a href="{{ $urlRetorno ?: '#' }}" class="btn btn-outline-info btn-sm" onclick="return volverConfigurarSalida();">
                            <i class="fa fa-fw fa-reply-all"></i> Volver atrás
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @include('configuracion.salida.formconfigurar')
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col-lg-3"></div>
                    <div class="col-lg-6">
                        <button type="submit" onclick="actualizar()" class="btn btn-success">Actualizar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
