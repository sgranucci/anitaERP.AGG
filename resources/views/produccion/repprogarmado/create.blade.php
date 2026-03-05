@extends("theme.$theme.layout")
@section('titulo')
    Programaci&oacute;n de Armado
@endsection

@section("scripts")
<script>
    $(function () {
        $("#ordenestrabajo").focus();
    });

    // Previene que se presione enter y envie el formulario por lector QR
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('input[type=text]').forEach( node => node.addEventListener('keypress', e => {
        if(e.keyCode == 13) {
            e.preventDefault();
        }
    }))
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
                <h3 class="card-title">Datos Reporte Programaci&oacute;n de armado</h3>
            </div>
            <form action="{{route('crear_repprogarmado')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('produccion.repprogarmado.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-genera-excel', array('ruta' => 'crear_repprogarmado'))
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
