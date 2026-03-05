@extends("theme.$theme.layout")
@section('titulo')
    Configurar Salidas
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/salida.js")}}" type="text/javascript"></script>
<script>

    $(function () {
        var programa = $("#programa").val();
        buscarSalida(programa);

        setTimeout(() => {
        }, 300);
    });

	function actualizar()
	{
        var programa = $("#programa").val();
        var salida_id = $("#salida_id").val();
        var urlRetorno = $("#urlretorno").val();

        if (programa == '')
            programa = 'xx';

        // Actualiza configuracion de salida
        var listarUri = "/anitaERP/public/configuracion/setearsalida/"+programa+"/"+salida_id;

        $.get(listarUri, function(data){
            setTimeout(() => {
                window.history.back();
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
                <h3 class="card-title">Configurar Salida {{$programa}}</h3>
                <div class="card-tools">
                    <a href="javascript:history.back()" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver atrás
                    </a>
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
