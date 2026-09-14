@extends("theme.$theme.layout")
@section('titulo')
    Editar turno
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
                <h3 class="card-title">Editar turno #{{ $data->id }}</h3>
                <div class="card-tools">
                    <a href="{{ route('facturacion_local_turno') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_turno_local', $data->id) }}" method="POST" id="form-general" class="form-horizontal" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('ventas.facturacion_local.turno_local.form')
                </div>
                <div class="card-footer">
                    @include('includes.boton-form-editar')
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
