@extends("theme.$theme.layout")
@section('titulo')
    Configurar modelo de etiqueta
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/modeloetiqueta.js")}}" type="text/javascript"></script>
<script>

    $(function () {
        buscarModeloetiqueta($("#programa").val());

        setTimeout(function () {
            if (!nombreModeloEtiqueta) {
                return;
            }

            $('#modeloetiqueta_id option').filter(function () {
                return $(this).text().trim() === nombreModeloEtiqueta;
            }).prop('selected', true);
        }, 300);
    });

	function actualizar()
	{
        var programa = $("#programa").val();
        var modeloetiqueta_id = $("#modeloetiqueta_id").val();
        var urlRetorno = $("#urlretorno").val();

        if (!modeloetiqueta_id) {
            alert('Seleccione un modelo de etiqueta.');
            return;
        }

        var listarUri = carpetaBase + '/configuracion/setearmodeloetiqueta/'
            + encodeURIComponent(programa)
            + '/'
            + encodeURIComponent(modeloetiqueta_id);

        $.get(listarUri, function () {
            if (urlRetorno) {
                window.location.href = urlRetorno;
                return;
            }

            window.history.back();
        });
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
                <h3 class="card-title">Configurar modelo de etiqueta — {{ $programaEtiqueta ?? $programa }}</h3>
                <div class="card-tools">
                    <a href="javascript:history.back()" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver atrás
                    </a>
                </div>
            </div>
            <div class="card-body">
                @include('configuracion.modeloetiqueta.formconfigurar')
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
