@extends("theme.$theme.layout")
@section('titulo')
    Crear t&eacute;cnico de laboratorio
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.mensaje-error-form')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Crear t&eacute;cnico de laboratorio</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_tecnico_laboratorio') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_tecnico_laboratorio') }}" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('sala.tecnico_laboratorio.form')
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
