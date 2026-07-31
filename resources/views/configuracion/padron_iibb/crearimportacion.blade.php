@extends("theme.$theme.layout")
@section('titulo')
    Importar Padrón IIBB
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/provincia/consulta.js")}}" type="text/javascript"></script>
<script>
    $(function () {
        activa_eventos_consultaprovincia();

        $("#tipopadron").hide();
        $("#codigoprovincia").focus();

        $("#codigoprovincia").change(function(){
            var codigoprovincia = $(this).val();

            // Si es tucuman activa tipo de padron
            if (codigoprovincia == 24)
                $("#tipopadron").show();
            else
                $("#tipopadron").hide();
        });

        var form = document.getElementById('form-general');
        var overlay = document.getElementById('padron-iibb-import-overlay');
        if (form && overlay) {
            form.addEventListener('submit', function (e) {
                if (!form.checkValidity()) {
                    return;
                }
                var fileInput = document.getElementById('file');
                var rutaInput = document.getElementById('ruta_servidor');
                var tieneFile = fileInput && fileInput.files && fileInput.files.length > 0;
                var tieneRuta = rutaInput && rutaInput.value.trim() !== '';
                if (!tieneFile && !tieneRuta) {
                    e.preventDefault();
                    alert('Indique un archivo a subir o una ruta en el servidor.');
                    return;
                }
                overlay.classList.remove('d-none');
                overlay.style.display = 'flex';
                overlay.setAttribute('aria-hidden', 'false');
            });
            window.addEventListener('pageshow', function () {
                overlay.classList.add('d-none');
                overlay.style.display = '';
                overlay.setAttribute('aria-hidden', 'true');
            });
        }
    });
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Importar Padrón IIBB</h3>
                <div class="card-tools">
                    <a href="{{route('padron_iibb')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('importar_padron_iibb')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    @include('configuracion.padron_iibb.formimportar')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-importar')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.configuracion.modalconsultaprovincia')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'padron-iibb-import-overlay',
    'tituloId' => 'padron-iibb-import-titulo',
    'subtituloId' => 'padron-iibb-import-subtitulo',
    'titulo' => 'Encolando importación…',
    'subtitulo' => 'CABA y ARBA se procesan en background. Puede cerrar esta pantalla luego del mensaje de confirmación.',
])
@endsection
