@extends("theme.$theme.layout")
@section('titulo')
    Nuevo marketplace
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection
@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Nuevo marketplace</h3>
                <div class="card-tools ml-auto">
                    <a href="{{ route('facturacion_local_marketplaces') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_marketplace') }}" method="POST" id="form-general" class="form-horizontal" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('ventas.facturacion_local.marketplace.partials.form')
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
