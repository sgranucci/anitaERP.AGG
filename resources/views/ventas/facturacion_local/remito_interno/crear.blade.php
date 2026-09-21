@extends("theme.$theme.layout")
@section('titulo')
    Nuevo remito interno
@endsection
@section('scripts')
<script>
window.RI_CFG = {
    urls: {
        buscar: @json(route('api_buscar_articulo_remito_interno')),
        variantes: @json(url('ventas/facturacion-local/remitos-internos/api/variantes'))
    },
    csrf: @json(csrf_token()),
    editable: true
};
</script>
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/remito_interno/form.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-primary">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Nuevo remito interno</h3>
                <a href="{{ route('facturacion_local_remitos_internos') }}" class="btn btn-outline-info btn-sm">
                    <i class="fa fa-reply-all"></i> Volver al listado
                </a>
            </div>
            <form action="{{ route('guardar_remito_interno') }}" method="POST" id="form-remito-interno" class="form-horizontal" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('ventas.facturacion_local.remito_interno.partials.form', [
                        'data' => $data,
                        'locales' => $locales,
                        'editable' => true,
                    ])
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary botonsubmit">
                        <i class="fa fa-save"></i> Guardar borrador
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
