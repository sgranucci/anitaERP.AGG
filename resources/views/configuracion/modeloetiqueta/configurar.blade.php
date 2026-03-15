@extends("theme.$theme.layout")
@section('titulo')
    Configurar Salidas
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/modeloetiqueta.js")}}" type="text/javascript"></script>
<script>

    $(function () {
        var programa = $("#programa").val();

        buscarModeloetiqueta(programa);

        setTimeout(() => {

            $('#modeloetiqueta_id option').filter(function() {
                // Use .text() and possibly .trim() to handle extra whitespace
                return $(this).text().trim() === nombreSalida;
            }).prop('selected', true);

        }, 300);

    });

	function actualizar()
	{
        var programa = $("#programa").val();
        var modeloetiqueta_id = $("#modeloetiqueta_id").val();
        var urlRetorno = $("#urlretorno").val();

        if (programa == '')
            programa = 'xx';

        // Actualiza configuracion de modeloetiqueta
        var listarUri = "/anitaERP/public/configuracion/setearmodeloetiqueta/"+programa+"/"+modeloetiqueta_id;

        $.get(listarUri, function(data){
            setTimeout(() => {
                //window.history.back();
                document.location.href=urlRetorno; 
            }, 300);	
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
                <h3 class="card-title">Configurar Modelo de Etiqueta {{$programa}}</h3>
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
