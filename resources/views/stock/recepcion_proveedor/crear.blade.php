@extends("theme.$theme.layout")
@section('titulo')
Nueva recepción de proveedor
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor/form.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/consulta_oc.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
@if (config('recepcion_proveedor.modal_articulo_proveedor_habilitado'))
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/modal_articulo_proveedor.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor/modal_articulo_proveedor.js')) ?: time() }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-truck"></i> Nueva recepción de proveedor</h3>
                <div class="card-tools">
                    <a href="{{ route('recepcion_proveedor') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_recepcion_proveedor') }}" id="form-recepcion-proveedor" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('stock.recepcion_proveedor.form', ['modoEdicion' => true, 'recepcion' => null])
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@if(!($modoDevolucion ?? false))
@include('stock.recepcion_proveedor.partials.modal_accion_lineas_sin_cantidad')
@endif
@include('includes.stock.modalconsultadeposito')
@include('includes.stock.modalconsultaordencompra_recepcion')
@endsection
