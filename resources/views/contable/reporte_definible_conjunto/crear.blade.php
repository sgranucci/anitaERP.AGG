@extends("theme.$theme.layout")
@section('titulo')
    Nuevo set de cuentas
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Nuevo set de cuentas</h3>
                <div class="card-tools">
                    <a href="{{ route('reporte_definible_conjunto') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form method="post" action="{{ route('guardar_reporte_definible_conjunto') }}" class="form-horizontal">
                @csrf
                <div class="card-body">
                    @include('includes.form-error')
                    @include('includes.mensaje')
                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Código</label>
                        <div class="col-lg-6">
                            <input type="text" name="codigo" class="form-control" maxlength="30"
                                   value="{{ old('codigo') }}" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Nombre</label>
                        <div class="col-lg-8">
                            <input type="text" name="nombre" class="form-control" maxlength="80"
                                   value="{{ old('nombre') }}" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Observaciones</label>
                        <div class="col-lg-8">
                            <textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones') }}</textarea>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-lg-8 offset-lg-3">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="activo" name="activo" value="1" checked>
                                <label class="custom-control-label" for="activo">Activo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
