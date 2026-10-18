@extends("theme.$theme.layout")
@section('titulo')
    Subrubros de bienes de uso
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/editar.js') }}" type="text/javascript"></script>
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
                <h3 class="card-title">Editar subrubro #{{ $data->id }}</h3>
                @if (empty($soloConsulta))
                <div class="card-tools">
                    <a href="{{ route('bien_uso_subrubro') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
                @endif
            </div>
            <form action="{{ route('actualizar_bien_uso_subrubro', $data->id) }}" id="form-general" class="form-horizontal" method="POST" autocomplete="off">
                @csrf @method('PUT')
                @if (! empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <div class="card-body">@include('contable.bien_uso_subrubro.form')</div>
                <div class="card-footer">
                    <div class="row"><div class="col-lg-3"></div><div class="col-lg-6">
                        @if (! empty($soloConsulta))
                            @include('includes.boton-form-consulta-modal')
                        @else
                            @include('includes.boton-form-editar')
                        @endif
                    </div></div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultabienusorubro')
@endsection
