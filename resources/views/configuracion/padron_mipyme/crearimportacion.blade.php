@extends("theme.$theme.layout")
@section('titulo')
    Importar Padrón Mipyme
@endsection

@section("scripts")
<script>
    window.padronMipymePreanalisisUrl = @json(route('preanalizar_padron_mipyme'));
</script>
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/padron_mipyme/importar.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Importar Padrón Mipyme</h3>
                <div class="card-tools">
                    <a href="{{route('padron_mipyme')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('importar_padron_mipyme')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    @include('configuracion.padron_mipyme.formimportar')
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

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'padron-mipyme-import-overlay',
    'tituloId' => 'padron-mipyme-import-titulo',
    'subtituloId' => 'padron-mipyme-import-subtitulo',
    'titulo' => 'Procesando padrón…',
    'subtitulo' => 'Si el archivo es un ZIP se descomprime primero. Puede demorar. No cierre la página.',
])
@endsection
