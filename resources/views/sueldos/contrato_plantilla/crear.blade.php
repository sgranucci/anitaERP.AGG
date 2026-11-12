@extends("theme.$theme.layout")
@section('titulo')
    Nueva plantilla de contrato
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
                <h3 class="card-title">Nueva plantilla Word</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_contrato_plantilla_sueldos') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_contrato_plantilla_sueldos') }}" method="POST" enctype="multipart/form-data" data-enter-campos="1" class="form-horizontal">
                @csrf
                <div class="card-body">
                    @include('sueldos.contrato_plantilla.partials.form')
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success botonsubmit">
                        <i class="fa fa-save"></i> Guardar plantilla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
