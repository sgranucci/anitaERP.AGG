@extends("theme.$theme.layout")
@section('titulo')
    Importar Precios
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script>
    window.precioImportPreviewUrl = @json(route('precio_import_preview'));
</script>
<script src="{{asset('assets/pages/scripts/stock/precio/importar.js')}}?v=20260710b" type="text/javascript"></script>
@if (session('precio_import_resultado'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var banner = document.getElementById('banner-resultado-import-precio');
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
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Importar Precios de Excel</h3>
                <div class="card-tools">
                    <a href="{{route('precio')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('importar_precio')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    @include('stock.precio.partials.flash_resultado_importacion')
                    @include('stock.precio.formimportar')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="submit" class="btn botonsubmit btn-success">
                                <i class="fa fa-upload"></i> Importar precios
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
