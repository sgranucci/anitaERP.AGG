@extends("theme.$theme.layout")
@section('titulo')
    Editar local de venta
@endsection
@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/crear.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/depmae/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/listaprecio/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/listaprecio/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cuentacaja/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cuentacontable/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/puntoventa/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/puntoventa/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/tipotransaccion/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/tipotransaccion/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script>
window.FACTURACION_LOCAL = {
    usocuentacajaLocalId: {{ (int) ($usocuentacaja_local_id ?? 0) }},
};
window.localVentaFormCfg = {
    localId: {{ (int) $data->id }},
    syncDepositosUrl: @json(route('sync_depositos_anita_local_venta_id', $data->id)),
    usocuentacajaLocalId: {{ (int) ($usocuentacaja_local_id ?? 0) }},
};
</script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/local_venta/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/facturacion_local/local_venta/form.js')) ?: time() }}" type="text/javascript"></script>
@endsection
@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar local #{{ $data->id }}</h3>
                <div class="card-tools">
                    <a href="{{ route('facturacion_local_locales') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_local_venta', $data->id) }}" method="POST" id="form-general" class="form-horizontal" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('ventas.facturacion_local.local_venta.form')
                </div>
                <div class="card-footer">
                    @include('includes.boton-form-editar')
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultadeposito')
@include('includes.stock.modalconsultalistaprecio')
@include('includes.caja.modalconsultacuentacaja')
@include('includes.contable.modalconsultacuentacontable')
@include('includes.ventas.modalconsultapuntoventa')
@include('includes.ventas.modalconsultatipotransaccion')
@endsection
