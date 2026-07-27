@extends("theme.$theme.layout")
@section('titulo')
    Importar usuarios
@endsection

@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/ventas/vendedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/usuario/empresas_roles.js') }}" type="text/javascript"></script>
<script>
    window.usuarioImportPreviewUrl = @json(route('usuario_import_preview'));
</script>
<script src="{{ asset('assets/pages/scripts/admin/usuario/importar.js') }}?v=20260723b" type="text/javascript"></script>
@if (session('usuario_import_resultado'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var banner = document.getElementById('banner-resultado-import-usuario');
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
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Carga masiva de usuarios desde Excel</h3>
                <div class="card-tools">
                    @if (session('rol_nombre') === 'administrador' || session('rol_nombre') === 'Enc-sistemas')
                        <a href="{{ route('usuario') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('importar_usuario') }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    @include('admin.usuario.partials.flash_resultado_importacion')
                    @include('admin.usuario.formimportar')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="submit" class="btn botonsubmit btn-success" id="btn-importar-usuarios">
                                <i class="fa fa-upload"></i> Importar usuarios
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'usuario-import-overlay',
    'tituloId' => 'usuario-import-titulo',
    'subtituloId' => 'usuario-import-subtitulo',
    'titulo' => 'Importando usuarios…',
    'subtitulo' => 'Puede demorar según la cantidad de filas. No cierre la página.',
])
@include('includes.ventas.modalconsultavendedor')
@endsection
