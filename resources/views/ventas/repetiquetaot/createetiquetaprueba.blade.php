@extends("theme.$theme.layout")
@section('titulo')
    Genera Etiqueta de Prueba
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/configuracion/salida.js")}}" type="text/javascript"></script>
<script>
    $(function () {
        imprimirSalida();
    });

    function imprimirSalida()
    {
        buscarSalida("");

        setTimeout(() => {
            $("#nombresalida").text(" - Imprime en: "+nombreSalida);
        }, 300);
    }
    
    function configurarSalida()
    {
        var url = "{{ route('configurar_salida', ['programa' => ':programa']) }}";
        var programa = "";

        url = url.replace(':programa', programa);
        location.href = url;
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
                <h3 class="card-title">Genera etiqueta de prueba</h3>
                <h3 class="card-title" id="nombresalida" style="color:black;"></h3>
                <div class="card-tools">
                    <a href="#" onclick="configurarSalida()" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-cog"></i> Configura salida
                    </a>
            	</div>
            </div>
            <form action="{{route('crear_repetiquetapruebaot')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('ventas.repetiquetaot.formetiquetaprueba')
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

