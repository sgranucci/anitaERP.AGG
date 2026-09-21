@extends("theme.$theme.layout")
@section('titulo')
    Nuevo cambio / devolución marketplace
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script>
window.cdmBuscarVentaUrl = @json(route('api_buscar_venta_cambio_devolucion_marketplace'));
</script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/cambio_devolucion/form.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-primary">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Nuevo legajo cambio / devolución marketplace</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('facturacion_local_cambios_devolucion') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form id="form-cambio-devolucion" method="POST" action="{{ route('guardar_cambio_devolucion_marketplace') }}" enctype="multipart/form-data" class="form-horizontal">
                @csrf
                <div class="card-body">
                    @include('ventas.facturacion_local.cambio_devolucion.partials.form')
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success botonsubmit">
                        <i class="fa fa-save"></i> Guardar borrador
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
