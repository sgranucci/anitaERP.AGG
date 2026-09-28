@extends("theme.$theme.layout")
@section('titulo')
    Programaci&oacute;n de Armado
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/produccion/repprogarmado/reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/produccion/repprogarmado/reporte.js')) ?: time() }}"></script>
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
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'repprogarmado-overlay',
    'tituloId' => 'repprogarmado-overlay-titulo',
    'subtituloId' => 'repprogarmado-overlay-subtitulo',
    'titulo' => 'Generando reporte…',
    'subtitulo' => 'Puede demorar según las órdenes. Pulse Esc para cerrar este aviso.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Datos Reporte Programaci&oacute;n de armado</h3>
            </div>
            {{-- data-sin-bloqueo-grabacion: el POST descarga el archivo y no navega; el banner global quedaría pegado --}}
            <form action="{{route('crear_repprogarmado')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off"
                data-sin-bloqueo-grabacion="1">
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
