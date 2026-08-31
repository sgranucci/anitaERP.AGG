@extends("theme.$theme.layout")
@section('titulo')
    Nueva configuración canon municipal
@endsection

@section('contenido')
    <div class="row">
        <div class="col-lg-10">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Nueva configuración canon municipal</h3>
                    <div class="card-tools">
                        <a href="{{ route('canon_municipal_config') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-reply-all"></i> Volver
                        </a>
                    </div>
                </div>
                <form action="{{ route('guardar_canon_municipal_config') }}" method="POST" class="form-horizontal" autocomplete="off">
                    @csrf
                    <div class="card-body">
                        @include('contable.canon_municipal_config.form')
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
