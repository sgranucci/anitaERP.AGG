@extends("theme.$theme.layout")
@section('titulo')
    Nuevo turno
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
                <h3 class="card-title mb-0">Nuevo turno</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('facturacion_local_turno') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_turno_local') }}" method="POST" id="form-general" class="form-horizontal" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('ventas.facturacion_local.turno_local.form')
                </div>
                <div class="card-footer">
                    @include('includes.boton-form-crear')
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
