@extends("theme.$theme.layout")
@section('titulo')
    Crear órdenes de trabajo
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/combinacion/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/combinacion/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/ordentrabajo/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/ordentrabajo/crear.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear orden de trabajo</h3>
                <div class="card-tools">
                    <a href="{{ route('ordentrabajo') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('consultar_pendiente_ot') }}" id="form-general" class="form-horizontal" method="POST" autocomplete="off">
                @csrf
                @include('ventas.ordentrabajo.form')
            </form>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultacombinacion')
@endsection
