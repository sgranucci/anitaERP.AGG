@extends("theme.$theme.layout")
@section('titulo')
    Editar plantilla de contrato
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
                <h3 class="card-title">Plantilla {{ $data->codigo }}</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_contrato_plantilla_sueldos') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_contrato_plantilla_sueldos', $data->id) }}" method="POST" enctype="multipart/form-data" data-enter-campos="1" class="form-horizontal">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('sueldos.contrato_plantilla.partials.form')
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success botonsubmit">
                        <i class="fa fa-save"></i> Actualizar plantilla
                    </button>
                    <a href="{{ route('descargar_contrato_plantilla_sueldos', $data->id) }}" class="btn btn-outline-secondary">
                        <i class="fa fa-download"></i> Descargar Word actual
                    </a>
                </div>
            </form>
        </div>

        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">Tags sugeridos</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach ($tags as $tag => $desc)
                        <div class="col-md-4 col-lg-3 mb-1 small">
                            <code>${{ $tag }}$</code> — {{ $desc }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
