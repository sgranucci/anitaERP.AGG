@extends("theme.$theme.layout")
@section('titulo')
    Nueva configuración canon entidades
@endsection

@section('contenido')
    <div class="row">
        <div class="col-lg-10">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Nueva configuración canon entidades</h3>
                    <div class="card-tools">
                        <a href="{{ route('canon_entidades_config') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-reply-all"></i> Volver
                        </a>
                    </div>
                </div>
                <form action="{{ route('guardar_canon_entidades_config') }}" method="POST" class="form-horizontal" autocomplete="off">
                    @csrf
                    <div class="card-body">
                        @include('contable.canon_entidades_config.form')
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
@include('contable.canon_entidades_config.partials.scripts_form')
@endsection
