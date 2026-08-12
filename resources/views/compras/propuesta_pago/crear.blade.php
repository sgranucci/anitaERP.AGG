@extends("theme.$theme.layout")
@section('titulo')
    Nueva propuesta de pagos
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
            <div class="card-header">
                <h3 class="card-title">Crear propuesta de pagos</h3>
                <div class="card-tools">
                    <a href="{{ route('propuesta_pago') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_propuesta_pago') }}" method="POST" id="form-general" class="form-horizontal" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('compras.propuesta_pago.form')
                </div>
                <div class="card-footer">
                    @include('includes.boton-form-crear')
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
