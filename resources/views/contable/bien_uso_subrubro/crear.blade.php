@extends("theme.$theme.layout")
@section('titulo')
    Subrubros de bienes de uso
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/bien_uso_rubro/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/bien_uso_rubro/consulta.js')) }}" type="text/javascript"></script>
<script>
$(function () {
    if (typeof activa_eventos_consultabienusorubro === 'function') {
        activa_eventos_consultabienusorubro();
    }
});
</script>
@endsection
@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear subrubro</h3>
                <div class="card-tools">
                    <a href="{{ route('bien_uso_subrubro') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_bien_uso_subrubro') }}" id="form-general" class="form-horizontal" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">@include('contable.bien_uso_subrubro.form')</div>
                <div class="card-footer">
                    <div class="row"><div class="col-lg-3"></div><div class="col-lg-6">@include('includes.boton-form-crear')</div></div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultabienusorubro')
@endsection
