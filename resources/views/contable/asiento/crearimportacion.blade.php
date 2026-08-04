@extends("theme.$theme.layout")
@section('titulo')
    Importar asiento desde Excel
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script>
    window.asientoImportPreviewUrl = @json(route('asiento_import_preview'));
</script>
<script src="{{ asset('assets/pages/scripts/contable/asiento/importar.js') }}?v=20260803a" type="text/javascript"></script>
@if (session('asiento_import_resultado'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var banner = document.getElementById('banner-resultado-import-asiento');
        if (banner) {
            banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Importar asiento contable desde Excel</h3>
                <div class="card-tools">
                    <a href="{{ route('asiento') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('importar_asiento') }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    @include('contable.asiento.partials.flash_resultado_importacion')
                    @include('contable.asiento.formimportar')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="submit" class="btn botonsubmit btn-success" id="btn-importar-asiento">
                                <i class="fa fa-upload"></i> Importar asiento
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'asiento-import-overlay',
    'tituloId' => 'asiento-import-titulo',
    'subtituloId' => 'asiento-import-subtitulo',
    'titulo' => 'Importando asiento…',
    'subtitulo' => 'Puede demorar según la cantidad de filas. No cierre la página.',
])
@endsection
